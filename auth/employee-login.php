<?php
require_once '../config/config.php';
require_once '../includes/email_helper.php';

if (!function_exists('maskEmail')) {
    function maskEmail($email) {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        [$localPart, $domainPart] = explode('@', $email, 2);

        $maskSegment = function ($segment) {
            $length = strlen($segment);
            if ($length <= 2) {
                return substr($segment, 0, 1) . str_repeat('*', max(0, $length - 1));
            }
            return substr($segment, 0, 1) . str_repeat('*', $length - 2) . substr($segment, -1);
        };

        $domainParts = explode('.', $domainPart);
        $domainName = array_shift($domainParts);
        $maskedDomainName = $maskSegment($domainName);
        $maskedDomain = $maskedDomainName . (!empty($domainParts) ? '.' . implode('.', $domainParts) : '');

        return $maskSegment($localPart) . '@' . $maskedDomain;
    }
}

function initializeLoginController(PDO $db, array $config): array {
    $defaults = [
        'context' => 'standard',
        'redirect' => 'login.php',
        'identifier_field' => 'username',
        'empty_identifier_message' => 'Please enter your credentials.',
        'invalid_credentials_message' => 'Invalid credentials.',
        'inactive_error_message' => 'Your account is inactive. Please contact an administrator.',
        'role_mapping' => [
            // Primary HR/Operations roles
            'super admin' => 1,
            'admin' => 2,
            'staff' => 3,
            'employee' => 4,
            // Legacy mappings kept for backward compatibility
            'doctor' => 5,
            'nurse' => 6,
            'receptionist' => 7,
            'appointment_coordinator' => 8,
            'billing_staff' => 9,
            'patient' => 10
        ],
        'default_role' => 'employee',
        'default_role_id' => 4,
        'fetch_user' => null,
        'source_table' => 'users',
        'source_identifier_field' => 'id',
    ];

    $config = array_merge($defaults, $config);

    if (!is_callable($config['fetch_user'])) {
        throw new InvalidArgumentException('fetch_user callback is required.');
    }

    if (isset($_SESSION['user_id'])) {
        header("Location: ../index.php");
        exit();
    }

    if (isset($_POST['resend_otp'])) {
        handleResendOtpRequest($config['redirect']);
    }

    if (isset($_POST['verify_code'])) {
        handleOtpVerificationSubmission($db, $config['redirect']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_code']) && !isset($_POST['resend_otp'])) {
        processPrimaryLoginAttempt($db, $config);
    }

    $resendCountdownShouldStart = isset($_SESSION['resend_cooldown_active']);
    $resendCountdownStartTime = $_SESSION['resend_cooldown_start'] ?? null;
    if ($resendCountdownShouldStart) {
        unset($_SESSION['resend_cooldown_active']);
    }

    return [
        'resendCountdownShouldStart' => $resendCountdownShouldStart,
        'resendCountdownStartTime' => $resendCountdownStartTime,
    ];
}

function processPrimaryLoginAttempt(PDO $db, array $config): void {
    // Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    if (function_exists('verifyRecaptcha') && !verifyRecaptcha($recaptcha_response)) {
        $_SESSION['error'] = 'Please complete the reCAPTCHA verification.';
        return;
    }
    
    $identifier = sanitizeInput($_POST[$config['identifier_field']] ?? '');
    $password = isset($_POST['password']) ? trim((string)$_POST['password']) : '';

    if (empty($identifier) || empty($password)) {
        $_SESSION['error'] = $config['empty_identifier_message'];
        return;
    }

    $user = call_user_func($config['fetch_user'], $db, $identifier);
    if (!$user) {
        $_SESSION['error'] = $config['invalid_credentials_message'];
        return;
    }

    $userStatus = $user['status'] ?? null;
    if ($userStatus !== null && $userStatus !== 'active') {
        $_SESSION['error'] = $config['inactive_error_message'];
        return;
    }

    // Check for lockout
    $lockoutUntil = $user['lockout_until'] ?? null;
    if ($lockoutUntil && strtotime($lockoutUntil) > time()) {
        $remaining = strtotime($lockoutUntil) - time();
        $_SESSION['error'] = "Too many failed attempts. Please wait before trying again.";
        $_SESSION['lockout_remaining'] = $remaining;
        return;
    }

    $sourceIdentifierField = $config['source_identifier_field'] ?? 'id';
    $sourceIdentifierValue = $user[$sourceIdentifierField] ?? ($user['id'] ?? null);
    $sourceTable = $config['source_table'] ?? 'users';

    if (!verifyUserPassword($password, $user['password'] ?? null)) {
        // Increment failed attempts
        $attempts = ($user['failed_login_attempts'] ?? 0) + 1;
        
        if ($attempts >= 3) {
            // Lockout for 30 seconds
            $lockoutTime = date('Y-m-d H:i:s', time() + 30);
            $updateStmt = $db->prepare("UPDATE {$sourceTable} SET failed_login_attempts = :attempts, lockout_until = :lockout WHERE {$sourceIdentifierField} = :id");
            $updateStmt->execute(['attempts' => $attempts, 'lockout' => $lockoutTime, 'id' => $sourceIdentifierValue]);
            $_SESSION['error'] = "Too many failed attempts. Please wait before trying again.";
            $_SESSION['lockout_remaining'] = 30;
        } else {
            $updateStmt = $db->prepare("UPDATE {$sourceTable} SET failed_login_attempts = :attempts WHERE {$sourceIdentifierField} = :id");
            $updateStmt->execute(['attempts' => $attempts, 'id' => $sourceIdentifierValue]);
            $_SESSION['error'] = $config['invalid_credentials_message'];
        }
        return;
    }

    // Reset failed attempts on success
    $resetStmt = $db->prepare("UPDATE {$sourceTable} SET failed_login_attempts = 0, lockout_until = NULL WHERE {$sourceIdentifierField} = :id");
    $resetStmt->execute(['id' => $sourceIdentifierValue]);

    $roleMeta = resolveUserRoleMetadata($db, $user, $config);
    $sourceIdentifierField = $config['source_identifier_field'] ?? 'id';
    $sourceIdentifierValue = $user[$sourceIdentifierField] ?? ($user['id'] ?? null);

    $_SESSION['pending_user'] = [
        'id' => $user['id'] ?? $sourceIdentifierValue,
        'username' => $user['username'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'] ?? '',
        'role_id' => $roleMeta['role_id'],
        'role_name' => $roleMeta['role_name'],
        'employee_id' => $user['employee_id'] ?? null,
        'login_context' => $config['context'] ?? 'standard',
        'source_table' => $config['source_table'] ?? 'users',
        'source_identifier_field' => $sourceIdentifierField,
        'source_identifier_value' => $sourceIdentifierValue,
    ];

    triggerOtpForPendingUser($user);
}

function resolveUserRoleMetadata(PDO $db, array $user, array $config): array {
    $role_id = $user['role_id'] ?? null;
    $role_name = $user['role'] ?? $config['default_role'];
    
    if (!empty($user['role_id'])) {
        try {
            $role_query = "SELECT role_name FROM roles WHERE id = :role_id";
            $role_stmt = $db->prepare($role_query);
            $role_stmt->bindParam(':role_id', $user['role_id']);
            $role_stmt->execute();
            $role_data = $role_stmt->fetch();
            if ($role_data) {
                $role_name = $role_data['role_name'];
            }
            $role_id = $user['role_id'];
        } catch (PDOException $e) {
            error_log("Roles lookup failed: " . $e->getMessage());
        }
    }
    
    $normalized_role_name = function_exists('normalizeRoleName')
        ? normalizeRoleName($role_name)
        : strtolower((string)$role_name);
    
    if (!$role_id && !empty($normalized_role_name)) {
        try {
            $lookup_stmt = $db->prepare("SELECT id FROM roles WHERE role_name = :role_name LIMIT 1");
            $lookup_stmt->bindValue(':role_name', $normalized_role_name);
            $lookup_stmt->execute();
            $lookup_role = $lookup_stmt->fetch(PDO::FETCH_ASSOC);
            if ($lookup_role) {
                $role_id = (int)$lookup_role['id'];
            }
        } catch (PDOException $e) {
            error_log("Role lookup by name failed: " . $e->getMessage());
        }
    }
    
    if (!$role_id) {
        $role_id = $config['role_mapping'][$normalized_role_name] ?? $config['default_role_id'];
    }
    
    return [
        'role_id' => $role_id,
        'role_name' => $normalized_role_name ?: $config['default_role'],
    ];
}

if (!function_exists('saveOtpToDatabase')) {
    function saveOtpToDatabase(PDO $db, string $code, string $destination, string $sourceTable, int|string|null $userId = null, int|string|null $departmentAccountId = null, string $purpose = 'login'): ?int {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + (10 * 60)); // 10 minutes from now
            
            $stmt = $db->prepare("
                INSERT INTO otp_codes (code, user_id, department_account_id, source_table, destination, purpose, expires_at, attempts, created_at)
                VALUES (:code, :user_id, :department_account_id, :source_table, :destination, :purpose, :expires_at, 0, NOW())
            ");
            
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            
            // Handle user_id - can be int or null
            if ($userId !== null) {
                $stmt->bindValue(':user_id', is_numeric($userId) ? (int)$userId : $userId, is_numeric($userId) ? PDO::PARAM_INT : PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
            }
            
            // Handle department_account_id - can be int, string, or null
            if ($departmentAccountId !== null) {
                $stmt->bindValue(':department_account_id', is_numeric($departmentAccountId) ? (int)$departmentAccountId : $departmentAccountId, is_numeric($departmentAccountId) ? PDO::PARAM_INT : PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':department_account_id', null, PDO::PARAM_NULL);
            }
            
            $stmt->bindValue(':source_table', $sourceTable, PDO::PARAM_STR);
            $stmt->bindValue(':destination', $destination, PDO::PARAM_STR);
            $stmt->bindValue(':purpose', $purpose, PDO::PARAM_STR);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                return (int)$db->lastInsertId();
            }
        } catch (PDOException $e) {
            error_log("Failed to save OTP to database: " . $e->getMessage());
        }
        return null;
    }
}

