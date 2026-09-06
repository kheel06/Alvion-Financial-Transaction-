/**
 * Alert Modal System
 * Reusable alert/confirmation modal for the entire system
 * Uses Flowbite modal structure
 */

// Alert modal configuration
const alertModalConfig = {
    success: {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        iconColor: 'text-green-600 dark:text-green-400',
        iconBg: 'bg-green-100 dark:bg-green-900/20',
        titleColor: 'text-green-800 dark:text-green-200',
        buttonColor: 'bg-green-600 hover:bg-green-700 focus:ring-green-500'
    },
    error: {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
        iconColor: 'text-red-600 dark:text-red-400',
        iconBg: 'bg-red-100 dark:bg-red-900/20',
        titleColor: 'text-red-800 dark:text-red-200',
        buttonColor: 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
    },
    warning: {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        iconColor: 'text-yellow-600 dark:text-yellow-400',
        iconBg: 'bg-yellow-100 dark:bg-yellow-900/20',
        titleColor: 'text-yellow-800 dark:text-yellow-200',
        buttonColor: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'
    },
    info: {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
        iconColor: 'text-blue-600 dark:text-blue-400',
        iconBg: 'bg-blue-100 dark:bg-blue-900/20',
        titleColor: 'text-blue-800 dark:text-blue-200',
        buttonColor: 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
    },
    confirm: {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
        iconColor: 'text-gray-600 dark:text-gray-400',
        iconBg: 'bg-gray-100 dark:bg-gray-800',
        titleColor: 'text-gray-800 dark:text-gray-200',
        buttonColor: 'bg-primary-600 hover:bg-primary-700 focus:ring-primary-500'
    }
};

let currentResolve = null;
let currentReject = null;

/**
 * Show an alert modal
 * @param {string} type - Type of alert: 'success', 'error', 'warning', 'info', 'confirm'
 * @param {string} title - Title of the alert
 * @param {string} message - Message content
 * @param {Object} options - Additional options
 * @returns {Promise} - Resolves when user clicks OK/Confirm, rejects on Cancel
 */
function showAlert(type, title, message, options = {}) {
    return new Promise((resolve, reject) => {
        const modal = document.getElementById('alertModal');
        if (!modal) {
            console.error('Alert modal not found in DOM');
            reject(new Error('Alert modal not found'));
            return;
        }

        const config = alertModalConfig[type] || alertModalConfig.info;
        const isConfirm = type === 'confirm';
        
        // Get modal elements
        const modalIcon = document.getElementById('alertModalIcon');
        const modalTitle = document.getElementById('alertModalTitle');
        const modalMessage = document.getElementById('alertModalMessage');
        const modalOkBtn = document.getElementById('alertModalOkBtn');
        const modalCancelBtn = document.getElementById('alertModalCancelBtn');
        const modalFooter = document.getElementById('alertModalFooter');

        // Set icon
        if (modalIcon) {
            modalIcon.className = `mx-auto flex items-center justify-center h-12 w-12 rounded-full ${config.iconBg}`;
            modalIcon.innerHTML = `<div class="${config.iconColor}">${config.icon}</div>`;
        }

        // Set title
        if (modalTitle) {
            modalTitle.className = `text-lg font-semibold ${config.titleColor}`;
            modalTitle.textContent = title;
        }

        // Set message
        if (modalMessage) {
            modalMessage.textContent = message;
        }

        // Configure buttons
        if (modalOkBtn) {
            modalOkBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg focus:ring-4 focus:outline-none ${config.buttonColor}`;
            modalOkBtn.textContent = options.okText || (isConfirm ? 'Confirm' : 'OK');
        }

        // Show/hide cancel button for confirm dialogs
        if (modalCancelBtn && modalFooter) {
            if (isConfirm) {
                modalCancelBtn.style.display = 'inline-flex';
                modalCancelBtn.textContent = options.cancelText || 'Cancel';
            } else {
                modalCancelBtn.style.display = 'none';
            }
        }

        // Store resolve/reject
        currentResolve = resolve;
        currentReject = reject;

        // Show modal using Flowbite or manual toggle
        try {
            // Try Flowbite Modal API
            if (typeof FlowbiteModal !== 'undefined') {
                const modalInstance = new FlowbiteModal(modal);
                modalInstance.show();
            } else {
                // Fallback: manual show
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
            }
        } catch (e) {
            // Fallback: manual show
            modal.classList.remove('hidden');
            modal.setAttribute('aria-hidden', 'false');
        }

        // Handle OK/Confirm button
        if (modalOkBtn) {
            const handleOk = () => {
                // Hide modal
                try {
                    if (typeof FlowbiteModal !== 'undefined') {
                        const modalInstance = new FlowbiteModal(modal);
                        modalInstance.hide();
                    } else {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                    }
                } catch (e) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
                
                if (currentResolve) {
                    currentResolve(true);
                    currentResolve = null;
                    currentReject = null;
                }
                modalOkBtn.removeEventListener('click', handleOk);
            };
            modalOkBtn.addEventListener('click', handleOk);
        }

        // Handle Cancel button
        if (modalCancelBtn && isConfirm) {
            const handleCancel = () => {
                // Hide modal
                try {
                    if (typeof FlowbiteModal !== 'undefined') {
                        const modalInstance = new FlowbiteModal(modal);
                        modalInstance.hide();
                    } else {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                    }
                } catch (e) {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                }
                
                if (currentReject) {
                    currentReject(false);
                    currentResolve = null;
                    currentReject = null;
                }
                modalCancelBtn.removeEventListener('click', handleCancel);
            };
            modalCancelBtn.addEventListener('click', handleCancel);
        }

        // Handle backdrop click (only for non-confirm dialogs)
        if (!isConfirm) {
            const handleBackdrop = (e) => {
                if (e.target === modal || e.target.classList.contains('bg-gray-500')) {
                    try {
                        if (typeof FlowbiteModal !== 'undefined') {
                            const modalInstance = new FlowbiteModal(modal);
                            modalInstance.hide();
                        } else {
                            modal.classList.add('hidden');
                            modal.setAttribute('aria-hidden', 'true');
                        }
                    } catch (e) {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                    }
                    
                    if (currentResolve) {
                        currentResolve(true);
                        currentResolve = null;
                        currentReject = null;
                    }
                    modal.removeEventListener('click', handleBackdrop);
                }
            };
            modal.addEventListener('click', handleBackdrop);
        }
    });
}

/**
 * Show success alert
 */
function showSuccessAlert(title, message, options) {
    return showAlert('success', title, message, options);
}

/**
 * Show error alert
 */
function showErrorAlert(title, message, options) {
    return showAlert('error', title, message, options);
}

/**
 * Show warning alert
 */
function showWarningAlert(title, message, options) {
    return showAlert('warning', title, message, options);
}

/**
 * Show info alert
 */
function showInfoAlert(title, message, options) {
    return showAlert('info', title, message, options);
}

/**
 * Show confirmation dialog
 */
function showConfirmAlert(title, message, options) {
    return showAlert('confirm', title, message, options);
}

// Export functions globally
window.showAlert = showAlert;
window.showSuccessAlert = showSuccessAlert;
window.showErrorAlert = showErrorAlert;
window.showWarningAlert = showWarningAlert;
window.showInfoAlert = showInfoAlert;
window.showConfirmAlert = showConfirmAlert;

// Shorthand aliases
window.alertSuccess = showSuccessAlert;
window.alertError = showErrorAlert;
window.alertWarning = showWarningAlert;
window.alertInfo = showInfoAlert;
window.alertConfirm = showConfirmAlert;

