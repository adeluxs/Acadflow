import { initPerformanceUX } from './performance';
import { initErrorFeedback } from './error-feedback';
import { initGlobalLoader, installAxiosLoader } from './global-loader';

/**
 * Most AcadSyntra screens are Blade-rendered. Vue and its component bundle are
 * loaded only when a page actually renders a #app Vue mount point. This keeps
 * normal course/knowledge/community navigation lightweight.
 */
async function initVueRoot() {
    const vueRoot = document.getElementById('app');
    if (!vueRoot) return;

    await import('./bootstrap');
    installAxiosLoader();
    initErrorFeedback();

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
    initGlobalLoader();
    initErrorFeedback();
    initPerformanceUX();
    if (document.querySelector('[data-admin-lookup]')) {
        import('./admin-lookups').then(({ initAdminLookups }) => initAdminLookups()).catch(() => {});
    }
    if (document.querySelector('[data-export-progress]')) {
        import('./research-export').then(({ initResearchExport }) => initResearchExport()).catch(() => {});
    }

    // Warm the small setup module on navigation intent without issuing an
    // authenticated HTML request or running an expensive controller in advance.
    let setupWarmed = false;
    const warmSetup = (event) => {
        const anchor = event.target.closest?.('a[href]');
        if (!anchor || setupWarmed || navigator.connection?.saveData) return;
        const url = new URL(anchor.href, location.href);
        if (url.origin !== location.origin || url.pathname !== '/research-studio/create') return;
        setupWarmed = true;
        import('./research-studio').catch(() => { setupWarmed = false; });
    };
    document.addEventListener('pointerover', warmSetup, { passive: true });
    document.addEventListener('focusin', warmSetup);

    if (document.querySelector('[data-contextual-ai]')) {
        import('./contextual-assistant')
            .then(({ initContextualAssistants }) => initContextualAssistants())
            .catch((error) => console.error('AcadSyntra contextual assistant failed to initialize:', error));
    }

    if (document.querySelector('[data-rich-editor]')) {
        import('./rich-editor')
            .then(({ initRichEditors }) => initRichEditors())
            .catch((error) => console.error('AcadSyntra rich editor failed to initialize:', error));
    }

    if (document.querySelector('[data-lecturer-ai]')) {
        // Load the rich editor first so window.AcadFlowRichEditor.insert exists
        // before the Lecturer AI Studio tries to insert a draft into the editor.
        import('./rich-editor')
            .then(() => import('./lecturer-ai'))
            .then(({ initLecturerAi }) => initLecturerAi())
            .catch((error) => console.error('AcadSyntra Lecturer AI Studio failed to initialize:', error));
    }

    if (document.querySelector('[data-password-policy]')) {
        import('./password-policy')
            .then(({ initPasswordPolicies }) => initPasswordPolicies())
            .catch((error) => console.error('AcadSyntra password policy UI failed to initialize:', error));
    }

    if (document.querySelector('[data-rate-limit-countdown]')) {
        import('./rate-limit-feedback')
            .then(({ initRateLimitFeedback }) => initRateLimitFeedback())
            .catch((error) => console.error('AcadSyntra rate-limit feedback failed to initialize:', error));
    }

    if (document.querySelector('meta[name="push-config-url"]')) {
        import('./browser-push').then(({ initBrowserPush }) => initBrowserPush()).catch(() => {});
    }

    initVueRoot().catch((error) => console.error('AcadSyntra Vue initialization failed:', error));

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

if (document.querySelector('[data-research-setup], [data-research-autosave], [data-writing-workspace]')) {
    import('./research-studio').catch(() => { /* Standard forms remain available if the module cannot load. */ });
}
