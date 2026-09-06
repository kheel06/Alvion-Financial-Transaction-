// Main JavaScript functionality

// Enhanced Dark Mode Utility Functions for cross-device compatibility
// Works on Windows 11/10, Android, iOS, macOS, and all modern browsers

// Robust system theme detection with multiple fallback methods
function detectSystemTheme() {
    // Primary method: prefers-color-scheme media query
    // Supported in: Chrome 76+, Firefox 67+, Safari 12.1+, Edge 79+, Opera 62+
    // Works on: Windows 10/11, macOS, iOS 13+, Android 10+
    if (window.matchMedia) {
        try {
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (darkModeQuery && typeof darkModeQuery.matches !== 'undefined') {
                return darkModeQuery.matches ? 'dark' : 'light';
            }
        } catch (e) {
            // Continue to fallback methods
        }
    }
    
    // Ultimate fallback: Default to light mode
    // This ensures the app always has a valid theme
    return 'light';
}

function getThemePreference() {
    try {
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme === 'light' || storedTheme === 'dark' || storedTheme === 'system') {
            return storedTheme;
        }
    } catch (e) {
        // localStorage might not be available (private browsing, etc.)
    }
    return 'system';
}

function applyThemePreference(preference) {
    let effectiveTheme = 'light';
    
    if (preference === 'dark') {
        effectiveTheme = 'dark';
    } else if (preference === 'light') {
        effectiveTheme = 'light';
    } else if (preference === 'system' || !preference) {
        // System preference - detect current system theme
        const systemTheme = detectSystemTheme();
        effectiveTheme = systemTheme;
    }
    
    // Apply the theme class
    if (effectiveTheme === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        document.documentElement.setAttribute('data-theme', 'light');
    }
    
    // Store preference for debugging/consistency
    document.documentElement.dataset.themePreference = preference || 'system';
}

function updateThemeDropdownSelection(preference) {
    const options = document.querySelectorAll('[data-theme-option]');
    options.forEach(option => {
        const isActive = option.getAttribute('data-theme-option') === preference;
        option.classList.toggle('bg-gray-100', isActive);
        option.classList.toggle('dark:bg-gray-700', isActive);
        option.classList.toggle('font-semibold', isActive);

        const checkIcon = option.querySelector('.theme-option-check');
        if (checkIcon) {
            checkIcon.classList.toggle('opacity-100', isActive);
        }
    });
}

function setThemePreference(preference) {
    if (preference === 'system') {
        try {
            localStorage.removeItem('theme');
        } catch (e) {
            // localStorage might not be available
        }
    } else {
        try {
            localStorage.setItem('theme', preference);
        } catch (e) {
            // localStorage might not be available
        }
    }
    applyThemePreference(preference);
    updateThemeDropdownSelection(preference);
    
    // Trigger periodic check update if needed (will be set up in DOMContentLoaded)
    if (typeof window.__updatePeriodicThemeCheck === 'function') {
        window.__updatePeriodicThemeCheck();
    }
}

function initDarkMode() {
    try {
        const preference = getThemePreference();
        applyThemePreference(preference);
    } catch (e) {
        console.warn('Dark mode initialization failed:', e);
    }
}

function toggleDarkMode() {
    const isDark = document.documentElement.classList.contains('dark');
    const nextPreference = isDark ? 'light' : 'dark';
    setThemePreference(nextPreference);
    // Force reflow to ensure transitions work
    document.body.offsetHeight;
}

// Initialize Dark Mode BEFORE DOM loads to prevent FOUC
// This runs immediately when the script loads
if (document.readyState === 'loading') {
    initDarkMode();
} else {
    // DOM already loaded, initialize immediately
    initDarkMode();
}

// Dropdown Management Functions
function closeAllDropdowns() {
    const dropdowns = [
        document.getElementById('userDropdown'),
        document.getElementById('notificationDropdown'),
        document.getElementById('themeDropdown')
    ];
    
    dropdowns.forEach(dropdown => {
        if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    });
}