if (!function_exists('markOtpAsConsumed')) {
    function markOtpAsConsumed(PDO $db, string $code, string $destination): bool {
        try {
            $stmt = $db->prepare("
                UPDATE otp_codes 
                SET consumed_at = NOW() 
                WHERE code = :code 
                AND destination = :destination 
                AND consumed_at IS NULL 
                AND expires_at > NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            
            $stmt->bindValue(':code', $code, PDO::PARAM_STR);
            $stmt->bindValue(':destination', $destination, PDO::PARAM_STR);
            
            return $stmt->execute() && $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Failed to mark OTP as consumed: " . $e->getMessage());
            return false;
        }
    }
}

function triggerOtpForPendingUser(array $user): void {
    global $db;
    
    $verification_code = generateVerificationCode();
    $code_expiry = time() + (10 * 60);

    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_code_expiry'] = $code_expiry;
    $_SESSION['otp_last_sent'] = time();
    $_SESSION['show_verification_modal'] = true;

    $pending_user = $_SESSION['pending_user'] ?? [];
    $user_email = $pending_user['email'] ?? '';
    $user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? 'User');
    $fallbackMessage = null;
    
    // Determine source table and IDs
    $sourceTable = $pending_user['source_table'] ?? 'department_accounts';
    $userId = null;
    $departmentAccountId = null;
    
    if ($sourceTable === 'users') {
        $userId = $pending_user['id'] ?? null;
    } else {
        $departmentAccountId = $pending_user['source_identifier_value'] ?? $pending_user['employee_id'] ?? null;
    }

    if (!empty($user_email)) {
        // Save OTP to database
        saveOtpToDatabase($db, $verification_code, $user_email, $sourceTable, $userId, $departmentAccountId, 'login');
        
        $email_result = sendVerificationCodeEmail($user_email, $user_name, $verification_code);
        if (!$email_result['success']) {
            error_log("Failed to send verification email: " . $email_result['message']);
            $_SESSION['error'] = "Failed to send verification code. Please contact support.";
            $_SESSION['show_verification_modal'] = true;
            $fallbackMessage = "Email delivery failed. Use this one-time code to continue.";
        } else {
            $_SESSION['verification_email'] = $user_email;
            unset($_SESSION['otp_fallback_code'], $_SESSION['otp_fallback_message']);
        }
    } else {
        $_SESSION['error'] = "No email address found for your account. Please contact support.";
        $_SESSION['show_verification_modal'] = true;
        $fallbackMessage = "No email on file. Use this one-time code to continue.";
    }

    if ($fallbackMessage !== null) {
        $_SESSION['otp_fallback_code'] = $verification_code;
        $_SESSION['otp_fallback_message'] = $fallbackMessage;
    }
}

