<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'employee';
$role_id = $_SESSION['role_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check if page is active (returns boolean)
function isCurrent($page) {
    global $current_page;
    return $current_page === $page;
}

// Helper function to check if directory/path is active (returns boolean)
function isCurrentDir($dir) {
    $script_path = $_SERVER['PHP_SELF'] ?? '';
    return strpos($script_path, $dir) !== false;
}

// Helper function for active menu item (returns class string)
function isActive($page) {
    return isCurrent($page) 
        ? 'bg-white/10 text-white shadow-sm' 
        : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors duration-200';
}

// Helper function to check if current page is in a directory (returns class string)
function isActiveDir($dir) {
    return isCurrentDir($dir) 
        ? 'bg-white/10 text-white font-medium shadow-sm' 
        : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors duration-200';
}
?>
<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden sidebar-transition backdrop-blur-sm"></div>

<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 bg-[#1e293b] shadow-xl sidebar-transition transform -translate-x-full lg:translate-x-0 transition-all duration-300 w-96 border-r border-slate-700">
    <div class="flex flex-col h-full">
        <!-- Logo/Branding -->
        <div class="flex items-center justify-center px-6 py-6 border-b border-slate-700 transition-all duration-300 bg-[#1e293b]">
            <div class="flex items-center justify-center sidebar-logo-full w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-9 object-contain transition-opacity duration-200">
            </div>
            <div class="flex items-center justify-center sidebar-logo-collapsed hidden w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-9 object-contain transition-opacity duration-200">
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto overflow-x-hidden custom-scrollbar">
            <!-- Dashboard -->
            <a href="<?php echo BASE_URL; ?>/admin/admin-dashboard.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrent('admin-dashboard.php') || strpos($current_page, 'dashboard') !== false ? 'bg-white/10 text-white shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 mr-3 flex-shrink-0 transition-colors group-hover:text-white"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                <span class="sidebar-text text-base">Dashboard</span>
            </a>

            <!-- FINANCIAL MANAGEMENT -->
            <div class="mb-2 mt-6">
                <h3 class="px-3 text-sm font-bold text-slate-500 uppercase tracking-wider mb-3 sidebar-text">
                    Financial Management
                </h3>

                <!-- General Ledger -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('general_ledger') || isCurrent('admin-chart_of_accounts.php') || isCurrent('admin-gl_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="gl-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('general_ledger') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
                            </span>
                            <span class="sidebar-text text-base">General Ledger</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="gl-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-general_ledger.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-general_ledger.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                                <span class="sidebar-text">Journal Entries</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-chart_of_accounts.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-chart_of_accounts.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                                <span class="sidebar-text">Chart of Accounts</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-gl_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-gl_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                                <span class="sidebar-text">GL Reports</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-financial_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-financial_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                <span class="sidebar-text">Financial Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Accounts Payable -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('accounts_payable') || isCurrent('admin-suppliers.php') || isCurrent('admin-ap_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="ap-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('accounts_payable') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                            </span>
                            <span class="sidebar-text text-base">Accounts Payable</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="ap-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_payable.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-accounts_payable.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                <span class="sidebar-text">Supplier Invoices</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-suppliers.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-suppliers.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span class="sidebar-text">Suppliers</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ap_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-ap_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                                <span class="sidebar-text">AP Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Accounts Receivable -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('accounts_receivable') || isCurrent('admin-ar_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="ar-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('accounts_receivable') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </span>
                            <span class="sidebar-text text-base">Accounts Receivable</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="ar-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-accounts_receivable.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-accounts_receivable.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                <span class="sidebar-text">Patient Invoices</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-ar_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-ar_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                                <span class="sidebar-text">AR Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Disbursement -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('disbursement') || isCurrent('admin-disbursement_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="disbursement-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('disbursement') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            </span>
                            <span class="sidebar-text text-base">Disbursement</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="disbursement-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-disbursement.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-disbursement.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span class="sidebar-text">Disbursement Vouchers</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-disbursement_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-disbursement_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                <span class="sidebar-text">Disbursement Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Budget Management -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('budget_management') || isCurrent('admin-budget_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="budget-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('budget_management') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                            </span>
                            <span class="sidebar-text text-base">Budget Management</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="budget-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-budget_management.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-budget_management.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <span class="sidebar-text">Budget Planning</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-budget_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-budget_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                                <span class="sidebar-text">Budget Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Collection -->
                <div class="space-y-1">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group <?php echo isCurrentDir('collection') || isCurrent('admin-collection_reports.php') ? 'bg-white/10 text-white font-medium shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white transition-colors'; ?>"
                        data-collapse-target="collection-submenu"
                    >
                        <span class="flex items-center">
                            <span class="flex items-center justify-center w-6 h-6 mr-3 rounded text-slate-400 group-hover:text-white transition-colors <?php echo isCurrentDir('collection') ? 'text-white' : ''; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-2 0-2-3 0-3h2"/><path d="m9 9 1-1 1-1 1 1 1 1"/><path d="m9 15 1 1 1 1 1-1 1-1"/><path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z"/></svg>
                            </span>
                            <span class="sidebar-text text-base">Collection</span>
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 text-slate-500 transition-transform duration-200 submenu-arrow">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <div id="collection-submenu" class="submenu hidden space-y-1 overflow-hidden transition-all duration-300 ease-in-out pl-3">
                        <div class="relative border-l-2 border-slate-700 ml-2.5 pl-3 py-1 space-y-1">
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-collection.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-collection.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                <span class="sidebar-text">Payment Processing</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-or_generation.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-or_generation.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                <span class="sidebar-text">OR Generation</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/admin/modules/admin-collection_reports.php" class="flex items-center px-3 py-2 text-base rounded-md transition-colors <?php echo isActive('admin-collection_reports.php'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-2.5 opacity-75"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                                <span class="sidebar-text">Collection Reports</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Financial System Setup -->
                <a href="<?php echo BASE_URL; ?>/admin/modules/verify_financial_setup.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 group mt-4 <?php echo isActive('verify_financial_setup.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 mr-3 flex-shrink-0 transition-colors group-hover:text-white"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                    <span class="sidebar-text text-base">System Setup</span>
                </a>

                <!-- Account Settings -->
                <a href="<?php echo BASE_URL; ?>/admin/modules/admin-account_settings.php" class="flex items-center px-3 py-2.5 rounded-lg transition-all duration-200 group mt-1 <?php echo isActive('admin-account_settings.php'); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6 mr-3 flex-shrink-0 transition-colors group-hover:text-white"><path d="M12 1v4"/><path d="M12 19v4"/><path d="M4.22 4.22 6.34 6.34"/><path d="M17.66 17.66l2.12 2.12"/><path d="M1 12h4"/><path d="M19 12h4"/><path d="M4.22 19.78 6.34 17.66"/><path d="M17.66 6.34l2.12-2.12"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="sidebar-text text-base">Account Settings</span>
                </a>
            </div>
        </nav>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle dropdown logic
        document.querySelectorAll('#sidebar [data-collapse-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const targetId = btn.getAttribute('data-collapse-target');
                const submenu = document.getElementById(targetId);
                const arrow = btn.querySelector('.submenu-arrow');
                
                if (!submenu) return;
                
                // Close other open submenus (Accordion behavior)
                document.querySelectorAll('.submenu').forEach(function(otherSubmenu) {
                    if (otherSubmenu.id !== targetId && !otherSubmenu.classList.contains('hidden')) {
                        otherSubmenu.classList.add('hidden');
                        
                        // Reset arrow for the closed submenu
                        const otherBtn = document.querySelector(`[data-collapse-target="${otherSubmenu.id}"]`);
                        if (otherBtn) {
                            const otherArrow = otherBtn.querySelector('.submenu-arrow');
                            if (otherArrow) otherArrow.classList.remove('rotate-180');
                        }
                    }
                });
                
                // Toggle current submenu
                submenu.classList.toggle('hidden');
                if (arrow) {
                    arrow.classList.toggle('rotate-180', !submenu.classList.contains('hidden'));
                }
            });
        });

        // Auto-expand active submenus
        const activeItems = document.querySelectorAll('#sidebar .bg-white\\/10');
        activeItems.forEach(function(item) {
            const parentSubmenu = item.closest('.submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.remove('hidden');
                
                // Find and rotate arrow
                const parentBtn = document.querySelector(`[data-collapse-target="${parentSubmenu.id}"]`);
                if (parentBtn) {
                     const arrow = parentBtn.querySelector('.submenu-arrow');
                     if (arrow) arrow.classList.add('rotate-180');
                }
            }
        });
    });
</script>