function setupDropdown(buttonId, dropdownId, closeOthers = true) {
    const button = document.getElementById(buttonId);
    const dropdown = document.getElementById(dropdownId);
    
    if (!button || !dropdown) return;
    
    const container = button.closest('.relative');
    
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        const isHidden = dropdown.classList.contains('hidden');
        
        if (closeOthers) {
            closeAllDropdowns();
        }
        
        if (isHidden) {
            dropdown.classList.remove('hidden');
        } else {
            dropdown.classList.add('hidden');
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    return { button, dropdown, container };
}

// Update Current Time
function updateCurrentTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[now.getMonth()];
        const day = now.getDate();
        const year = now.getFullYear();
        
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        const hoursStr = String(hours); // No padding for hours to match format
        
        const formattedTime = `${month} ${day}, ${year}, ${hoursStr}:${minutes}:${seconds} ${ampm}`;
        timeElement.textContent = formattedTime;
    }
}

// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const userMenuButton = document.getElementById('userMenuButton');
    const notificationButton = document.getElementById('notificationButton');
    const themeToggle = document.getElementById('themeToggle');
    const themeOptions = document.querySelectorAll('[data-theme-option]');
    
    // Initialize and update time
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);

    // Setup dropdowns with coordinated closing
    const userDropdown = setupDropdown('userMenuButton', 'userDropdown');
    const notificationDropdown = setupDropdown('notificationButton', 'notificationDropdown');
    const themeDropdown = setupDropdown('themeToggle', 'themeDropdown');

    // Close all dropdowns when clicking anywhere on the document
    document.addEventListener('click', function(e) {
        closeAllDropdowns();
    });

    // Close dropdowns when pressing Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllDropdowns();
        }
    });

    // Sidebar Toggle (Mobile)
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarCollapseBtn = document.getElementById('sidebarCollapseBtn');
    const mainContent = document.getElementById('mainContent');
    
    // Check for saved sidebar state (handled below after elements are defined)
    
    // Mobile sidebar toggle
    if (sidebarToggle && sidebar && sidebarOverlay) {
        const openSidebar = () => {
            sidebar.classList.remove('-translate-x-full');
            sidebarOverlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        };
        
        const closeSidebar = () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
            document.body.style.overflow = '';
        };
        
        sidebarToggle.addEventListener('click', function() {
            if (sidebar.classList.contains('-translate-x-full')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
        
        // Close sidebar when clicking overlay
        sidebarOverlay.addEventListener('click', closeSidebar);
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !sidebar.classList.contains('-translate-x-full')) {
                closeSidebar();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
                document.body.style.overflow = '';
            } else {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    closeSidebar();
                }
            }
        });
    }
    
    // Desktop sidebar collapse/expand
    if (sidebarCollapseBtn && sidebar && mainContent) {
        sidebarCollapseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Only collapse on desktop (lg breakpoint and above)
            if (window.innerWidth < 1024) {
                return;
            }
            
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            
            if (isCollapsed) {
                sidebar.classList.remove('sidebar-collapsed');
                sidebar.style.width = '';
                mainContent.classList.remove('sidebar-collapsed-content');
                localStorage.setItem('sidebarCollapsed', 'false');
            } else {
                sidebar.classList.add('sidebar-collapsed');
                sidebar.style.width = '4rem';
                mainContent.classList.add('sidebar-collapsed-content');
                localStorage.setItem('sidebarCollapsed', 'true');
            }
        });
    }

    // Apply saved collapsed state on load
    if (sidebar && mainContent && window.innerWidth >= 1024) {
        const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isSidebarCollapsed) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.style.width = '4rem';
            mainContent.classList.add('sidebar-collapsed-content');
        } else {
            sidebar.style.width = '';
            mainContent.classList.remove('sidebar-collapsed-content');
        }
    }

    if (themeOptions.length) {
        themeOptions.forEach(option => {
            option.addEventListener('click', function(e) {
                e.preventDefault();
                const preference = option.getAttribute('data-theme-option');
                setThemePreference(preference);
                if (themeDropdown && themeDropdown.dropdown) {
                    themeDropdown.dropdown.classList.add('hidden');
                }
                if (themeToggle) {
                    themeToggle.setAttribute('aria-expanded', 'false');
                }
            });
        });
        updateThemeDropdownSelection(getThemePreference());
    }
    
    // Ensure theme is applied (fallback for any edge cases)
    // Re-initialize to ensure consistency
    initDarkMode();
    
    // Enhanced system theme change listener for cross-device compatibility
    // Listens for system theme changes on Windows, Android, iOS, macOS
    if (window.matchMedia) {
        try {
            // Primary listener for prefers-color-scheme changes
            const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');
            
            // Use addEventListener if available (modern browsers)
            if (darkModeQuery.addEventListener) {
                darkModeQuery.addEventListener('change', function(e) {
                    const preference = getThemePreference();
                    if (preference === 'system') {
                        applyThemePreference(preference);
                        updateThemeDropdownSelection(preference);
                    }
                });
            } 
            // Fallback for older browsers (Safari < 14, older Chrome)
            else if (darkModeQuery.addListener) {
                darkModeQuery.addListener(function(e) {
                    const preference = getThemePreference();
                    if (preference === 'system') {
                        applyThemePreference(preference);
                        updateThemeDropdownSelection(preference);
                    }
                });
            }
            
            // Additional listener for light mode changes (some browsers need this)
            const lightModeQuery = window.matchMedia('(prefers-color-scheme: light)');
            if (lightModeQuery.addEventListener) {
                lightModeQuery.addEventListener('change', function(e) {
                    const preference = getThemePreference();
                    if (preference === 'system') {
                        applyThemePreference(preference);
                        updateThemeDropdownSelection(preference);
                    }
                });
            } else if (lightModeQuery.addListener) {
                lightModeQuery.addListener(function(e) {
                    const preference = getThemePreference();
                    if (preference === 'system') {
                        applyThemePreference(preference);
                        updateThemeDropdownSelection(preference);
                    }
                });
            }
        } catch (e) {
            // If media query listeners fail, log but don't break the app
            console.warn('System theme change detection not available:', e);
        }
    }
    
    // Periodic check for system theme changes (fallback for browsers that don't support listeners)
    // This helps catch theme changes on devices where the listener might not fire properly
    // Only runs if system preference is selected and listeners might not be working
    let lastSystemTheme = detectSystemTheme();
    let periodicCheckInterval = null;
    
    // Only start periodic check if we're using system theme
    function startPeriodicCheckIfNeeded() {
        const preference = getThemePreference();
        if (preference === 'system') {
            // Clear existing interval if any
            if (periodicCheckInterval) {
                clearInterval(periodicCheckInterval);
            }
            // Check every 3 seconds (less frequent, more efficient)
            periodicCheckInterval = setInterval(function() {
                const currentPreference = getThemePreference();
                if (currentPreference === 'system') {
                    const currentSystemTheme = detectSystemTheme();
                    if (currentSystemTheme !== lastSystemTheme) {
                        lastSystemTheme = currentSystemTheme;
                        applyThemePreference(currentPreference);
                        updateThemeDropdownSelection(currentPreference);
                    }
                } else {
                    // Stop checking if user switched away from system preference
                    if (periodicCheckInterval) {
                        clearInterval(periodicCheckInterval);
                        periodicCheckInterval = null;
                    }
                }
            }, 3000); // Check every 3 seconds
        } else {
            // Stop checking if not using system preference
            if (periodicCheckInterval) {
                clearInterval(periodicCheckInterval);
                periodicCheckInterval = null;
            }
        }
    }
    
    // Start periodic check if needed
    startPeriodicCheckIfNeeded();
    
    // Expose function to update periodic check when theme preference changes
    window.__updatePeriodicThemeCheck = startPeriodicCheckIfNeeded;

    // Auto-dismiss alerts
    const autoDismissAlerts = document.querySelectorAll('.alert-auto-dismiss');
    autoDismissAlerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Form validation helpers
    const forms = document.querySelectorAll('form[needs-validation]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Philippine phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('0')) {
                value = '+63' + value.substring(1);
            }
            if (value.startsWith('63')) {
                value = '+' + value;
            }
            e.target.value = value;
        });
    });
});