function handleResendOtpRequest(string $redirectPath): void {
    global $db;
    
    if (!isset($_SESSION['pending_user'])) {
        $_SESSION['error'] = "Session expired. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        header("Location: {$redirectPath}");
        exit();
    }

    unset($_SESSION['error']);

    $verification_code = generateVerificationCode();
    $code_expiry = time() + (10 * 60);
    $current_time = time();

    $_SESSION['verification_code'] = $verification_code;
    $_SESSION['verification_code_expiry'] = $code_expiry;
    $_SESSION['otp_last_sent'] = $current_time;
    $_SESSION['resend_cooldown_start'] = $current_time;
    $_SESSION['resend_cooldown_active'] = true;

    $pending_user = $_SESSION['pending_user'];
    $user_email = $pending_user['email'] ?? '';
    $user_name = trim(($pending_user['first_name'] ?? '') . ' ' . ($pending_user['last_name'] ?? '')) ?: ($pending_user['username'] ?? 'User');
    
    // Determine source table and IDs
    $sourceTable = $pending_user['source_table'] ?? 'department_accounts';
    $userId = null;
    $departmentAccountId = null;
    
    if ($sourceTable === 'users') {
        $userId = $pending_user['id'] ?? null;
    } else {
        $departmentAccountId = $pending_user['source_identifier_value'] ?? $pending_user['employee_id'] ?? null;
    }

    if (!empty($user_email)) {
        // Save OTP to database
        saveOtpToDatabase($db, $verification_code, $user_email, $sourceTable, $userId, $departmentAccountId, 'login');
        
        $email_result = sendVerificationCodeEmail($user_email, $user_name, $verification_code);
        if (!$email_result['success']) {
            error_log("Failed to resend verification email: " . $email_result['message']);
            $_SESSION['error'] = "Failed to resend verification code. Please try again.";
            $_SESSION['show_verification_modal'] = true;
        } else {
            $_SESSION['success_message'] = "A new verification code has been sent to your email.";
            $_SESSION['show_verification_modal'] = true;
            $_SESSION['verification_email'] = $user_email;
        }
    } else {
        $_SESSION['error'] = "No email address found for your account. Please contact support.";
        $_SESSION['show_verification_modal'] = true;
    }

    header("Location: {$redirectPath}");
    exit();
}

function handleOtpVerificationSubmission(PDO $db, string $redirectPath): void {
    $entered_code = '';
    if (isset($_POST['code1'], $_POST['code2'], $_POST['code3'], $_POST['code4'], $_POST['code5'], $_POST['code6'])) {
        $entered_code = sanitizeInput($_POST['code1'] . $_POST['code2'] . $_POST['code3'] . $_POST['code4'] . $_POST['code5'] . $_POST['code6']);
    } elseif (isset($_POST['verification_code'])) {
        $entered_code = sanitizeInput($_POST['verification_code']);
    }

    $entered_code = trim((string)$entered_code);
    $stored_code = isset($_SESSION['verification_code']) ? trim((string)$_SESSION['verification_code']) : null;
    $code_expiry = $_SESSION['verification_code_expiry'] ?? null;

    if (empty($entered_code)) {
        $_SESSION['error'] = "Please enter the verification code.";
        $_SESSION['show_verification_modal'] = true;
    } elseif (!$stored_code) {
        $_SESSION['error'] = "No verification code found. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        clearPendingVerificationState();
    } elseif ($code_expiry === null || time() > $code_expiry) {
        $_SESSION['error'] = "Verification code has expired. Please login again.";
        $_SESSION['show_verification_modal'] = true;
        clearPendingVerificationState();
    } elseif ($entered_code !== $stored_code) {
        $_SESSION['error'] = "Wrong OTP. Please try again.";
        $_SESSION['show_verification_modal'] = true;
    } else {
        // Mark OTP as consumed in database
        $pending_user = $_SESSION['pending_user'] ?? [];
        $user_email = $pending_user['email'] ?? '';
        if (!empty($user_email)) {
            markOtpAsConsumed($db, $entered_code, $user_email);
        }
        
        finalizeUserLogin($db);
        return;
    }

    header("Location: {$redirectPath}");
    exit();
}

function clearPendingVerificationState(): void {
    unset($_SESSION['pending_user'], $_SESSION['verification_code'], $_SESSION['verification_code_expiry']);
}

function finalizeUserLogin(PDO $db): void {
    $pending_user = $_SESSION['pending_user'];
    $loginContext = $pending_user['login_context'] ?? 'standard';
    $_SESSION['login_context'] = $loginContext;

    $_SESSION['user_id'] = $pending_user['id'];
    $_SESSION['username'] = $pending_user['username'];
    $_SESSION['first_name'] = $pending_user['first_name'];
    $_SESSION['last_name'] = $pending_user['last_name'];
    $_SESSION['role_id'] = $pending_user['role_id'];
    $resolvedRoleName = $pending_user['role_name'] ?? 'employee';
    if (function_exists('normalizeRoleName')) {
        $resolvedRoleName = normalizeRoleName($resolvedRoleName);
    } else {
        $resolvedRoleName = strtolower((string)$resolvedRoleName);
    }
    $_SESSION['role_name'] = $resolvedRoleName;
    $_SESSION['user_role'] = $resolvedRoleName;
    if (function_exists('getRoleDisplayName')) {
        $_SESSION['role_display_name'] = getRoleDisplayName($resolvedRoleName);
    }

    if (isset($pending_user['employee_id'])) {
        $_SESSION['employee_id'] = $pending_user['employee_id'];
    }

    $sourceTable = $pending_user['source_table'] ?? 'users';
    $sourceField = $pending_user['source_identifier_field'] ?? 'id';
    $sourceValue = $pending_user['source_identifier_value'] ?? $pending_user['id'];

    updateSourceLastLogin($db, $sourceTable, $sourceField, $sourceValue);

    unset($_SESSION['pending_user'], $_SESSION['verification_code'], $_SESSION['verification_code_expiry'], $_SESSION['otp_last_sent']);

    $userFullName = trim(($pending_user['first_name'] ?? '') . ' ' . ($pending_user['last_name'] ?? '')) ?: ($pending_user['username'] ?? 'there');
    $_SESSION['success'] = "Welcome back! 🎉 " . $userFullName . "! You have successfully logged in.";
    header("Location: ../index.php");
    exit();
}

