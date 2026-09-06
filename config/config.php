<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'Alvion');
define('SITE_SHORT_NAME', 'ALVION');
define('SITE_VERSION', '1.0.0');
define('BASE_URL', '');

// Session Timeout Enforcement (15 minutes of inactivity)
// Skip timeout check for logout page to avoid redirect loops
define('SESSION_TIMEOUT', 900); // 15 minutes in seconds
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['user_id']) && $current_page !== 'logout.php') {
    $timeout_duration = SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        header("Location: " . BASE_URL . "/auth/logout.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// reCAPTCHA Configuration
define('RECAPTCHA_ENABLED', true);
define('RECAPTCHA_SITE_KEY', ''); // Google Test Site Key
define('RECAPTCHA_SECRET_KEY', ''); // Google Test Secret Key

// Financial System Configuration
define('CURRENT_FISCAL_YEAR', date('Y'));
define('COMPANY_TIN', '123-456-789-000');
define('COMPANY_ACCREDITATION_NO', 'HOSP-2024-001');
define('DEFAULT_CURRENCY', 'PHP');
define('VAT_RATE', 0.12);
define('EWT_RATE', 0.01);
define('MAX_DAILY_CASH_DISBURSEMENT', 50000.00);

// Chart of Accounts Configuration
define('DEFAULT_ASSET_ACCOUNTS', [101, 102, 103, 104, 105]);
define('DEFAULT_LIABILITY_ACCOUNTS', [201, 202, 203, 204]);
define('DEFAULT_EQUITY_ACCOUNTS', [301, 302, 303]);
define('DEFAULT_REVENUE_ACCOUNTS', [401, 402, 403, 404, 405, 406]);
define('DEFAULT_EXPENSE_ACCOUNTS', [501, 502, 503, 504, 505, 506, 507, 508]);

// Approval Workflow
define('APPROVAL_LEVELS', [
    'disbursement' => ['dept_head', 'finance_manager', 'hospital_director'],
    'budget' => ['finance_manager', 'hospital_director'],
    'journal_entry' => ['finance_manager'],
    'ap_invoice' => ['dept_head', 'finance_officer']
]);

// Financial Periods
define('CURRENT_PERIOD', date('Y-m'));
define('LOCK_PREVIOUS_PERIOD', true);

// Theme Colors
define('PRIMARY_COLOR', 'teal');
define('SECONDARY_COLOR', 'blue');
define('ACCENT_COLOR', 'emerald');
define('NEUTRAL_COLOR', 'slate');

// Database Configuration
require_once 'database.php';
$database = new Database();
$db = $database->getConnection();

// Include helper functions
require_once __DIR__ . '/../includes/functions.php';

// Include financial functions
require_once __DIR__ . '/../includes/financial_functions.php';

// Include permissions system
require_once __DIR__ . '/permissions.php';

// Ensure the core roles exist in the database
if (function_exists('ensureCoreRoles')) {
    ensureCoreRoles($db);
}

// Include role-based helpers
require_once __DIR__ . '/../includes/role_helpers.php';

// Financial module permissions
function requireFinancialPermission($permission) {
    if (!hasPermission($permission)) {
        $_SESSION['error'] = "You don't have permission to access this financial module.";
        header("Location: " . BASE_URL . "/admin/admin-dashboard.php");
        exit();
    }
}

// Check if user is logged in for protected pages
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Check user role - supports both role_id (array of integers) and role_name (array of strings)
function checkRole($allowed_roles) {
    $user_role_id = $_SESSION['role_id'] ?? null;
    $user_role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? null;
    $normalized_role_name = $user_role_name ? normalizeRoleName($user_role_name) : null;
    
    // Super Admin bypasses role gate checks
    if ($normalized_role_name === 'super admin') {
        return;
    }
    
    // Check if allowed_roles contains integers (role_ids) or strings (role_names)
    $first_allowed = $allowed_roles[0] ?? null;
    $is_role_id_check = !empty($allowed_roles) && is_numeric($first_allowed);
    
    if ($is_role_id_check) {
        // Check using role_id
        if (!$user_role_id || !in_array($user_role_id, $allowed_roles)) {
            $_SESSION['error'] = "You don't have permission to access this page.";
            header("Location: ../index.php");
            exit();
        }
        return;
    }
    
    // Check using role_name (backward compatibility)
    $normalized_allowed = [];
    foreach ($allowed_roles as $role) {
        if (is_string($role)) {
            $normalized_allowed[] = normalizeRoleName($role);
        }
    }
    
    if (!$normalized_role_name || (!empty($normalized_allowed) && !in_array($normalized_role_name, $normalized_allowed, true))) {
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: ../index.php");
        exit();
    }
}
?>
