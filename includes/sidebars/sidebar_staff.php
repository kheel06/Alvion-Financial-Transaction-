<?php
// Ensure BASE_URL is defined
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

$current_page = basename($_SERVER['PHP_SELF']);

function staffIsActive($page)
{
    global $current_page;
    return $current_page === $page
        ? 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white'
        : '';
}
?>

<!-- Sidebar Overlay (Mobile) -->
<div id="sidebarOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden sidebar-transition"></div>

<aside id="sidebar" class="fixed lg:static inset-y-0 left-0 z-50 bg-white dark:bg-gray-800 shadow-xl sidebar-transition transform -translate-x-full lg:translate-x-0 transition-all duration-300">
    <div class="flex flex-col h-full">
        <!-- Logo/Branding -->
        <div class="flex items-center justify-center px-4 py-5 border-b border-gray-200 dark:border-gray-700 transition-all duration-300">
            <div class="flex items-center justify-center sidebar-logo-full w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-8 object-contain transition-opacity duration-200">
            </div>
            <div class="flex items-center justify-center sidebar-logo-collapsed hidden w-full">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion" class="h-8 object-contain transition-opacity duration-200">
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 px-2.5 py-3 space-y-0.5 overflow-y-auto overflow-x-hidden">
            <!-- Dashboard -->
            <a href="<?php echo BASE_URL; ?>/staff/staff-dashboard.php" class="flex items-center px-3 py-2 text-gray-200 hover:bg-white/5 rounded-md sidebar-item transition-all duration-200 <?php echo $current_page === 'staff-dashboard.php' || strpos($current_page, 'dashboard') !== false ? 'bg-white/5 text-white' : ''; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect></svg>
                <span class="sidebar-text text-sm">Dashboard</span>
            </a>

            <div class="mb-3 mt-4">
                <!-- Team Attendance (dropdown) -->
                <button
                    type="button"
                    class="w-full flex items-center justify-between px-3 py-2 mt-1 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200"
                    data-collapse-target="staff-attendance-submenu"
                >
                    <span class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4"></path><path d="M8 2v4"></path><path d="M3 10h18"></path></svg>
                        <span class="sidebar-text text-sm">Team Attendance</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 ml-2 text-gray-400 transition-transform duration-200">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>

                <div id="staff-attendance-submenu" class="mt-1 space-y-1">
                    <a href="<?php echo BASE_URL; ?>/staff/modules/staff-team_attendance.php?view=logs" class="flex items-center pl-9 pr-3 py-1.5 text-xs text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                        <span class="sidebar-text">Department Attendance Logs</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/staff/modules/staff-team_attendance.php?view=exceptions" class="flex items-center pl-9 pr-3 py-1.5 text-xs text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                        <span class="sidebar-text">Exceptions</span>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/staff/modules/staff-team_attendance.php?view=corrections" class="flex items-center pl-9 pr-3 py-1.5 text-xs text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                        <span class="sidebar-text">Approve Attendance Corrections</span>
                    </a>
                </div>

                <!-- Department Scheduling -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-scheduling.php" class="flex items-center px-3 py-2 mt-3 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <span class="sidebar-text text-sm">Department Scheduling</span>
                </a>

                <!-- Leave Management -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-leave_management.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M3 3h18"></path><path d="M8 3v18"></path><path d="M3 8h10"></path><path d="M3 13h7"></path><path d="M3 18h4"></path></svg>
                    <span class="sidebar-text text-sm">Leave Management</span>
                </a>

                <!-- Overtime Requests -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-overtime_requests.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l3 3"></path></svg>
                    <span class="sidebar-text text-sm">Overtime Requests</span>
                </a>

                <!-- Claims -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-claims.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>
                    <span class="sidebar-text text-sm">Claims</span>
                </a>

                <!-- Team Timesheets -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-timesheets.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg:white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M3 10h18"></path><path d="M8 2v4"></path><path d="M16 2v4"></path></svg>
                    <span class="sidebar-text text-sm">Team Timesheets</span>
                </a>

                <!-- Reports -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-reports.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><line x1="12" y1="20" x2="12" y2="10"></line><line x1="18" y1="20" x2="18" y2="4"></line><line x1="6" y1="20" x2="6" y2="16"></line></svg>
                    <span class="sidebar-text text-sm">Reports</span>
                </a>

                <!-- Account Settings -->
                <a href="<?php echo BASE_URL; ?>/staff/modules/staff-account_settings.php" class="flex items-center px-3 py-2 mt-2 text-gray-200 rounded-md hover:bg-white/5 sidebar-item transition-all duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5 mr-3 flex-shrink-0"><path d="M12 1v4"></path><path d="M12 19v4"></path><path d="M4.22 4.22 6.34 6.34"></path><path d="M17.66 17.66l2.12 2.12"></path><path d="M1 12h4"></path><path d="M19 12h4"></path><path d="M4.22 19.78 6.34 17.66"></path><path d="M17.66 6.34l2.12-2.12"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <span class="sidebar-text text-sm">Account Settings</span>
                </a>
            </div>
        </nav>

        <div class="p-3 border-t border-gray-200/10"></div>
    </div>
</aside>

<script>
    // Dropdown for staff sidebar submodules
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#sidebar [data-collapse-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-collapse-target');
                if (!targetId) return;
                var submenu = document.getElementById(targetId);
                if (!submenu) return;
                submenu.classList.toggle('hidden');
            });
        });
    });
</script>

