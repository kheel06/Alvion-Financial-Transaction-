<?php
/**
 * Employee Profile Page
 * Allows administrative/staff users to manage their profile sourced from department_accounts
 */
require_once '../../config/config.php';
requireAuth();

$account_allowed_roles = $account_allowed_roles ?? ['admin', 'doctor', 'staff', 'receptionist', 'finance staff'];
checkRole($account_allowed_roles);

$account_page_title = $account_page_title ?? 'Employee Profile';
$account_heading = $account_heading ?? 'Employee Profile';
$account_description = $account_description ?? 'Manage your department account information';
$account_upload_prefix = $account_upload_prefix ?? 'employee_profile';

$page_title = $account_page_title;

/**
 * Helper functions
 */
function employeeSanitizeIdentifier(string $identifier): string {
    $sanitized = preg_replace('/[^A-Za-z0-9_]/', '', $identifier);
    return $sanitized ?: '';
}

function employeeTableExists(PDO $db, string $table): bool {
    $table = employeeSanitizeIdentifier($table);
    if ($table === '') {
        return false;
    }
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table_name");
        $stmt->bindParam(':table_name', $table);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Table existence check failed for {$table}: " . $e->getMessage());
        return false;
    }
}

function employeeGetTableColumns(PDO $db, string $table): array {
    $table = employeeSanitizeIdentifier($table);
    if ($table === '') {
        return [];
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$table}`");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['Field'])) {
                $columns[] = $row['Field'];
            }
        }
        return $columns;
    } catch (PDOException $e) {
        error_log("Failed to fetch columns for {$table}: " . $e->getMessage());
        return [];
    }
}

function employeeFindFirstColumn(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

function employeeEnsureProfilePictureColumn(PDO $db, string $table): void {
    $table = employeeSanitizeIdentifier($table);
    if ($table === '' || !employeeTableExists($db, $table)) {
        return;
    }
    try {
        $stmt = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'profile_picture'");
        if ($stmt && $stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN profile_picture VARCHAR(255) NULL");
        }
    } catch (PDOException $e) {
        error_log("Failed to ensure profile_picture column on {$table}: " . $e->getMessage());
    }
}

/**
 * Remove employee profile picture
 * Deletes the file and updates the database, then clears session
 */
function removeEmployeeProfilePicture(PDO $db, string $table, string $field, $identifier, int $param_type, string $upload_dir, ?string $current_picture_path = null): array {
    $result = ['success' => false, 'message' => ''];
    
    $table = employeeSanitizeIdentifier($table);
    if ($table === '' || !employeeTableExists($db, $table)) {
        $result['message'] = "Invalid employee table.";
        return $result;
    }
    
    try {
        // Delete physical file if it exists
        if (!empty($current_picture_path)) {
            $old_file = $upload_dir . basename($current_picture_path);
            if (file_exists($old_file)) {
                if (!unlink($old_file)) {
                    error_log("Failed to delete employee profile picture file: {$old_file}");
                }
            }
        }
        
        // Update database to set profile_picture to NULL
        $identifierParam = $param_type === PDO::PARAM_INT ? (int)$identifier : $identifier;
        $deleteQuery = "UPDATE `{$table}` SET profile_picture = NULL WHERE `{$field}` = :identifier";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':identifier', $identifierParam, $param_type);
        
        if ($deleteStmt->execute()) {
            // Clear session variable so header.php reflects the change
            unset($_SESSION['profile_picture']);
            
            $result['success'] = true;
            $result['message'] = "Profile picture removed successfully.";
        } else {
            $result['message'] = "Failed to remove profile picture from database.";
        }
    } catch (PDOException $e) {
        error_log("Error removing employee profile picture: " . $e->getMessage());
        $result['message'] = "Error removing profile picture: " . $e->getMessage();
    }
    
    return $result;
}

/**
 * Resolve table name
 */
$employeeTableCandidates = ['department_accounts', 'department_account'];
$employeeTable = null;
foreach ($employeeTableCandidates as $candidate) {
    if (employeeTableExists($db, $candidate)) {
        $employeeTable = $candidate;
        break;
    }
}

if (!$employeeTable) {
    $_SESSION['error'] = "Employee profile table is missing. Please contact an administrator.";
    header("Location: ../../index.php");
    exit();
}

$employeeTable = employeeSanitizeIdentifier($employeeTable);
$employeeColumns = employeeGetTableColumns($db, $employeeTable);

if (empty($employeeColumns)) {
    $_SESSION['error'] = "Unable to inspect employee profile table.";
    header("Location: ../../index.php");
    exit();
}

$identifierValue = $_SESSION['employee_id'] ?? $_SESSION['user_id'] ?? null;
if ($identifierValue === null) {
    $_SESSION['error'] = "Employee identifier not found in session.";
    header("Location: ../../index.php");
    exit();
}

$identifierColumns = array_filter([
    employeeFindFirstColumn($employeeColumns, ['employee_id']),
    employeeFindFirstColumn($employeeColumns, ['user_id']),
    employeeFindFirstColumn($employeeColumns, ['id'])
]);

if (empty($identifierColumns)) {
    $_SESSION['error'] = "Employee table does not contain usable identifier columns.";
    header("Location: ../../index.php");
    exit();
}

$whereParts = [];
foreach ($identifierColumns as $column) {
    $whereParts[] = "`{$column}` = :identifier";
}
$whereClause = implode(' OR ', $whereParts);

try {
    $employeeQuery = "SELECT * FROM `{$employeeTable}` WHERE {$whereClause} LIMIT 1";
    $employeeStmt = $db->prepare($employeeQuery);
    $employeeStmt->bindParam(':identifier', $identifierValue);
    $employeeStmt->execute();
    $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        $_SESSION['error'] = "Employee record not found.";
        header("Location: ../../index.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to load employee profile: " . $e->getMessage();
    header("Location: ../../index.php");
    exit();
}

/**
 * Normalize employee data for display
 */
$first_name = $employee['first_name']
    ?? $employee['employee_fname']
    ?? $employee['fname']
    ?? '';
$last_name = $employee['last_name']
    ?? $employee['employee_lname']
    ?? $employee['lname']
    ?? '';
$email = $employee['employee_email']
    ?? $employee['email']
    ?? $employee['work_email']
    ?? '';
$username = $employee['username']
    ?? $employee['employee_id']
    ?? ($_SESSION['username'] ?? '');
$department = $employee['department'] ?? ($employee['assigned_department'] ?? '');
$role_name = $employee['role_name'] ?? $employee['role'] ?? ($_SESSION['role_name'] ?? 'employee');
$contact_number = $employee['contact_number'] ?? $employee['phone'] ?? $employee['mobile_number'] ?? '';
$employee_profile_picture = $employee['profile_picture'] ?? null;
// Get employee status - check multiple possible field names
$employee_status = null;
if (isset($employee['status'])) {
    $employee_status = $employee['status'];
} elseif (isset($employee['employee_status'])) {
    $employee_status = $employee['employee_status'];
} elseif (isset($employee['account_status'])) {
    $employee_status = $employee['account_status'];
} elseif (isset($employee['is_active'])) {
    // Convert is_active (0/1) to status string
    $employee_status = ((int)$employee['is_active'] === 1) ? 'active' : 'inactive';
} else {
    $employee_status = 'active'; // Default to active if no status field found
}

$first_initial = strtoupper(substr($first_name, 0, 1));
$last_initial = strtoupper(substr($last_name, 0, 1));
$user_initials = trim($first_initial . $last_initial) ?: 'EMP';

$profilePictureIdentifier = $employee['employee_id']
    ?? $employee['id']
    ?? $employee['user_id']
    ?? null;

$profilePictureContext = [
    'table' => $employeeTable,
    'field' => employeeFindFirstColumn($employeeColumns, ['employee_id', 'id', 'user_id']) ?? 'employee_id',
    'identifier' => $profilePictureIdentifier,
    'param_type' => is_numeric($profilePictureIdentifier) ? PDO::PARAM_INT : PDO::PARAM_STR,
];

$upload_dir = __DIR__ . '/../../assets/uploads/profile_pictures/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/**
 * Update basic information
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_first_name = sanitizeInput($_POST['first_name']);
    $new_last_name = sanitizeInput($_POST['last_name']);
    $new_email = sanitizeInput($_POST['email']);
    $new_username = sanitizeInput($_POST['username']);
    $new_contact = sanitizeInput($_POST['contact_number']);

    $updateColumns = [];
    $params = [];

    $firstNameCol = employeeFindFirstColumn($employeeColumns, ['first_name', 'employee_fname', 'fname']);
    $lastNameCol = employeeFindFirstColumn($employeeColumns, ['last_name', 'employee_lname', 'lname']);
    $emailCol = employeeFindFirstColumn($employeeColumns, ['employee_email', 'email', 'work_email', 'company_email']);
    $usernameCol = employeeFindFirstColumn($employeeColumns, ['username']);
    $contactCol = employeeFindFirstColumn($employeeColumns, ['contact_number', 'phone', 'mobile_number']);

    if ($firstNameCol) {
        $updateColumns[] = "`{$firstNameCol}` = :first_name";
        $params[':first_name'] = $new_first_name;
    }
    if ($lastNameCol) {
        $updateColumns[] = "`{$lastNameCol}` = :last_name";
        $params[':last_name'] = $new_last_name;
    }
    if ($emailCol) {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Invalid email address.";
            header("Location: employee-profile.php");
            exit();
        }
        $updateColumns[] = "`{$emailCol}` = :email";
        $params[':email'] = $new_email;
    }
    if ($usernameCol) {
        $updateColumns[] = "`{$usernameCol}` = :username";
        $params[':username'] = $new_username;
    }
    if ($contactCol) {
        $updateColumns[] = "`{$contactCol}` = :contact_number";
        $params[':contact_number'] = $new_contact;
    }

    if (!empty($updateColumns)) {
        $updateQuery = "UPDATE `{$employeeTable}` SET " . implode(', ', $updateColumns) . " WHERE {$whereClause}";
        try {
            $updateStmt = $db->prepare($updateQuery);
            foreach ($params as $key => $value) {
                $updateStmt->bindValue($key, $value);
            }
            $updateStmt->bindValue(':identifier', $identifierValue);
            if ($updateStmt->execute()) {
                $_SESSION['first_name'] = $new_first_name;
                $_SESSION['last_name'] = $new_last_name;
                if (!empty($new_email)) {
                    $_SESSION['email'] = $new_email;
                }
                if (!empty($new_username)) {
                    $_SESSION['username'] = $new_username;
                }
                $_SESSION['success'] = "Profile information updated successfully.";
                header("Location: employee-profile.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update profile.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "No editable columns are available in the employee table.";
    }
}

/**
 * Upload profile picture
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_profile_picture'])) {
    if (empty($profilePictureContext['identifier'])) {
        $_SESSION['error'] = "Unable to determine the correct employee record.";
        header("Location: employee-profile.php");
        exit();
    }

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_type = $file['type'];

        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
        } elseif ($file_size > 2097152) {
            $_SESSION['error'] = "File size too large. Maximum size is 2MB.";
        } else {
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = $account_upload_prefix . '_' . $profilePictureContext['identifier'] . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (!empty($employee_profile_picture)) {
                $old_file = $upload_dir . basename($employee_profile_picture);
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }

            if (move_uploaded_file($file_tmp, $upload_path)) {
                employeeEnsureProfilePictureColumn($db, $employeeTable);

                $relative_path = 'assets/uploads/profile_pictures/' . $new_filename;
                $field = $profilePictureContext['field'];
                $identifier = $profilePictureContext['identifier'];
                $identifierParam = $profilePictureContext['param_type'] === PDO::PARAM_INT ? (int)$identifier : $identifier;

                $pictureQuery = "UPDATE `{$employeeTable}` SET profile_picture = :profile_picture WHERE `{$field}` = :identifier";
                $pictureStmt = $db->prepare($pictureQuery);
                $pictureStmt->bindParam(':profile_picture', $relative_path);
                $pictureStmt->bindParam(':identifier', $identifierParam, $profilePictureContext['param_type']);

                if ($pictureStmt->execute()) {
                    $_SESSION['profile_picture'] = $relative_path;
                    $_SESSION['success'] = "Profile picture updated successfully.";
                    header("Location: employee-profile.php");
                    exit();
                } else {
                    $_SESSION['error'] = "Failed to update profile picture.";
                }
            } else {
                $_SESSION['error'] = "Failed to upload profile picture.";
            }
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }
}

/**
 * Delete profile picture
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile_picture'])) {
    if (empty($profilePictureContext['identifier'])) {
        $_SESSION['error'] = "Unable to determine the correct employee record.";
        header("Location: employee-profile.php");
        exit();
    }
    
    $remove_result = removeEmployeeProfilePicture(
        $db,
        $employeeTable,
        $profilePictureContext['field'],
        $profilePictureContext['identifier'],
        $profilePictureContext['param_type'],
        $upload_dir,
        $employee_profile_picture ?? null
    );
    
    if ($remove_result['success']) {
        // Reload employee data to reflect changes
        try {
            $employeeQuery = "SELECT * FROM `{$employeeTable}` WHERE {$whereClause} LIMIT 1";
            $employeeStmt = $db->prepare($employeeQuery);
            $employeeStmt->bindParam(':identifier', $identifierValue);
            $employeeStmt->execute();
            $employee = $employeeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                $employee_profile_picture = $employee['profile_picture'] ?? null;
            }
        } catch (PDOException $e) {
            error_log("Failed to reload employee data after picture removal: " . $e->getMessage());
        }
        
        $_SESSION['success'] = $remove_result['message'];
        header("Location: employee-profile.php");
        exit();
    } else {
        $_SESSION['error'] = $remove_result['message'];
    }
}

include '../../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                <?php echo htmlspecialchars($account_heading); ?>
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                <?php echo htmlspecialchars($account_description); ?>
            </p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Account Information</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update your personal and account details</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name</label>
                            <input type="text" id="first_name" name="first_name" required
                                   value="<?php echo htmlspecialchars($first_name); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required
                                   value="<?php echo htmlspecialchars($last_name); ?>"
                                   class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo htmlspecialchars($email); ?>"
                               class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                        <input type="text" id="username" name="username" required
                               value="<?php echo htmlspecialchars($username); ?>"
                               class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number"
                               value="<?php echo htmlspecialchars($contact_number); ?>"
                               class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" title="Save Changes" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-md shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <svg data-lucide="save" class="w-5 h-5"></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Profile Picture</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Upload or change your profile photo</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col items-center space-y-4">
                    <div class="relative">
                        <?php if (!empty($employee_profile_picture) && file_exists(__DIR__ . '/../../' . $employee_profile_picture)): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($employee_profile_picture); ?>"
                                 alt="Profile Picture"
                                 id="profilePicturePreview"
                                 class="w-32 h-32 rounded-lg object-cover border-2 border-gray-200 dark:border-gray-700">
                        <?php else: ?>
                            <div id="profilePicturePreview" class="w-32 h-32 bg-purple-500 rounded-lg flex items-center justify-center text-white text-4xl font-semibold border-2 border-gray-200 dark:border-gray-700">
                                <?php echo htmlspecialchars($user_initials); ?>
                            </div>
                        <?php endif; ?>
                        <div id="imagePreviewContainer" class="hidden mt-2">
                            <img id="imagePreview" src="" alt="Preview" class="w-32 h-32 rounded-lg object-cover border-2 border-primary-500">
                        </div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="w-full">
                        <input type="hidden" name="upload_profile_picture" value="1">
                        <div class="space-y-3">
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Choose Image
                            </label>
                            <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" required
                                   class="block w-full text-sm text-gray-500 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-900/40 dark:file:text-primary-200">
                            <p class="text-xs text-gray-500 dark:text-gray-400">JPEG, PNG or GIF. Max size: 2MB</p>
                            <div class="flex space-x-2">
                                <button type="submit" title="Upload New Picture" class="flex-1 flex items-center justify-center px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 text-sm">
                                    <svg data-lucide="upload" class="w-5 h-5"></svg>
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (!empty($employee_profile_picture)): ?>
                        <form method="POST" class="w-full delete-profile-picture-form" onsubmit="event.preventDefault(); showConfirmAlert('Remove Profile Picture', 'Are you sure you want to remove your profile picture?').then(confirmed => { if(confirmed) this.submit(); }); return false;">
                            <input type="hidden" name="delete_profile_picture" value="1">
                            <button type="submit" title="Remove Picture"
                                    class="flex items-center justify-center w-full px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 text-sm">
                                <svg data-lucide="trash-2" class="w-5 h-5"></svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">Account Summary</h3>
            </div>
            <div class="px-4 py-5 sm:p-6 space-y-3">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars(trim($first_name . ' ' . $last_name)); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Role</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $role_name))); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Department</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($department ?: 'Not specified'); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Contact</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($contact_number ?: 'Not provided'); ?>
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Account Status</p>
                    <p class="text-base font-medium text-gray-900 dark:text-white">
                        <?php
                        $account_status = strtolower($employee_status);
                        $status_is_active = in_array($account_status, ['active', 'enabled', '1', 'true'], true);
                        $status_is_inactive = in_array($account_status, ['inactive', 'disabled', '0', 'false', 'suspended'], true);
                        $status_is_pending = in_array($account_status, ['pending', 'pending_approval'], true);
                        
                        // Determine badge color based on status
                        if ($status_is_active) {
                            $status_class = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                        } elseif ($status_is_inactive) {
                            $status_class = 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
                        } elseif ($status_is_pending) {
                            $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
                        } else {
                            $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
                        }
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $account_status)); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profilePictureInput = document.getElementById('profile_picture');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const profilePicturePreview = document.getElementById('profilePicturePreview');

    if (profilePictureInput && imagePreview && imagePreviewContainer && profilePicturePreview) {
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    showErrorAlert('Invalid File Type', 'Please upload a JPEG, PNG, or GIF image.');
                    e.target.value = '';
                    return;
                }

                if (file.size > 2097152) {
                    showErrorAlert('File Too Large', 'File size too large. Maximum size is 2MB.');
                    e.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(evt) {
                    imagePreview.src = evt.target.result;
                    imagePreviewContainer.classList.remove('hidden');
                    if (profilePicturePreview.tagName === 'IMG') {
                        profilePicturePreview.style.display = 'none';
                    } else {
                        profilePicturePreview.style.display = 'none';
                    }
                };
                reader.readAsDataURL(file);
            } else {
                imagePreviewContainer.classList.add('hidden');
                if (profilePicturePreview.tagName === 'IMG') {
                    profilePicturePreview.style.display = 'block';
                } else {
                    profilePicturePreview.style.display = 'flex';
                }
            }
        });
    }
});
</script>

