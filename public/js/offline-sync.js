/**
 * UniFlow Offline Sync Manager
 * Handles offline actions, IndexedDB storage, and background sync
 */

class OfflineSyncManager {
    constructor() {
        this.dbName = 'UniFlowDB';
        this.dbVersion = 1;
        this.storeName = 'offline_queue';
        this.db = null;
        this.syncEventTag = 'uniflow-sync';
    }

    /**
     * Initialize IndexedDB
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = (event) => reject(event.target.error);
            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const store = db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('type', 'type', { unique: false });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('synced', 'synced', { unique: false });
                }
            };
        });
    }

    /**
     * Add action to offline queue
     */
    async queueAction(action, data) {
        await this.init();
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const record = {
                type: action,
                data: data,
                timestamp: Date.now(),
                synced: false,
                retry_count: 0,
            };
            const request = store.add(record);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending actions
     */
    async getPendingActions() {
        await this.init();
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const index = store.index('synced');
            const request = index.getAll(false);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Mark action as synced
     */
    async markAsSynced(id) {
        await this.init();
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(id);

            request.onsuccess = () => {
                const data = request.result;
                if (data) {
                    data.synced = true;
                    data.synced_at = Date.now();
                    store.put(data);
                }
                resolve();
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Clear synced actions
     */
    async clearSynced() {
        await this.init();
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const index = store.index('synced');
            const request = index.openCursor(true); // true = only synced=true

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    cursor.delete();
                    cursor.continue();
                } else {
                    resolve();
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Process offline queue (called by service worker on sync)
     */
    async processQueue() {
        const pending = await this.getPendingActions();
        for (const action of pending) {
            try {
                await this.syncAction(action);
                await this.markAsSynced(action.id);
            } catch (error) {
                console.error('Failed to sync action:', action.type, error);
                // Increment retry count, will try again later
                await this.incrementRetry(action.id);
            }
        }
        await this.clearSynced();
    }

    /**
     * Sync a single action to server
     */
    async syncAction(action) {
        const { type, data } = action;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;

        const response = await fetch(this.getEndpointForAction(type, data), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify(data),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        return response.json();
    }

    /**
     * Get API endpoint for action type
     */
    getEndpointForAction(type, data) {
        const endpoints = {
            'submission_create': `/courses/${data.course_id}/submissions`,
            'submission_update': `/submissions/${data.submission_id}`,
            'reply_create': `/courses/${data.course_id}/discussions/${data.discussion_id}/reply`,
            'discussion_create': `/courses/${data.course_id}/discussions`,
            'material_download': null, // Downloads don't need sync
        };

        return endpoints[type] || null;
    }

    /**
     * Increment retry count for failed action
     */
    async incrementRetry(id) {
        await this.init();
        return new Promise((resolve) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.get(id);

            request.onsuccess = () => {
                const data = request.result;
                if (data) {
                    data.retry_count = (data.retry_count || 0) + 1;
                    // If too many retries, delete it
                    if (data.retry_count > 5) {
                        store.delete(id);
                    } else {
                        store.put(data);
                    }
                }
                resolve();
            };
        });
    }

    /**
     * Check if we are online
     */
    isOnline() {
        return navigator.onLine;
    }

    /**
     * Setup online/offline listeners
     */
    setupListeners() {
        window.addEventListener('online', () => {
            console.log('Back online, processing queue...');
            this.processQueue();
        });

        window.addEventListener('offline', () => {
            console.log('Connection lost. Actions will be queued.');
        });
    }

    /**
     * Request background sync
     */
    async requestBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
            const registration = await navigator.serviceWorker.ready;
            try {
                await registration.sync.register(this.syncEventTag);
                console.log('Background sync registered');
            } catch (error) {
                console.log('Background sync registration failed:', error);
            }
        } else {
            // Fallback: process immediately if online
            if (this.isOnline()) {
                await this.processQueue();
            }
        }
    }
}

// Initialize
const offlineSync = new OfflineSyncManager();
offlineSync.init();
offlineSync.setupListeners();

// Export for use in other scripts
window.OfflineSync = offlineSync;