// Search functionality
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Table sorting
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const isNumeric = !isNaN(parseFloat(rows[0].cells[columnIndex].textContent));
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
            return parseFloat(aValue) - parseFloat(bValue);
        } else {
            return aValue.localeCompare(bValue);
        }
    });
    
    // Remove existing rows
    while (tbody.firstChild) {
        tbody.removeChild(tbody.firstChild);
    }
    
    // Add sorted rows
    rows.forEach(row => tbody.appendChild(row));
}

// Print functionality
function printElement(elementId) {
    const element = document.getElementById(elementId);
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Print</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    @media print { 
                        .no-print { display: none !important; }
                        body { margin: 0; }
                    }
                </style>
            </head>
            <body>
                ${element.innerHTML}
                <script>
                    window.onload = function() { window.print(); }
                <\/script>
            </body>
        </html>
    `);
    printWindow.document.close();
}

// Notification Management Functions
function addNotification(title, message, type = 'info', link = null) {
    const notificationList = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    
    if (!notificationList) return;
    
    // Remove "No new notifications" message if it exists
    const emptyMessage = notificationList.querySelector('.text-center');
    if (emptyMessage && emptyMessage.textContent.includes('No new notifications')) {
        emptyMessage.remove();
    }
    
    // Create notification item
    const notificationItem = document.createElement('div');
    notificationItem.className = `px-4 py-3 border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors`;
    
    const typeColors = {
        'info': 'text-blue-600 dark:text-blue-400',
        'success': 'text-green-600 dark:text-green-400',
        'warning': 'text-yellow-600 dark:text-yellow-400',
        'error': 'text-red-600 dark:text-red-400'
    };
    
    const iconMap = {
        'info': 'ph-info',
        'success': 'ph-check-circle',
        'warning': 'ph-warning',
        'error': 'ph-x-circle'
    };
    
    notificationItem.innerHTML = `
        <div class="flex items-start space-x-3">
            <i class="ph-duotone ${iconMap[type] || iconMap.info} ${typeColors[type] || typeColors.info} text-lg mt-0.5"></i>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-white">${title}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">${message}</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">${new Date().toLocaleTimeString()}</p>
            </div>
        </div>
    `;
    
    if (link) {
        notificationItem.addEventListener('click', function() {
            window.location.href = link;
        });
    }
    
    // Add to top of list
    notificationList.insertBefore(notificationItem, notificationList.firstChild);
    
    // Show notification badge
    if (badge) {
        badge.classList.remove('hidden');
    }
    
    // Show clear button
    const clearBtn = document.getElementById('clearNotificationsBtn');
    if (clearBtn) {
        clearBtn.classList.remove('hidden');
    }
}

function clearNotifications() {
    const notificationList = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const clearBtn = document.getElementById('clearNotificationsBtn');
    
    if (notificationList) {
        notificationList.innerHTML = `
            <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-center">
                No new notifications
            </div>
        `;
    }
    
    if (badge) {
        badge.classList.add('hidden');
    }
    
    if (clearBtn) {
        clearBtn.classList.add('hidden');
    }
}

// Export to CSV
function exportToCsv(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            // Clean and escape data
            let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').replace(/(\s\s)/gm, ' ');
            data = data.replace(/"/g, '""');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Download CSV file
    const csvString = csv.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Toggle sidebar collapse
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('sidebar-collapsed');
    
    // Toggle logos
    document.querySelector('.sidebar-logo-full').classList.toggle('hidden');
    document.querySelector('.sidebar-logo-collapsed').classList.toggle('hidden');
    
    // Toggle sidebar text
    document.querySelectorAll('.sidebar-text').forEach(el => {
        el.classList.toggle('hidden');
    });
    
    // Store state in localStorage
    const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed);
}

// Initialize sidebar state
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        sidebar.classList.add('sidebar-collapsed');
        document.querySelector('.sidebar-logo-full').classList.add('hidden');
        document.querySelector('.sidebar-logo-collapsed').classList.remove('hidden');
        document.querySelectorAll('.sidebar-text').forEach(el => {
            el.classList.add('hidden');
        });
    }
});