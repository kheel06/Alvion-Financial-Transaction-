// Toast notifications
function showToast(type, message, duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-notification max-w-xs sm:max-w-sm w-full transform transition-all duration-300 ease-out translate-y-4 opacity-0`;

    const variants = {
        success: {
            label: 'Success',
            accent: '#16A34A',
            background: '#ECFDF5',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m9 12 2 2 4-4"></path></svg>'
        },
        warning: {
            label: 'Warning',
            accent: '#F59E0B',
            background: '#FFF7ED',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path></svg>'
        },
        info: {
            label: 'Info',
            background: '#EEF2FF',
            accent: '#2563EB',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path></svg>'
        },
        error: {
            label: 'Error',
            accent: '#DC2626',
            background: '#FEF2F2',
            icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>'
        }
    };

    const variant = variants[type] || variants.info;
    let description = '';
    let title = variant.label;

    // Handle different message formats
    if (typeof message === 'string') {
        // Check if it's a welcome message pattern
        if (message.includes('Welcome back!') || message.includes('🎉')) {
            // Special handling for welcome messages
            if (message.includes('::')) {
                const [msgTitle, msgDesc] = message.split('::');
                title = msgTitle.trim() || 'Welcome back! 🎉';
                description = msgDesc?.trim() || '';
            } else {
                // Extract name from welcome message
                const welcomeMatch = message.match(/Welcome back! 🎉\s*(.+)/);
                if (welcomeMatch) {
                    title = 'Welcome back! 🎉';
                    description = welcomeMatch[1].trim();
                } else {
                    // Fallback: use entire message as description
                    description = message;
                    title = variant.label;
                }
            }
        } else if (message.includes('::')) {
            // Regular :: separator
            const [msgTitle, msgDesc] = message.split('::');
            title = msgTitle.trim() || variant.label;
            description = msgDesc?.trim() || '';
        } else if (message.includes('\n')) {
            // Newline separator
            const [msgTitle, ...rest] = message.split('\n');
            if (msgTitle.trim()) {
                title = msgTitle.trim();
                description = rest.join('\n').trim();
            }
        } else {
            // Single string message
            description = message;
            title = variant.label;
        }
    } else if (typeof message === 'object' && message) {
        title = message.title || variant.label;
        description = message.description || '';
    }

    // Special case for welcome messages - ensure they have proper formatting
    if (title.includes('Welcome') || title.includes('🎉')) {
        // Use success styling for welcome messages
        const welcomeVariant = variants.success;
        toast.innerHTML = `
            <div class="flex items-stretch rounded-2xl shadow-lg ring-1 ring-black/5 overflow-hidden" style="background:${welcomeVariant.background};">
                <div class="w-1.5" style="background:${welcomeVariant.accent};"></div>
                <div class="flex-1 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="text-xl" style="color:${welcomeVariant.accent};">${welcomeVariant.icon}</div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold" style="color:${welcomeVariant.accent};">${title}</p>
                                ${description ? `<p class="text-sm text-slate-600 whitespace-pre-line">${description}</p>` : ''}
                            </div>
                        </div>
                        <button class="rounded-full p-1 text-slate-400 hover:text-slate-600 focus:outline-none close-toast" aria-label="Close" style="color:${welcomeVariant.accent};">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-lg"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        // Regular toast
        toast.innerHTML = `
            <div class="flex items-stretch rounded-2xl shadow-lg ring-1 ring-black/5 overflow-hidden" style="background:${variant.background};">
                <div class="w-1.5" style="background:${variant.accent};"></div>
                <div class="flex-1 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="text-xl" style="color:${variant.accent};">${variant.icon}</div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold" style="color:${variant.accent};">${title}</p>
                                ${description ? `<p class="text-sm text-slate-600 whitespace-pre-line">${description}</p>` : ''}
                            </div>
                        </div>
                        <button class="rounded-full p-1 text-slate-400 hover:text-slate-600 focus:outline-none close-toast" aria-label="Close" style="color:${variant.accent};">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-lg"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    container.appendChild(toast);

    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
        toast.classList.add('translate-y-0', 'opacity-100');
    }, 10);

    // Close button
    const closeButton = toast.querySelector('.close-toast');
    closeButton.addEventListener('click', () => {
        hideToast(toast);
    });

    // Auto hide
    if (duration > 0) {
        setTimeout(() => {
            hideToast(toast);
        }, duration);
    }

    return toast;
}

// Global toast function
window.showToast = showToast;

// Quick toast functions
window.showSuccess = (message, duration) => showToast('success', message, duration);
window.showError = (message, duration) => showToast('error', message, duration);
window.showWarning = (message, duration) => showToast('warning', message, duration);
window.showInfo = (message, duration) => showToast('info', message, duration);

// Hide and remove a toast with animation
function hideToast(toast) {
    if (!toast) return;

    // Animate out
    toast.classList.remove('translate-y-0', 'opacity-100');
    toast.classList.add('translate-y-4', 'opacity-0');

    // Remove from DOM after transition
    const transitionDurationMs = 300;
    setTimeout(() => {
        if (toast && toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, transitionDurationMs);
}