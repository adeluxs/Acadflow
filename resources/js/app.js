import { initRichEditors } from './rich-editor';
import './bootstrap';
import { createApp } from 'vue';

// Import Vue components
import NotificationCenter from './components/NotificationCenter.vue';
import FileUpload from './components/FileUpload.vue';

// Create and mount Vue app
const app = createApp({
    components: {
        NotificationCenter,
        FileUpload
    }
});

// Register global components
app.component('notification-center', NotificationCenter);
app.component('file-upload', FileUpload);

// Mount only when a Vue root exists. Most Blade pages are server-rendered.
const vueRoot = document.getElementById('app');
if (vueRoot) {
    app.mount(vueRoot);
}

// Initialize offline sync if available
if (window.OfflineSync) {
    window.OfflineSync.init();
    window.OfflineSync.setupListeners();
}

if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initRichEditors); } else { initRichEditors(); }