function verifyUserPassword(string $inputPassword, ?string $storedPassword): bool {
    if ($storedPassword === null || $storedPassword === '') {
        return false;
    }

    $storedPassword = trim((string)$storedPassword);
    $isPasswordHash = preg_match('/^\$(2y|2a|2b|argon2i|argon2id|argon2|P)\$/', $storedPassword) === 1;

    if ($isPasswordHash) {
        return password_verify($inputPassword, $storedPassword);
    }

    return hash_equals($storedPassword, $inputPassword);
}

function tableHasColumn(PDO $db, string $table, string $column): bool {
    static $tableColumnCache = [];
    $sanitizedTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $cacheKey = "{$sanitizedTable}.{$column}";

    if (array_key_exists($cacheKey, $tableColumnCache)) {
        return $tableColumnCache[$cacheKey];
    }

    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$sanitizedTable}` LIKE :column_name");
        $stmt->bindParam(':column_name', $column);
        $stmt->execute();
        $tableColumnCache[$cacheKey] = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Failed to inspect {$sanitizedTable}.{$column}: " . $e->getMessage());
        $tableColumnCache[$cacheKey] = false;
    }

    return $tableColumnCache[$cacheKey];
}

function userTableHasColumn(PDO $db, string $column): bool {
    return tableHasColumn($db, 'users', $column);
}

function updateSourceLastLogin(PDO $db, string $table, string $field, $value): void {
    if ($value === null) {
        return;
    }

    $sanitizedTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $sanitizedField = preg_replace('/[^A-Za-z0-9_]/', '', $field);

    try {
        $query = "UPDATE `{$sanitizedTable}` SET last_login = NOW() WHERE `{$sanitizedField}` = :identifier";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':identifier', $value);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Failed to update {$sanitizedTable} last_login: " . $e->getMessage());
    }
}

$departmentTable = 'department_accounts';
$departmentTableSafe = preg_replace('/[^A-Za-z0-9_]/', '', $departmentTable);
$departmentIdentifierColumns = [];

if (tableHasColumn($db, $departmentTable, 'employee_id')) {
    $departmentIdentifierColumns[] = 'employee_id';
}
if (tableHasColumn($db, $departmentTable, 'employee_email')) {
    $departmentIdentifierColumns[] = 'employee_email';
}
if (tableHasColumn($db, $departmentTable, 'email')) {
    $departmentIdentifierColumns[] = 'email';
}
if (tableHasColumn($db, $departmentTable, 'username')) {
    $departmentIdentifierColumns[] = 'username';
}

if (empty($departmentIdentifierColumns)) {
    $departmentIdentifierColumns[] = 'id';
}

$departmentRoleMapping = [
    'admin' => 1,
    'doctor' => 2,
    'nurse' => 3,
    'staff' => 3,
    'employee' => 3,
    'receptionist' => 4,
    'appointment_coordinator' => 5,
    'billing_staff' => 6,
    'finance_staff' => 6,
    'finance staff' => 6,
    'patient' => 7,
];

$loginControllerState = initializeLoginController($db, [
    'context' => 'employee',
    'redirect' => 'employee-login.php',
    'identifier_field' => 'employee_id',
    'empty_identifier_message' => "Please enter both employee ID and password.",
    'invalid_credentials_message' => "Invalid employee ID or password.",
    'role_mapping' => $departmentRoleMapping,
    'default_role' => 'employee',
    'default_role_id' => 3,
    'source_table' => $departmentTableSafe,
    'source_identifier_field' => 'employee_id',
    'fetch_user' => function(PDO $db, string $identifier) use ($departmentTableSafe, $departmentIdentifierColumns) {
        $conditions = array_map(fn($column) => "{$column} = :identifier", $departmentIdentifierColumns);
        $whereClause = implode(' OR ', $conditions);
        $query = "SELECT * FROM `{$departmentTableSafe}` WHERE {$whereClause} LIMIT 1";

        try {
            $stmt = $db->prepare($query);
            $stmt->bindParam(':identifier', $identifier);
            $stmt->execute();
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $record['id'] = $record['id'] ?? $record['employee_id'] ?? ($record['staff_id'] ?? null);
                $record['username'] = $record['username']
                    ?? $record['employee_email']
                    ?? $record['email']
                    ?? $record['employee_id']
                    ?? ($record['id'] ?? null);

                $record['email'] = $record['employee_email']
                    ?? $record['email']
                    ?? ($record['work_email'] ?? $record['company_email'] ?? null);

                $record['first_name'] = $record['first_name']
                    ?? $record['employee_fname']
                    ?? $record['fname']
                    ?? '';

                $record['last_name'] = $record['last_name']
                    ?? $record['employee_lname']
                    ?? $record['lname']
                    ?? '';

                if ((empty($record['first_name']) || empty($record['last_name'])) && !empty($record['full_name'])) {
                    $nameParts = preg_split('/\s+/', trim($record['full_name']), 2);
                    $record['first_name'] = $record['first_name'] ?: ($nameParts[0] ?? '');
                    if (count($nameParts) === 2) {
                        $record['last_name'] = $record['last_name'] ?: $nameParts[1];
                    }
                }

                $record['role'] = $record['role']
                    ?? $record['role_name']
                    ?? $record['account_type']
                    ?? 'employee';

                $record['role_name'] = $record['role_name'] ?? $record['role'];

                if (!isset($record['status'])) {
                    if (isset($record['is_active'])) {
                        $record['status'] = ((int)$record['is_active'] === 1) ? 'active' : 'inactive';
                    } elseif (isset($record['account_status'])) {
                        $record['status'] = $record['account_status'];
                    }
                }

                $record['employee_id'] = $record['employee_id'] ?? ($record['staff_id'] ?? $record['id'] ?? null);

                if (isset($record['password'])) {
                    $record['password'] = trim($record['password']);
                }
            }

            return $record;
        } catch (PDOException $e) {
            error_log("Department account lookup failed: " . $e->getMessage());
            return null;
        }
    },
]);

$resendCountdownShouldStart = $loginControllerState['resendCountdownShouldStart'];
$resendCountdownStartTime = $loginControllerState['resendCountdownStartTime'];
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>Employee Login - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>/assets/js/toast.js"></script>
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED && defined('RECAPTCHA_SITE_KEY') && !empty(RECAPTCHA_SITE_KEY)): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <style>
        :root {
            --ink: #11142b;
            --muted: #7b819d;
            --line: #e8e7f1;
            --purple: #6938ef;
            --purple-2: #8c39dd;
            --pink: #d31d78;
            --panel: #f7f4ff;
        }

        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 12% 20%, rgba(135, 94, 255, .16), transparent 28%),
                radial-gradient(circle at 87% 78%, rgba(219, 66, 151, .12), transparent 30%),
                linear-gradient(135deg, #f5f0ff 0%, #faf7ff 52%, #fff8fb 100%);
            overflow-x: hidden;
        }

        .page-shell {
            min-height: 100vh;
            min-height: 100svh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: min(100%, 980px);
            min-height: 580px;
            display: grid;
            grid-template-columns: minmax(330px, 42%) minmax(0, 58%);
            background: rgba(255,255,255,.97);
            border: 1px solid rgba(255,255,255,.8);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(38, 22, 70, .15);
        }

        .brand-panel {
            position: relative;
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(circle at 74% 14%, rgba(173, 147, 255, .18), transparent 28%),
                radial-gradient(circle at 28% 86%, rgba(218, 30, 130, .14), transparent 32%),
                linear-gradient(160deg, #191739 0%, #2b1e62 45%, #5535d9 100%);
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border: 1px solid rgba(255,255,255,.11);
            border-radius: 999px;
            pointer-events: none;
        }
        .brand-panel::before { width: 360px; height: 360px; right: -190px; bottom: -195px; }
        .brand-panel::after { width: 260px; height: 260px; right: -140px; bottom: -135px; }

        .brand-inner {
            position: relative;
            z-index: 1;
            min-height: 580px;
            height: 100%;
            padding: 26px 28px 22px;
            display: flex;
            flex-direction: column;
        }

        .brand-top { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .brand-logo {
            width: 42px; height: 42px; border-radius: 13px;
            object-fit: contain; background: #fff; padding: 7px;
            box-shadow: 0 10px 26px rgba(0,0,0,.14);
        }
        .access-pill {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 8px 11px; border-radius: 999px;
            font-size: 9px; font-weight: 700; letter-spacing: .04em;
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.14);
            backdrop-filter: blur(8px);
        }
        .access-dot { width: 6px; height: 6px; border-radius: 999px; background: #ffbc67; box-shadow: 0 0 0 4px rgba(255,188,103,.08); }

        .brand-copy { margin-top: 52px; max-width: 340px; }
        .eyebrow {
            font-size: 9px; font-weight: 800; letter-spacing: .24em; text-transform: uppercase;
            color: #ffb26e; margin-bottom: 12px;
        }
        .brand-title {
            margin: 0;
            font-size: clamp(2rem, 3.1vw, 3.45rem);
            line-height: .95;
            letter-spacing: -.055em;
            font-weight: 800;
        }
        .brand-subtitle {
            margin: 18px 0 0;
            font-size: 12px; line-height: 1.65;
            color: rgba(255,255,255,.77);
            max-width: 315px;
        }

        .security-note {
            margin-top: auto;
            width: min(100%, 300px);
            padding: 11px 13px;
            display: flex; gap: 10px; align-items: flex-start;
            border-radius: 14px;
            background: rgba(255,255,255,.075);
            border: 1px solid rgba(255,255,255,.11);
            backdrop-filter: blur(10px);
        }
        .security-icon {
            width: 26px; height: 26px; flex: 0 0 26px;
            display: grid; place-items: center;
            border-radius: 8px; background: rgba(255,255,255,.08); color: #ffbe71;
        }
        .security-note strong { display:block; font-size: 10px; line-height: 1.25; }
        .security-note span { display:block; margin-top: 2px; font-size: 8.5px; line-height: 1.5; color: rgba(255,255,255,.62); }
        .brand-footer { margin-top: 12px; font-size: 8px; color: rgba(255,255,255,.45); }

        .form-panel {
            position: relative;
            background: rgba(255,255,255,.98);
            padding: 44px 46px 40px;
            display: flex;
            align-items: center;
        }

        .form-content { width: 100%; max-width: 470px; margin: 0 auto; }
        .signin-kicker { color: #7042ef; font-size: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: .28em; }
        .form-title { margin: 7px 0 0; font-size: 22px; line-height: 1.1; font-weight: 800; letter-spacing: -.035em; }
        .form-description { margin: 6px 0 0; font-size: 9.5px; color: var(--muted); line-height: 1.5; }

        .login-form { margin-top: 23px; }
        .field { margin-top: 13px; }
        .field:first-child { margin-top: 0; }
        .field-row { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:6px; }
        .field-label { font-size: 9px; font-weight: 800; color:#464b68; }
        .field-side { font-size: 8px; color:#9ba0b8; }

        .input-wrap { position:relative; }
        .input-icon {
            position:absolute; left:11px; top:50%; transform:translateY(-50%);
            width:15px; height:15px; color:#7b5cff; pointer-events:none;
        }
        .login-input {
            width:100%; height:39px; border-radius:10px;
            border:1px solid var(--line); background:#fff;
            padding:0 35px 0 32px; color:#2b2f4b; font-size:10px;
            outline:none; transition:.2s ease;
            box-shadow: 0 2px 0 rgba(17,20,43,.01);
        }
        .login-input::placeholder { color:#b2b6ca; }
        .login-input:focus { border-color:#a28aff; box-shadow:0 0 0 3px rgba(105,56,239,.08); }
        .toggle-password {
            position:absolute; right:8px; top:50%; transform:translateY(-50%);
            width:25px; height:25px; display:grid; place-items:center;
            border:0; background:transparent; color:#9297ae; cursor:pointer; border-radius:7px;
        }
        .toggle-password:hover { background:#f7f5ff; color:#6840e9; }

        .captcha-shell {
            margin-top: 14px;
            width: 100%;
            min-height: 58px;
            padding: 6px;
            display:flex; justify-content:center; align-items:center;
            border:1px solid #eeeaf8; border-radius:11px;
            background:#faf9fd;
            overflow:hidden;
        }
        .captcha-frame {
            width:304px; max-width:100%; height:78px;
            overflow:hidden; display:flex; justify-content:center; align-items:center;
        }
        .captcha-frame iframe { max-width:100%; }
        .g-recaptcha { transform-origin:center center; }

        .submit-btn {
            margin-top: 13px;
            width:100%; height:39px; border:0; border-radius:11px;
            display:flex; align-items:center; justify-content:center; gap:8px;
            color:#fff; font-size:10px; font-weight:800; cursor:pointer;
            background:linear-gradient(100deg, var(--purple) 0%, var(--purple-2) 52%, var(--pink) 100%);
            box-shadow:0 13px 25px rgba(105,56,239,.2);
            transition:transform .16s ease, box-shadow .16s ease, opacity .16s ease;
        }
        .submit-btn:hover { transform:translateY(-1px); box-shadow:0 16px 28px rgba(105,56,239,.24); }
        .submit-btn:active { transform:translateY(0); }
        .submit-btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }

        .trust-row {
            margin-top:12px;
            padding:10px 12px;
            display:flex; gap:9px; align-items:flex-start;
            background:#fafaff; border:1px solid #eceaff; border-radius:11px;
        }
        .trust-row svg { width:14px; height:14px; color:#7258f5; flex:0 0 auto; }
        .trust-row span { font-size:8.5px; line-height:1.45; color:#8589a1; }

        /* OTP */
        .otp-overlay {
            position:fixed; inset:0; z-index:1000; display:flex; align-items:center; justify-content:center;
            padding:20px; background:rgba(17, 14, 35, .58); backdrop-filter:blur(7px);
        }
        .otp-overlay.hidden { display:none; }
        .otp-card {
            position:relative; width:min(100%, 430px); max-height:min(92vh, 700px); overflow:auto;
            background:#fff; border-radius:24px; padding:24px;
            box-shadow:0 28px 80px rgba(26, 17, 57, .28);
            border:1px solid rgba(255,255,255,.75);
        }
        .otp-card::-webkit-scrollbar { width:6px; }
        .otp-card::-webkit-scrollbar-thumb { background:#dcd7f4; border-radius:999px; }
        .otp-close {
            position:absolute; top:14px; right:14px; width:30px; height:30px;
            border:1px solid #eceaf6; border-radius:9px; background:#fff; color:#8c90a6;
            display:grid; place-items:center; cursor:pointer;
        }
        .otp-close:hover { background:#f8f6ff; color:#5d45d4; }
        .otp-head { text-align:center; padding:3px 24px 0; }
        .otp-icon {
            width:50px; height:50px; margin:0 auto 12px; display:grid; place-items:center;
            border-radius:15px; color:#6842e9; background:linear-gradient(135deg,#f0edff,#fbebf6); 
        }
        .otp-kicker { font-size:8px; font-weight:800; letter-spacing:.22em; color:#7a4aef; text-transform:uppercase; }
        .otp-title { margin:6px 0 0; font-size:20px; font-weight:800; letter-spacing:-.03em; }
        .otp-desc { margin:7px auto 0; max-width:330px; font-size:9.5px; line-height:1.5; color:#868aa2; }
        .masked-email { color:#5f43d8; font-weight:800; }

        .alert { margin-top:13px; border-radius:11px; padding:10px 12px; font-size:9px; line-height:1.45; }
        .alert-error { background:#fff5f6; border:1px solid #ffdce0; color:#c74b5a; }
        .alert-success { background:#f3fbf6; border:1px solid #d9f3e0; color:#438355; }
        .alert-fallback { background:#fff8ed; border:1px solid #ffe7c3; color:#a56b19; }
        .otp-fallback-code { margin-top:5px; font-size:20px; font-weight:900; letter-spacing:.28em; color:#8d5710; }

        .otp-form { margin-top:18px; }
        .otp-inputs { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:7px; }
        .code-input {
            width:100%; height:50px; border:1px solid #e2dfef; border-radius:12px; background:#fbfaff;
            text-align:center; font-size:20px; font-weight:800; color:#282541; outline:none;
            transition:.2s ease;
        }
        .code-input:focus { background:#fff; border-color:#7b59ed; box-shadow:0 0 0 3px rgba(105,56,239,.09); }
        .code-input.filled { border-color:#b9a8fa; background:#f8f6ff; }

        .otp-verify { margin-top:14px; }
        .otp-meta { margin-top:12px; text-align:center; font-size:8.5px; color:#989caf; line-height:1.5; }
        .resend-form { display:inline; }
        .resend-btn { border:0; background:transparent; color:#6842e9; font-size:8.5px; font-weight:800; cursor:pointer; padding:0; }
        .resend-btn:disabled { color:#aeb1c2; cursor:not-allowed; }
        .otp-security { margin-top:14px; padding-top:13px; border-top:1px solid #efedf6; display:flex; align-items:center; justify-content:center; gap:7px; color:#9b9eb2; font-size:8px; }
        .otp-security svg { width:13px; height:13px; color:#7b61ec; }

        @media (max-width: 900px) {
            .login-card { width:min(100%, 780px); grid-template-columns:1fr 1.15fr; min-height:540px; }
            .brand-inner { min-height:540px; padding:22px 24px 18px; }
            .brand-copy { margin-top:38px; }
            .form-panel { padding:32px 28px; }
        }

        @media (max-width: 720px) {
            body { overflow:auto; }
            .page-shell { padding:16px; align-items:flex-start; }
            .login-card { grid-template-columns:1fr; max-width:480px; min-height:0; border-radius:22px; }
            .brand-panel { display:none; }
            .form-panel { padding:30px 22px 24px; }
            .form-content { max-width:none; }
        }

        @media (max-width: 360px) {
            .form-panel { padding:24px 16px 20px; }
            .otp-overlay { padding:10px; }
            .otp-card { padding:20px 15px; border-radius:19px; }
            .otp-inputs { gap:5px; }
            .code-input { height:45px; border-radius:10px; font-size:18px; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordEyeIcon = document.getElementById('passwordEyeIcon');

            if (togglePasswordBtn && passwordField && passwordEyeIcon) {
                togglePasswordBtn.addEventListener('click', function() {
                    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordField.setAttribute('type', type);
                    passwordEyeIcon.innerHTML = type === 'password'
                        ? '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>'
                        : '<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path><line x1="2" y1="2" x2="22" y2="22"></line>';
                });
            }

            let codeInputsSetup = false;
            function setupCodeInputs() {
                if (codeInputsSetup) return;
                codeInputsSetup = true;
                const codeInputs = document.querySelectorAll('.code-input');

                <?php if (isset($_SESSION['error']) && isset($_SESSION['show_verification_modal'])): ?>
                codeInputs.forEach(input => { input.value = ''; input.classList.remove('filled'); });
                <?php endif; ?>

                codeInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/\D/g, '');
                        if (value.length > 1) {
                            const chars = value.slice(0, 6).split('');
                            codeInputs.forEach((item, i) => item.value = chars[i] || '');
                            codeInputs.forEach(item => item.classList.toggle('filled', !!item.value));
                            const target = codeInputs[Math.min(chars.length, 6) - 1];
                            if (target) target.focus();
                        } else {
                            e.target.value = value.slice(0, 1);
                            e.target.classList.toggle('filled', !!e.target.value);
                            if (e.target.value && index < codeInputs.length - 1) codeInputs[index + 1].focus();
                        }
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !input.value && index > 0) {
                            codeInputs[index - 1].focus();
                            codeInputs[index - 1].select();
                        }
                        if (e.key === 'ArrowLeft' && index > 0) codeInputs[index - 1].focus();
                        if (e.key === 'ArrowRight' && index < codeInputs.length - 1) codeInputs[index + 1].focus();
                        if (e.key === 'Enter') {
                            const fullCode = Array.from(codeInputs).map(inp => inp.value).join('');
                            if (fullCode.length === 6) {
                                document.getElementById('fullCode').value = fullCode;
                                document.getElementById('verificationForm').requestSubmit();
                            }
                        }
                    });

                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                        pasted.split('').forEach((digit, i) => { if (codeInputs[i]) codeInputs[i].value = digit; });
                        codeInputs.forEach(item => item.classList.toggle('filled', !!item.value));
                        const nextIndex = Math.min(pasted.length, 5);
                        codeInputs[nextIndex].focus();
                    });
                });

                if (codeInputs.length) codeInputs[0].focus();
            }

            <?php if (isset($_SESSION['show_verification_modal']) && $_SESSION['show_verification_modal']): ?>
            const modal = document.getElementById('verificationModal');
            if (modal) {
                modal.classList.remove('hidden');
                setTimeout(setupCodeInputs, 80);
            }
            <?php
                $error_message = $_SESSION['error'] ?? '';
                $is_otp_error = stripos($error_message, 'OTP') !== false || stripos($error_message, 'verification') !== false || stripos($error_message, 'code') !== false;
                if (!isset($_SESSION['error']) || !$is_otp_error) unset($_SESSION['show_verification_modal']);
            endif; ?>

            setupCodeInputs();

            <?php if (isset($_SESSION['error']) && !isset($_SESSION['show_verification_modal'])): ?>
            if (typeof showToast === 'function') showToast('error', <?php echo json_encode($_SESSION['error']); ?>);
            <?php unset($_SESSION['error']); endif; ?>
        });
    </script>
</head>
<body>
    <div id="toast-container" class="fixed top-4 right-4 z-[1100] flex flex-col gap-3 pointer-events-none"></div>

    <main class="page-shell">
        <section class="login-card" aria-label="Employee login">
            <aside class="brand-panel">
                <div class="brand-inner">
                    <div class="brand-top">
                        <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="<?php echo SITE_NAME; ?> logo" class="brand-logo">
                        <div class="access-pill"><span class="access-dot"></span> Access Pass</div>
                    </div>

                    <div class="brand-copy">
                        <div class="eyebrow">Employee entry</div>
                        <h1 class="brand-title">Your<br>secure<br>work pass.</h1>
                        <p class="brand-subtitle">Use your employee credentials to request a protected session and continue to your workspace.</p>
                    </div>

                    <div class="security-note">
                        <div class="security-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="14" height="14"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                        </div>
                        <div>
                            <strong>Protected session</strong>
                            <span>Failed attempts are temporarily paused for account safety.</span>
                        </div>
                    </div>
                    <div class="brand-footer">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</div>
                </div>
            </aside>

            <section class="form-panel">
                <div class="form-content">
                    <div class="signin-kicker">Sign in</div>
                    <h2 class="form-title">Employee Login</h2>
                    <p class="form-description">Enter your employee ID and password to continue.</p>

                    <form id="employeeLoginForm" class="login-form" method="POST">
                        <div class="field">
                            <div class="field-row">
                                <label class="field-label" for="employee_id">Employee ID</label>
                            </div>
                            <div class="input-wrap">
                                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M7 9h4M7 13h6"></path></svg>
                                <input id="employee_id" name="employee_id" type="text" required autocomplete="off" class="login-input" placeholder="Enter employee ID" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>">
                            </div>
                        </div>

                        <div class="field">
                            <div class="field-row">
                                <label class="field-label" for="password">Password</label>
                                <span class="field-side">Private</span>
                            </div>
                            <div class="input-wrap">
                                <svg class="input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="10" rx="2"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                                <input id="password" name="password" type="password" required autocomplete="current-password" class="login-input" placeholder="Enter password">
                                <button type="button" id="togglePassword" class="toggle-password" aria-label="Show password">
                                    <svg id="passwordEyeIcon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                </button>
                            </div>
                        </div>

                        <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED && defined('RECAPTCHA_SITE_KEY') && !empty(RECAPTCHA_SITE_KEY)): ?>
                        <div class="captcha-shell" aria-label="Security verification">
                            <div class="captcha-frame">
                                <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>" data-theme="light"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <button type="submit" id="loginButton" class="submit-btn">
                            <span>Continue</span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M5 12h13"></path><path d="m13 6 6 6-6 6"></path></svg>
                        </button>

                        <div class="trust-row">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Your session uses password verification, email OTP, and temporary lockout protection.</span>
                        </div>
                    </form>
                </div>
            </section>
        </section>
    </main>

    <!-- OTP Modal -->
    <div id="verificationModal" class="hidden otp-overlay" role="dialog" aria-modal="true" aria-labelledby="otpTitle">
        <div class="otp-card" id="otpCard">
            <button type="button" id="closeOtpModal" class="otp-close" aria-label="Close verification dialog">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="15" height="15"><path d="m6 6 12 12M18 6 6 18"></path></svg>
            </button>

            <div class="otp-head">
                <div class="otp-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="22" height="22"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="m4 7 8 6 8-6"></path></svg>
                </div>
                <div class="otp-kicker">Step 02 · Verify</div>
                <h3 id="otpTitle" class="otp-title">Confirm your code</h3>
                <p class="otp-desc">We've sent a 6-digit verification code to <span class="masked-email"><?php echo (isset($_SESSION['verification_email']) && !empty($_SESSION['verification_email'])) ? htmlspecialchars(maskEmail($_SESSION['verification_email'])) : 'your email'; ?></span>.</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error auto-dismiss" data-auto-dismiss="true">
                <?php $error_msg = $_SESSION['error']; echo htmlspecialchars($error_msg); unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success auto-dismiss" data-auto-dismiss="true">
                <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['otp_fallback_code'])): ?>
            <div class="alert alert-fallback">
                <strong>Manual verification code</strong>
                <div><?php echo htmlspecialchars($_SESSION['otp_fallback_message'] ?? 'Use this one-time code to continue.'); ?></div>
                <div class="otp-fallback-code"><?php echo htmlspecialchars($_SESSION['otp_fallback_code']); ?></div>
            </div>
            <?php unset($_SESSION['otp_fallback_code'], $_SESSION['otp_fallback_message']); endif; ?>

            <form method="POST" id="verificationForm" class="otp-form">
                <div class="otp-inputs">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                    <input type="text" name="code<?php echo $i; ?>" class="code-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" aria-label="Verification digit <?php echo $i; ?>" required>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="verification_code" id="fullCode">
                <input type="hidden" name="verify_code" value="1">
                <button type="submit" id="verifyButton" class="submit-btn otp-verify">Verify &amp; continue</button>
            </form>

            <div class="otp-meta">
                <span>Code expires in 10 minutes.</span>
                <span> Didn't receive it? </span>
                <form method="POST" id="resendOtpForm" class="resend-form">
                    <input type="hidden" name="resend_otp" value="1">
                    <button type="submit" id="resendOtpBtn" class="resend-btn">Resend code</button>
                </form>
            </div>

            <div class="otp-security">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.4 2.9 8.4 7 10 4.1-1.6 7-5.6 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>
                Never share your verification code with anyone.
            </div>
        </div>
    </div>

    <script>
        function setButtonLoading(button, text) {
            if (!button || button.dataset.loading === 'true') return;
            button.dataset.loading = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = `
                <span style="display:inline-flex;align-items:center;justify-content:center;gap:8px;">
                    <svg class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".28"></circle>
                        <path d="M21 12a9 9 0 0 1-9 9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <span>${text}</span>
                </span>`;
        }

        const verificationForm = document.getElementById('verificationForm');
        const verifyButton = document.getElementById('verifyButton');
        if (verificationForm) {
            verificationForm.addEventListener('submit', function(e) {
                const codeInputs = document.querySelectorAll('.code-input');
                const fullCode = Array.from(codeInputs).map(input => input.value || '').join('');
                if (fullCode.length !== 6) {
                    e.preventDefault();
                    if (typeof showErrorAlert === 'function') showErrorAlert('Verification Code', 'Please enter all 6 digits of the verification code.');
                    return false;
                }
                document.getElementById('fullCode').value = fullCode;
                setButtonLoading(verifyButton, 'Verifying...');
            });
        }

        const loginForm = document.getElementById('employeeLoginForm');
        const loginButton = document.getElementById('loginButton');
        if (loginForm && loginButton) {
            loginForm.addEventListener('submit', function(e) {
                <?php if (defined('RECAPTCHA_ENABLED') && RECAPTCHA_ENABLED && defined('RECAPTCHA_SITE_KEY') && !empty(RECAPTCHA_SITE_KEY)): ?>
                if (typeof grecaptcha !== 'undefined' && !grecaptcha.getResponse()) {
                    e.preventDefault();
                    if (typeof showToast === 'function') showToast('error', 'Please complete the reCAPTCHA verification.');
                    return false;
                }
                <?php endif; ?>
                setButtonLoading(loginButton, 'Authenticating...');
            });
        }

        (function() {
            const resendBtn = document.getElementById('resendOtpBtn');
            const resendForm = document.getElementById('resendOtpForm');
            const cooldown = 60;
            let remaining = 0, countdownInterval = null;
            const originalText = resendBtn ? resendBtn.textContent.trim() : 'Resend code';

            function setButtonText(text) { if (resendBtn) resendBtn.textContent = text; }
            function resetButton() { if (!resendBtn) return; resendBtn.disabled = false; setButtonText(originalText); }
            function startCountdown(seconds) {
                if (!resendBtn) return;
                remaining = seconds; resendBtn.disabled = true; setButtonText(`Wait ${remaining}s`);
                if (countdownInterval) clearInterval(countdownInterval);
                countdownInterval = setInterval(() => {
                    remaining--;
                    if (remaining > 0) setButtonText(`Wait ${remaining}s`);
                    else { clearInterval(countdownInterval); countdownInterval = null; resetButton(); }
                }, 1000);
            }

            const resumeCountdown = <?php echo $resendCountdownShouldStart && $resendCountdownStartTime ? 'true' : 'false'; ?>;
            const serverStartTime = <?php echo $resendCountdownStartTime ? (int)$resendCountdownStartTime : 'null'; ?>;
            const serverNow = <?php echo time(); ?>;
            if (resumeCountdown && serverStartTime) {
                const remainingTime = Math.max(0, cooldown - (serverNow - serverStartTime));
                remainingTime > 0 ? startCountdown(remainingTime) : resetButton();
            }
            resendForm?.addEventListener('submit', function() { startCountdown(cooldown); });
        })();

        (function() {
            const loginBtn = document.getElementById('loginButton');
            const lockoutRemaining = <?php echo isset($_SESSION['lockout_remaining']) ? (int)$_SESSION['lockout_remaining'] : 0; ?>;
            <?php unset($_SESSION['lockout_remaining']); ?>
            if (loginBtn && lockoutRemaining > 0) {
                let remaining = lockoutRemaining;
                const originalText = loginBtn.innerHTML;
                loginBtn.disabled = true;
                loginBtn.innerHTML = `<span>Try again in ${remaining}s</span>`;
                const interval = setInterval(() => {
                    remaining--;
                    if (remaining > 0) loginBtn.innerHTML = `<span>Try again in ${remaining}s</span>`;
                    else { clearInterval(interval); loginBtn.disabled = false; loginBtn.innerHTML = originalText; }
                }, 1000);
            }
        })();

        (function() {
            const modal = document.getElementById('verificationModal');
            const closeBtn = document.getElementById('closeOtpModal');
            const card = document.getElementById('otpCard');
            function closeModal() { modal?.classList.add('hidden'); }
            closeBtn?.addEventListener('click', closeModal);
            modal?.addEventListener('mousedown', function(e) { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
            });
            if (card) card.addEventListener('mousedown', e => e.stopPropagation());
        })();

        document.querySelectorAll('[data-auto-dismiss="true"]').forEach(function(el) {
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(-3px)';
                setTimeout(() => el.remove(), 250);
            }, 5000);
        });
    </script>
</body>
</html>
