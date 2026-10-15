/**
 * AcadFlow PWA Sync Manager
 * Handles offline queue, sync, and state management
 */

class SyncManager {
    constructor() {
        this.dbName = 'UniFlowOfflineDB';
        this.dbVersion = 1;
        this.syncQueueName = 'syncQueue';
        this.offlineDataName = 'offlineData';
        this.db = null;
        this.isOnline = navigator.onLine;
        this.syncInProgress = false;
        this.listeners = [];
        
        this.init();
    }

    async init() {
        // Listen for online/offline events
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.notifyListeners('online', null);
            this.syncAll();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.notifyListeners('offline', null);
        });

        // Listen for service worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                const { data } = event;
                
                if (data.type === 'SYNC_SUCCESS') {
                    this.notifyListeners('sync-success', data.action);
                }
            });
        }

        // Open IndexedDB
        await this.openDB();
    }

    // Open IndexedDB
    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create sync queue store
                if (!db.objectStoreNames.contains(this.syncQueueName)) {
                    const syncStore = db.createObjectStore(this.syncQueueName, { keyPath: 'id' });
                    syncStore.createIndex('timestamp', 'timestamp', { unique: false });
                    syncStore.createIndex('url', 'url', { unique: false });
                }

                // Create offline data store
                if (!db.objectStoreNames.contains(this.offlineDataName)) {
                    const dataStore = db.createObjectStore(this.offlineDataName, { keyPath: 'key' });
                    dataStore.createIndex('expiry', 'expiry', { unique: false });
                    dataStore.createIndex('type', 'type', { unique: false });
                }
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    // Queue an action for sync when online
    async queueAction(url, method = 'POST', body = null, headers = {}) {
        if (!this.db) await this.openDB();

        const action = {
            id: this.generateId(),
            url: url,
            method: method,
            body: body ? JSON.stringify(body) : null,
            headers: headers,
            timestamp: Date.now(),
            retryCount: 0,
            maxRetries: 3,
            status: 'pending', // pending, synced, failed
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.syncQueueName], 'readwrite');
            const store = transaction.objectStore(this.syncQueueName);
            const request = store.add(action);

            request.onsuccess = () => {
                this.notifyListeners('action-queued', action);
                
                // If online, try to sync immediately
                if (this.isOnline) {
                    this.syncAction(action);
                }
                
                resolve(action);
            };

            request.onerror = () => reject(request.error);
        });
    }

    // Sync a single action
    async syncAction(action) {
        try {
            const response = await fetch(action.url, {
                method: action.method,
                headers: action.headers || {},
                body: action.body ? action.body : null,
            });

            if (response.ok) {
                action.status = 'synced';
                await this.removeFromQueue(action.id);
                this.notifyListeners('sync-success', action);
                return { success: true, action };
            } else if (response.status === 409) {
                // Conflict detected
                const conflictData = await response.json();
                action.status = 'conflict';
                action.conflictData = conflictData;
                await this.updateInQueue(action);
                this.notifyListeners('sync-conflict', { action, conflictData });
                return { success: false, conflict: true, action, conflictData };
            } else {
                throw new Error(`Sync failed: ${response.status}`);
            }
        } catch (error) {
            action.retryCount++;
            action.lastError = error.message;
            action.status = action.retryCount >= action.maxRetries ? 'failed' : 'pending';
            
            await this.updateInQueue(action);
            this.notifyListeners('sync-error', { action, error });
            
            return { success: false, action, error };
        }
    }

    // Sync all queued actions
    async syncAll() {
        if (!this.isOnline || this.syncInProgress) return;
        
        this.syncInProgress = true;
        this.notifyListeners('sync-started', null);

        try {
            const actions = await this.getQueuedActions();
            
            for (const action of actions) {
                if (action.status === 'pending') {
                    await this.syncAction(action);
                }
            }
        } catch (error) {
            console.error('Sync all failed:', error);
        } finally {
            this.syncInProgress = false;
            this.notifyListeners('sync-completed', null);
        }
    }

    // Get all queued actions
    getQueuedActions() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.syncQueueName], 'readonly');
            const store = transaction.objectStore(this.syncQueueName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    }

    // Remove action from queue
    async removeFromQueue(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.syncQueueName], 'readwrite');
            const store = transaction.objectStore(this.syncQueueName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    // Update action in queue
    async updateInQueue(action) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.syncQueueName], 'readwrite');
            const store = transaction.objectStore(this.syncQueueName);
            const request = store.put(action);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    // Store data offline
    async storeOfflineData(key, data, type = 'generic', expiryHours = 24) {
        if (!this.db) await this.openDB();

        const item = {
            key: key,
            data: data,
            type: type,
            timestamp: Date.now(),
            expiry: Date.now() + (expiryHours * 60 * 60 * 1000),
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.offlineDataName], 'readwrite');
            const store = transaction.objectStore(this.offlineDataName);
            const request = store.put(item);

            request.onsuccess = () => resolve(item);
            request.onerror = () => reject(request.error);
        });
    }

    // Retrieve offline data
    async getOfflineData(key) {
        if (!this.db) await this.openDB();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.offlineDataName], 'readonly');
            const store = transaction.objectStore(this.offlineDataName);
            const request = store.get(key);

            request.onsuccess = () => {
                const item = request.result;
                
                if (!item) {
                    resolve(null);
                    return;
                }

                // Check expiry
                if (item.expiry && Date.now() > item.expiry) {
                    this.removeOfflineData(key);
                    resolve(null);
                    return;
                }

                resolve(item.data);
            };

            request.onerror = () => reject(request.error);
        });
    }

    // Remove offline data
    async removeOfflineData(key) {
        if (!this.db) await this.openDB();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.offlineDataName], 'readwrite');
            const store = transaction.objectStore(this.offlineDataName);
            const request = store.delete(key);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    // Clear expired data
    async clearExpiredData() {
        if (!this.db) await this.openDB();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.offlineDataName], 'readwrite');
            const store = transaction.objectStore(this.offlineDataName);
            const index = store.index('expiry');
            const range = IDBKeyRange.upperBound(Date.now());
            const request = index.openCursor(range);

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

    // Get sync status
    async getSyncStatus() {
        const actions = await this.getQueuedActions();
        const pending = actions.filter(a => a.status === 'pending').length;
        const failed = actions.filter(a => a.status === 'failed').length;
        const synced = actions.filter(a => a.status === 'synced').length;

        return {
            online: this.isOnline,
            syncInProgress: this.syncInProgress,
            pending,
            failed,
            synced,
            total: actions.length,
        };
    }

    // Add event listener
    addListener(callback) {
        this.listeners.push(callback);
    }

    // Remove event listener
    removeListener(callback) {
        this.listeners = this.listeners.filter(l => l !== callback);
    }

    // Notify listeners
    notifyListeners(event, data) {
        this.listeners.forEach(callback => {
            try {
                callback(event, data);
            } catch (error) {
                console.error('Sync listener error:', error);
            }
        });
    }

    // Generate unique ID
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    // Helper: Submit assignment (handles offline)
    async submitAssignment(taskId, data) {
        const url = `/courses/${data.courseId}/assignments/${taskId}/submit`;
        
        if (!this.isOnline) {
            // Store draft offline
            await this.storeOfflineData(
                `draft_submission_${taskId}`,
                { taskId, data, timestamp: Date.now() },
                'submission_draft'
            );
            
            // Queue for sync
            await this.queueAction(url, 'POST', data);
            
            return { offline: true, message: 'Submission queued for sync' };
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });

            if (response.ok) {
                // Clear any saved draft
                await this.removeOfflineData(`draft_submission_${taskId}`);
                return { success: true, data: await response.json() };
            }
        } catch (error) {
            // Queue for sync
            await this.queueAction(url, 'POST', data);
            return { offline: true, message: 'Submission queued for sync' };
        }
    }

    // Helper: Save draft
    async saveDraft(type, id, data) {
        const key = `draft_${type}_${id}`;
        await this.storeOfflineData(key, data, 'draft', 72); // Keep drafts for 72 hours
        return { saved: true };
    }

    // Helper: Load draft
    async loadDraft(type, id) {
        const key = `draft_${type}_${id}`;
        return await this.getOfflineData(key);
    }
}

// Export singleton
window.syncManager = new SyncManager();
