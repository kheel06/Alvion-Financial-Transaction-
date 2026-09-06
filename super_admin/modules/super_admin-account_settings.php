<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
checkRole(['super admin']);

$page_title = 'Super Admin Account Settings';

// Handle basic POST (profile and security updates). This is a scaffold; extend as needed.
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_profile' && isset($db)) {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName  = trim($_POST['last_name'] ?? '');

            if ($firstName === '' || $lastName === '') {
                $errors[] = 'First name and last name are required.';
            } else {
                $stmt = $db->prepare("
                    UPDATE users
                    SET first_name = :first_name,
                        last_name  = :last_name
                    WHERE id = :id
                ");
                $stmt->bindValue(':first_name', $firstName);
                $stmt->bindValue(':last_name', $lastName);
                $stmt->bindValue(':id', $_SESSION['user_id'] ?? 0, PDO::PARAM_INT);
                $stmt->execute();

                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name']  = $lastName;

                $success = 'Profile updated successfully.';
            }
        }

        if ($action === 'update_security' && isset($db)) {
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';

            if ($password !== '' || $confirm !== '') {
                if (strlen($password) < 8) {
                    $errors[] = 'Password must be at least 8 characters.';
                } elseif ($password !== $confirm) {
                    $errors[] = 'Password confirmation does not match.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT);

                    $stmt = $db->prepare("
                        UPDATE users
                        SET password_hash = :hash
                        WHERE id = :id
                    ");
                    $stmt->bindValue(':hash', $hash);
                    $stmt->bindValue(':id', $_SESSION['user_id'] ?? 0, PDO::PARAM_INT);
                    $stmt->execute();

                    $success = 'Password updated successfully.';
                }
            }
        }
    } catch (PDOException $e) {
        error_log('Super admin account settings error: ' . $e->getMessage());
        $errors[] = 'An unexpected error occurred. Please try again.';
    }

    if ($success) {
        $_SESSION['success'] = $success;
        header('Location: ' . BASE_URL . '/super_admin/modules/super_admin-account_settings.php');
        exit;
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode(' ', $errors);
        header('Location: ' . BASE_URL . '/super_admin/modules/super_admin-account_settings.php');
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-5xl mx-auto">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Account & Preferences
        </h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage your profile information, security settings, notifications, and system-level preferences.
        </p>
    </div>

    <!-- Profile Management -->
    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <header class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Profile</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Update your display name and contact details used across the platform.
                </p>
            </div>
        </header>
        <div class="px-6 py-5">
            <form method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <input type="hidden" name="action" value="update_profile">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        First Name
                    </label>
                    <input
                        type="text"
                        name="first_name"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>"
                        required
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Last Name
                    </label>
                    <input
                        type="text"
                        name="last_name"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                        value="<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>"
                        required
                    >
                </div>
                <div class="sm:col-span-2 flex justify-end mt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        <svg data-lucide="save" class="w-4 h-4 mr-1.5"></svg>
                        Save Profile
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Security Settings -->
    <section class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <header class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Security</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Strengthen your account with a strong password. MFA and advanced policies are configured under System Configuration.
                </p>
            </div>
        </header>
        <div class="px-6 py-5">
            <form method="POST" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <input type="hidden" name="action" value="update_security">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        New Password
                    </label>
                    <input
                        type="password"
                        name="password"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                        autocomplete="new-password"
                    >
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Confirm Password
                    </label>
                    <input
                        type="password"
                        name="password_confirm"
                        class="block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
                        autocomplete="new-password"
                    >
                </div>
                <div class="sm:col-span-2 flex justify-end mt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-lg border border-transparent bg-primary-600 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                    >
                        <svg data-lucide="shield-check" class="w-4 h-4 mr-1.5"></svg>
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Notification & System Preference placeholders -->
    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <header class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Notification Preferences</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Choose which HR events should surface as email or in-app alerts.
                </p>
            </header>
            <div class="px-6 py-5 text-xs text-gray-500 dark:text-gray-400">
                <p class="mb-2">
                    This section can be wired to a `notification_preferences` table for fine-grained alert routing.
                </p>
                <ul class="list-disc list-inside space-y-1">
                    <li>Approval decisions (leave, OT, claims).</li>
                    <li>Schedule changes affecting your account.</li>
                    <li>Security notifications and login alerts.</li>
                </ul>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <header class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">System Preferences</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Personalize how HR analytics and dashboards are presented to you.
                </p>
            </header>
            <div class="px-6 py-5 text-xs text-gray-500 dark:text-gray-400">
                <p>
                    This can later store preferences such as default reporting range, landing dashboard, and layout density.
                </p>
            </div>
        </div>
    </section>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



