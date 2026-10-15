/**
 * UniFlow Main JavaScript
 * Handles offline sync, PWA install prompts, and UI enhancements
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize offline sync manager
    if (window.OfflineSync) {
        window.OfflineSync.init();
        window.OfflineSync.setupListeners();
    }

    // Intercept form submissions for offline queue
    const forms = document.querySelectorAll('form[data-offline="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', handleOfflineSubmit);
    });

    // Add CSRF token to all AJAX requests
    setupCsrfToken();

    // Initialize PWA install prompt
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        showInstallButton();
    });
});

/**
 * Handle offline form submissions
 */
async function handleOfflineSubmit(event) {
    const form = event.target;
    
    // If online, submit normally
    if (navigator.onLine) {
        return true;
    }

    // Offline: prevent default and queue action
    event.preventDefault();
    
    const formData = new FormData(form);
    const action = form.getAttribute('data-action-type');
    const url = form.action;
    
    // Extract endpoint info from action
    const pathname = new URL(url, window.location.origin).pathname;
    
    // Build data object
    const data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });

    // Queue based on action type
    if (window.OfflineSync) {
        await window.OfflineSync.queueAction(action, {
            ...data,
            pathname: pathname,
            method: form.method,
        });
        
        // Optionally sync in background
        window.OfflineSync.requestBackgroundSync();
    }

    showOfflineNotification('Action queued. Will sync when you are back online.');
    return false;
}

/**
 * Show offline queued notification
 */
function showOfflineNotification(message) {
    const existing = document.querySelector('.offline-notification');
    if (existing) {
        existing.remove();
    }

    const notification = document.createElement('div');
    notification.className = 'offline-notification fixed bottom-4 right-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded shadow-lg z-50';
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <span class="text-sm font-medium">${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 4000);
}

/**
 * Setup CSRF token for AJAX
 */
function setupCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (token) {
        // Laravel default setup already handles this for Axios/fetch
        window.csrfToken = token;
    }
}

/**
 * Show install PWA button
 */
function showInstallButton() {
    // This could show a custom install button
    console.log('PWA is installable');
    // Dispatch custom event that UI can listen for
    window.dispatchEvent(new CustomEvent('pwa-install-available', { detail: deferredPrompt }));
}

/**
 * Install PWA
 */
async function installPWA() {
    if (!deferredPrompt) return;

    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    if (outcome === 'accepted') {
        console.log('PWA installed');
    }
    deferredPrompt = null;
}

// Export functions
window.installPWA = installPWA;
window.showOfflineNotification = showOfflineNotification;
