import { initRichEditors } from './rich-editor';
import { initPerformanceUX } from './performance';
import { initErrorFeedback } from './error-feedback';
import './bootstrap';

/**
 * Most AcadFlow screens are Blade-rendered. Vue and its component bundle are
 * loaded only when a page actually renders a #app Vue mount point. This keeps
 * normal course/knowledge/community navigation lightweight.
 */
async function initVueRoot() {
    const vueRoot = document.getElementById('app');
    if (!vueRoot) return;

    const [{ createApp }, { default: NotificationCenter }, { default: FileUpload }] = await Promise.all([
        import('vue'),
        import('./components/NotificationCenter.vue'),
        import('./components/FileUpload.vue'),
    ]);

    const app = createApp({ components: { NotificationCenter, FileUpload } });
    app.component('notification-center', NotificationCenter);
    app.component('file-upload', FileUpload);
    app.mount(vueRoot);
}

function boot() {
    initErrorFeedback();
    initPerformanceUX();
    initRichEditors();

    if (document.querySelector('[data-password-policy]')) {
        import('./password-policy')
            .then(({ initPasswordPolicies }) => initPasswordPolicies())
            .catch((error) => console.error('AcadFlow password policy UI failed to initialize:', error));
    }

    if (document.querySelector('[data-rate-limit-countdown]')) {
        import('./rate-limit-feedback')
            .then(({ initRateLimitFeedback }) => initRateLimitFeedback())
            .catch((error) => console.error('AcadFlow rate-limit feedback failed to initialize:', error));
    }

    initVueRoot().catch((error) => console.error('AcadFlow Vue initialization failed:', error));

    if (window.OfflineSync) {
        window.OfflineSync.init();
        window.OfflineSync.setupListeners();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
