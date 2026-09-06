<?php
// Use __DIR__ to get absolute path regardless of where this file is included from
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/1.8.1/flowbite.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <!-- Favicons (provide explicit sizes for crisp tab icon) -->
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="icon" type="image/png" sizes="512x512" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>/assets/img/alvion-emblem-removebg.png">
    
    <script>
        // Enhanced dark mode initialization for cross-device compatibility
        // Works on Windows 11/10, Android, iOS, macOS, and all modern browsers
        (function() {
            'use strict';
            
            // Robust system theme detection function
            function detectSystemTheme() {
                // Primary method: prefers-color-scheme media query (supported in all modern browsers)
                if (window.matchMedia) {
                    try {
                        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
                        if (darkModeQuery && darkModeQuery.matches !== undefined) {
                            return darkModeQuery.matches ? 'dark' : 'light';
                        }
                    } catch (e) {
                        // Fallback if matchMedia fails
                    }
                }
                
                // Fallback method: Check for system-level indicators
                // This helps with older browsers or edge cases
                if (window.screen && window.screen.colorDepth) {
                    // Some older systems might have lower color depth in dark mode
                    // This is a very weak indicator, but better than nothing
                }
                
                // Default to light if we can't detect
                return 'light';
            }
            
            // Apply theme based on preference
            function applyTheme(themePreference) {
                let shouldUseDark = false;
                
                if (themePreference === 'dark') {
                    shouldUseDark = true;
                } else if (themePreference === 'light') {
                    shouldUseDark = false;
                } else if (themePreference === 'system' || !themePreference) {
                    // System preference - detect current system theme
                    const systemTheme = detectSystemTheme();
                    shouldUseDark = systemTheme === 'dark';
                }
                
                // Apply the theme class
                if (shouldUseDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
            
            // Initialize theme immediately
            try {
                // Get stored preference
                let storedTheme = 'system';
                try {
                    const stored = localStorage.getItem('theme');
                    if (stored === 'light' || stored === 'dark' || stored === 'system') {
                        storedTheme = stored;
                    }
                } catch (e) {
                    // localStorage might not be available (private browsing, etc.)
                    storedTheme = 'system';
                }
                
                // Apply theme immediately to prevent FOUC
                applyTheme(storedTheme);
                
                // Store preference for later use
                window.__themePreference = storedTheme;
                
            } catch (e) {
                // Ultimate fallback: try to detect system theme
                try {
                    const systemTheme = detectSystemTheme();
                    applyTheme(systemTheme);
                } catch (fallbackError) {
                    // If everything fails, default to light mode
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
        })();
        
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                        }
                    }
                }
            },
            corePlugins: {
                preflight: true,
            }
        }
    </script>
    
    <style>
    .sidebar-transition {
        transition: all 0.3s ease-in-out;
    }
    /* Ensure sidebar responds to dark mode changes */
    #sidebar,
    #sidebar * {
        transition-property: background-color, border-color, color, fill, stroke;
        transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        transition-duration: 200ms;
    }
    /* Sidebar spacing when expanded */
    #sidebar:not(.sidebar-collapsed) nav {
        padding-left: 0.625rem;
        padding-right: 0.625rem;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    #sidebar:not(.sidebar-collapsed) .sidebar-item {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    /* Sidebar collapsed state */
    #sidebar {
        transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
    }
    @media (max-width: 1023px) {
        #sidebar {
            width: 18rem;
        }
    }
    @media (min-width: 1024px) {
        #sidebar {
            width: 18rem;
        }
        #sidebar.sidebar-collapsed {
            width: 4rem !important;
            min-width: 4rem;
        }
    }
    #sidebar.sidebar-collapsed .sidebar-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
        white-space: nowrap;
    }
    #sidebar.sidebar-collapsed .sidebar-logo-full {
        display: none;
    }
    #sidebar.sidebar-collapsed .sidebar-logo-collapsed {
        display: block !important;
    }
    #sidebar.sidebar-collapsed .flex.items-center.justify-center {
        justify-content: center;
        width: 100%;
    }
    #sidebar.sidebar-collapsed .sidebar-item {
        justify-content: center;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
    }
    #sidebar.sidebar-collapsed .sidebar-item .mr-3 {
        margin-right: 0;
    }
    #sidebar.sidebar-collapsed nav {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }
    #sidebar.sidebar-collapsed h3 {
        opacity: 0;
        height: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }
    /* Hide scrollbar when collapsed */
    #sidebar.sidebar-collapsed nav {
        overflow: hidden !important;
    }
    #sidebar.sidebar-collapsed nav::-webkit-scrollbar {
        display: none !important;
    }
    #sidebar.sidebar-collapsed nav {
        -ms-overflow-style: none !important;
        scrollbar-width: none !important;
    }
    /* Adjust footer padding when collapsed */
    #sidebar.sidebar-collapsed .p-3 {
        padding: 0.75rem 0.5rem;
    }
    /* Adjust logo padding when collapsed */
    #sidebar.sidebar-collapsed > div > div:first-child {
        padding: 1rem 0.5rem;
    }
    
    
    .sidebar-text {
        transition: opacity 0.3s ease-in-out, width 0.3s ease-in-out;
    }
    
    .page-enter {
        animation: fadeIn 0.3s ease-in;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    /* User dropdown - positioning and animation (Header) */
    #userDropdown {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 0.5rem;
        width: 16rem;
        z-index: 9999;
        transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
    }
    #userDropdown.hidden {
        opacity: 0;
        transform: translateY(-10px);
        pointer-events: none;
    }
    #userDropdown:not(.hidden) {
        opacity: 1;
        transform: translateY(0);
    }
    /* Notification dropdown animation */
    #notificationDropdown {
        transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
    }
    #notificationDropdown.hidden {
        opacity: 0;
        transform: translateY(-10px);
        pointer-events: none;
    }
    #notificationDropdown:not(.hidden) {
        opacity: 1;
        transform: translateY(0);
    }
    /* Notification badge pulse animation */
    #notificationBadge {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: .5;
        }
    }
    
    /* Page Loader Styles */
    body:has(#pageLoader[style*="display: flex"]) {
        overflow: hidden !important;
    }
    
    #pageLoader {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
