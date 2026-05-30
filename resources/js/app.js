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

// Mount the app
app.mount('#app');

// Initialize offline sync if available
if (window.OfflineSync) {
    window.OfflineSync.init();
    window.OfflineSync.setupListeners();
}