</style>
</head>
<body class="h-full">
    <!-- Full Page Loader -->
    <div id="pageLoader" class="fixed inset-0 z-[9999] bg-white dark:bg-gray-900 flex flex-col items-center justify-center transition-opacity duration-300 ease-out" style="display: flex !important; opacity: 1 !important;">
        <div class="flex flex-col items-center justify-center">
            <!-- Logo -->
            <div class="mb-8 animate-pulse">
                <img src="<?php echo BASE_URL; ?>/assets/img/alvion-logo-removebg.png" alt="Alvion Logo" class="h-20 md:h-24 w-auto">
            </div>
            
            <!-- Spinner -->
            <div class="relative w-16 h-16 mb-6">
                <div class="absolute inset-0 border-4 border-[#008080]/20 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-transparent border-t-[#008080] rounded-full animate-spin"></div>
            </div>
            
            <!-- Loading Text -->
            <div class="text-center">
                <p class="text-lg font-semibold text-gray-900 dark:text-white mb-2" id="loaderText">Loading...</p>
                <div class="flex items-center justify-center space-x-1">
                    <div class="w-2 h-2 bg-[#008080] rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                    <div class="w-2 h-2 bg-[#008080] rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                    <div class="w-2 h-2 bg-[#008080] rounded-full animate-bounce" style="animation-delay: 0.4s;"></div>
                </div>
            </div>
            
            <!-- Progress Bar (Optional) -->
            <div class="w-64 h-1 bg-gray-200 dark:bg-gray-700 rounded-full mt-6 overflow-hidden">
                <div id="loaderProgress" class="h-full bg-gradient-to-r from-[#008080] to-[#0f766e] rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize loader elements
        const pageLoader = document.getElementById('pageLoader');
        const loaderText = document.getElementById('loaderText');
        const loaderProgress = document.getElementById('loaderProgress');
        
        function startProgress() {
            let width = 0;
            const interval = setInterval(() => {
                if (width >= 90) clearInterval(interval);
                else {
                    width += 5;
                    if (loaderProgress) loaderProgress.style.width = width + '%';
                }
            }, 100);
        }

        // Show loader immediately (it's already visible in HTML)
        // Start progress simulation
        startProgress();
        
        // Update loader text based on page state
        function updateLoaderText(text) {
            if (loaderText) {
                loaderText.textContent = text;
            }
        }
        
        // Fallback to hide loader if footer script fails or page crashes
        setTimeout(function() {
            if (pageLoader && pageLoader.style.display !== 'none') {
                console.warn('Loader cleanup fallback triggered from header');
                if (typeof hideLoader === 'function') {
                    hideLoader();
                } else {
                    // Manual hide if function not available
                    pageLoader.style.opacity = '0';
                    setTimeout(() => {
                        pageLoader.style.display = 'none';
                    }, 300);
                }
            }
        }, 8000); // 8 seconds failsafe
    </script>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Main Layout with Sidebar -->
    <div class="flex h-screen bg-gray-50 dark:bg-gray-900">
        <!-- Sidebar -->
        <?php 
        $role_name_raw        = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'employee';
        $normalized_role_name = normalizeRoleName($role_name_raw);

        // Map normalized roles to sidebar include files
        $sidebar_map = [
            'super admin' => __DIR__ . '/sidebars/sidebar_superadmin.php',
            'admin'       => __DIR__ . '/sidebars/sidebar_admin.php',
            'staff'       => __DIR__ . '/sidebars/sidebar_staff.php',
            'employee'    => __DIR__ . '/sidebars/sidebar_employee.php',
        ];

        $sidebar_path = $sidebar_map[$normalized_role_name] ?? __DIR__ . '/sidebars/sidebar_admin.php';

        if (!file_exists($sidebar_path)) {
            // Fallback to admin sidebar if specific file is missing
            $sidebar_path = __DIR__ . '/sidebars/sidebar_admin.php';
        }

        include $sidebar_path;
        ?>
        
        <!-- Main Content -->
        <div id="mainContent" class="flex-1 flex flex-col overflow-hidden transition-all duration-300 ease-in-out">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm z-10 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between px-6 py-3">
                    <div class="flex items-center flex-1">
                        <button id="sidebarToggle" class="lg:hidden text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-xl"><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>
                        </button>
                        <!-- Desktop Sidebar Collapse Button -->
                        <button id="sidebarCollapseBtn" class="hidden lg:flex items-center justify-center w-8 h-8 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 flex-shrink-0 ml-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-lg"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                        </button>
                        
                        <!-- Search Bar - Prominent -->
                        <div class="hidden md:flex items-center flex-1 max-w-md mx-6">
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
                                </div>
                                <input type="text" id="globalSearch" placeholder="Search" class="block w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent text-sm shadow-sm">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <!-- Time Display -->
                        <div class="hidden md:flex items-center space-x-2 text-gray-600 dark:text-gray-400 py-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-lg"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span id="currentTime" class="text-sm font-medium leading-none"></span>
                        </div>
                        
                        <!-- Notification Bell -->
                        <div class="relative">
                            <button type="button" id="notificationButton" class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-xl"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                <span id="notificationBadge" class="hidden absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                            </button>
                            
                            <!-- Notification Dropdown -->
                            <div id="notificationDropdown" class="hidden absolute top-full right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg z-50 border border-gray-200 dark:border-gray-700">
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Notifications</h3>
                                    <button id="clearNotificationsBtn" onclick="clearNotifications()" class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 hidden">
                                        Clear all
                                    </button>
                                </div>
                                <div id="notificationList" class="max-h-80 overflow-y-auto">
                                    <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                                        No new notifications
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Theme Switcher -->
                        <div class="relative">
                            <button id="themeToggle" aria-haspopup="true" aria-expanded="false" aria-controls="themeDropdown" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center justify-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dark:hidden text-xl"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path></svg>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden dark:block text-xl"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2"></path><path d="M12 20v2"></path><path d="m4.93 4.93 1.41 1.41"></path><path d="m17.66 17.66 1.41 1.41"></path><path d="M2 12h2"></path><path d="M20 12h2"></path><path d="m6.34 17.66-1.41 1.41"></path><path d="m19.07 4.93-1.41 1.41"></path></svg>
                            </button>
                            <div id="themeDropdown" class="hidden absolute right-0 mt-2 w-40 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 py-2 z-50">
                                <p class="px-4 pb-2 text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Theme</p>
                                <button type="button" data-theme-option="light" class="theme-option flex w-full items-center justify-between px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <span>Light</span>
                                    <svg class="theme-option-check w-4 h-4 text-primary-500 opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 12 5 5 9-9"></path></svg>
                                </button>
                                <button type="button" data-theme-option="dark" class="theme-option flex w-full items-center justify-between px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <span>Dark</span>
                                    <svg class="theme-option-check w-4 h-4 text-primary-500 opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 12 5 5 9-9"></path></svg>
                                </button>
                                <button type="button" data-theme-option="system" class="theme-option flex w-full items-center justify-between px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                    <span>System</span>
                                    <svg class="theme-option-check w-4 h-4 text-primary-500 opacity-0 transition-opacity" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m5 12 5 5 9-9"></path></svg>
                                </button>
                            </div>
                        </div>
                        
                        <!-- User Menu (Header) -->
                        <div class="relative ml-2">
                            <?php 
                            $session_first_name = $_SESSION['first_name'] ?? '';
                            $session_last_name = $_SESSION['last_name'] ?? '';
                            $first_initial = strtoupper(substr($session_first_name, 0, 1));
                            $last_initial = strtoupper(substr($session_last_name, 0, 1));
                            $user_initials = $first_initial . $last_initial;
                            $role_name = $_SESSION['role_name'] ?? $_SESSION['user_role'] ?? 'user';
                            $normalized_role = normalizeRoleName($role_name);
                            $employee_roles = ['super admin', 'admin', 'staff', 'employee'];
                            $is_employee_role = in_array($normalized_role, $employee_roles, true);
                            $is_self_service_role = ($normalized_role === 'employee');
                            
                            // For employee self-service, always fetch email from users table (not username)
                            if ($is_self_service_role && isset($_SESSION['user_id'])) {
                                if (!empty($_SESSION['email'])) {
                                    $user_email = $_SESSION['email'];
                                } else {
                                    try {
                                        $email_query = "SELECT email FROM users WHERE id = :user_id LIMIT 1";
                                        $email_stmt = $db->prepare($email_query);
                                        $email_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                                        $email_stmt->execute();
                                        $email_result = $email_stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($email_result && !empty($email_result['email'])) {
                                            $user_email = $email_result['email'];
                                            $_SESSION['email'] = $user_email;
                                        } else {
                                            $user_email = $_SESSION['username'] ?? '';
                                        }
                                    } catch (PDOException $e) {
                                        $user_email = $_SESSION['username'] ?? '';
                                    }
                                }
                            } else {
                                // For non-patient roles, use email from session or fallback to username
                                $user_email = $_SESSION['email'] ?? $_SESSION['username'] ?? '';
                            }
                            
                            $display_full_name = trim($session_first_name . ' ' . $session_last_name);
                            $display_secondary = $user_email;
                            $display_role_badge = $is_self_service_role ? '' : ucfirst(str_replace('_', ' ', $role_name));
                            
                            $employee_record = null;
                            if ($is_employee_role) {
                                try {
                                    $department_tables = ['department_accounts', 'department_account'];
                                    $employee_table = null;
                                    foreach ($department_tables as $table_candidate) {
                                        $table_stmt = $db->prepare("SHOW TABLES LIKE :table_name");
                                        $table_stmt->bindValue(':table_name', $table_candidate);
                                        $table_stmt->execute();
                                        if ($table_stmt->rowCount() > 0) {
                                            $employee_table = $table_candidate;
                                            break;
                                        }
                                    }
                                    
                                    if ($employee_table) {
                                        $columns_stmt = $db->query("SHOW COLUMNS FROM `{$employee_table}`");
                                        $columns = $columns_stmt ? array_column($columns_stmt->fetchAll(PDO::FETCH_ASSOC), 'Field') : [];
                                        $identifier_candidates = [
                                            'employee_id' => $_SESSION['employee_id'] ?? null,
                                            'user_id' => $_SESSION['user_id'] ?? null,
                                            'id' => $_SESSION['employee_id'] ?? ($_SESSION['user_id'] ?? null),
                                        ];
                                        
                                        foreach ($identifier_candidates as $column => $value) {
                                            if (empty($value) || !in_array($column, $columns, true)) {
                                                continue;
                                            }
                                            
                                            $record_stmt = $db->prepare("SELECT * FROM `{$employee_table}` WHERE `{$column}` = :identifier LIMIT 1");
                                            if (is_numeric($value)) {
                                                $value = (int)$value;
                                                $record_stmt->bindParam(':identifier', $value, PDO::PARAM_INT);
                                            } else {
                                                $record_stmt->bindParam(':identifier', $value, PDO::PARAM_STR);
                                            }
                                            $record_stmt->execute();
                                            $employee_record = $record_stmt->fetch(PDO::FETCH_ASSOC);
                                            
                                            if ($employee_record) {
                                                if (!isset($_SESSION['employee_id']) && !empty($employee_record['employee_id'])) {
                                                    $_SESSION['employee_id'] = $employee_record['employee_id'];
                                                }
                                                break;
                                            }
                                        }
                                    }
                                } catch (PDOException $e) {
                                    $employee_record = null;
                                }
                            }
                            
                            if ($employee_record) {
                                $employee_first = $employee_record['first_name']
                                    ?? $employee_record['employee_fname']
                                    ?? $employee_record['fname']
                                    ?? $session_first_name;
                                $employee_last = $employee_record['last_name']
                                    ?? $employee_record['employee_lname']
                                    ?? $employee_record['lname']
                                    ?? $session_last_name;
                                $employee_identifier = $employee_record['employee_id']
                                    ?? $employee_record['id']
                                    ?? $employee_record['user_id']
                                    ?? '';
                                $employee_email = $employee_record['employee_email']
                                    ?? $employee_record['email']
                                    ?? $employee_record['work_email']
                                    ?? $user_email;
                                $employee_role_label = $employee_record['role_name']
                                    ?? $employee_record['role']
                                    ?? $role_name;
                                
                                $display_full_name = trim($employee_first . ' ' . $employee_last) ?: $display_full_name;
                                $display_secondary = $employee_identifier ? 'ID: ' . $employee_identifier : $employee_email;
                                $display_role_badge = ucfirst(str_replace('_', ' ', $employee_role_label));
                            } elseif ($is_self_service_role) {
                                $display_role_badge = '';
                                // Use email from users table (already fetched above)
                                $display_secondary = $user_email;
                            } else {
                                $display_secondary = '';
                            }
                            
                            // Get user profile picture (check session first, then database)
                            $user_profile_picture = $_SESSION['profile_picture'] ?? null;
                            
                            // Always try users table first (covers patients and employees synced to users)
                            // Only fetch from database if session doesn't have it (to avoid unnecessary queries)
                            if (empty($user_profile_picture) && isset($_SESSION['user_id'])) {
                                try {
                                    $check_column = $db->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
                                    $column_exists = $check_column->rowCount() > 0;
                                    
                                    if ($column_exists) {
                                        $pic_query = "SELECT profile_picture FROM users WHERE id = :user_id";
                                        $pic_stmt = $db->prepare($pic_query);
                                        $pic_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                                        $pic_stmt->execute();
                                        $pic_result = $pic_stmt->fetch(PDO::FETCH_ASSOC);
                                        // Only set if profile_picture exists and is not NULL/empty
                                        if ($pic_result && !empty($pic_result['profile_picture'])) {
                                            $user_profile_picture = $pic_result['profile_picture'];
                                            $_SESSION['profile_picture'] = $user_profile_picture;
                                        } else {
                                            // Explicitly clear session if database has NULL/empty
                                            unset($_SESSION['profile_picture']);
                                            $user_profile_picture = null;
                                        }
                                    }
                                } catch (PDOException $e) {
                                    $user_profile_picture = null;
                                }
                            }
                            
                            // For employees, check employee record if users table doesn't have it
                            if (empty($user_profile_picture) && $employee_record && !empty($employee_record['profile_picture'])) {
                                $user_profile_picture = $employee_record['profile_picture'];
                                $_SESSION['profile_picture'] = $user_profile_picture;
                            } elseif ($employee_record && empty($employee_record['profile_picture'])) {
                                // If employee record has no profile picture, ensure session is cleared
                                unset($_SESSION['profile_picture']);
                                $user_profile_picture = null;
                            }
                            ?>
                            <button type="button" id="userMenuButton" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <div class="relative">
                                    <?php if (!empty($user_profile_picture) && file_exists(__DIR__ . '/../' . $user_profile_picture)): ?>
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user_profile_picture); ?>" 
                                             alt="Profile Picture" 
                                             class="w-10 h-10 rounded-full object-cover flex-shrink-0 border border-gray-200 dark:border-gray-700">
                                    <?php else: ?>
                                        <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white flex-shrink-0 font-semibold text-sm">
                                            <?php echo $user_initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-500 dark:text-gray-300"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                    </span>
                                </div>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="userDropdown" class="hidden absolute top-full right-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-lg shadow-2xl z-50 border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <!-- User Profile Section -->
                                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center space-x-3">
                                        <?php if (!empty($user_profile_picture) && file_exists(__DIR__ . '/../' . $user_profile_picture)): ?>
                                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($user_profile_picture); ?>" 
                                                 alt="Profile Picture" 
                                                 class="w-12 h-12 rounded-full object-cover flex-shrink-0 border border-gray-200 dark:border-gray-700">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center text-white flex-shrink-0 font-semibold text-sm">
                                                <?php echo $user_initials; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                                <?php echo htmlspecialchars($display_full_name); ?>
                                            </p>
                                            <?php if (!empty($display_secondary)): ?>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                    <?php echo htmlspecialchars($display_secondary); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($display_role_badge)): ?>
                                                <span class="mt-1 inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full bg-primary-50 text-primary-700 dark:bg-primary-600/20 dark:text-primary-100">
                                                    <?php echo htmlspecialchars($display_role_badge); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Menu Items -->
                                <div class="py-1">
                                    <?php
                                    $accountLinkMap = [
                                        'super admin'      => BASE_URL . '/super_admin/modules/super_admin-account_settings.php',
                                        'admin'            => BASE_URL . '/admin/modules/admin-account_settings.php',
                                        'staff'            => BASE_URL . '/staff/modules/staff-account_settings.php',
                                        'staff supervisor' => BASE_URL . '/staff/modules/staff-account_settings.php',
                                        'employee'         => BASE_URL . '/employee/modules/employee-account_profile.php',
                                    ];
                                    $normalizedRole = $normalized_role;
                                    $accountLink = $accountLinkMap[$normalizedRole] ?? BASE_URL . '/modules/profile/profile.php';
                                    ?>
                                    <a href="<?php echo $accountLink; ?>" class="flex items-center px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3 text-gray-500 dark:text-gray-400"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>
                                        Account
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>/auth/logout.php" data-logout-trigger="true" class="flex items-center px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-3 text-red-600 dark:text-red-400"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                                        Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="relative flex-1 overflow-y-auto p-6 page-enter bg-gray-50 dark:bg-gray-900">
    <?php else: ?>
    <!-- Public Layout -->
    <main>
    <?php endif; ?>