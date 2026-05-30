const CACHE_NAME = 'uniflow-v1';
const STATIC_ASSETS = [
    '/',
    '/dashboard',
    '/manifest.webmanifest',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
];

const DYNAMIC_CACHE = 'uniflow-dynamic-v1';
const OFFLINE_CACHE = 'uniflow-offline-v1';

// IndexedDB for offline data
const DB_NAME = 'uniflow-offline-db';
const DB_VERSION = 1;

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME && name !== DYNAMIC_CACHE && name !== OFFLINE_CACHE) {
                        return caches.delete(name);
                    }
                })
            ))
            .then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle same-origin requests
    if (url.origin === location.origin) {
        // For course materials (PDFs, documents) - cache-first
        if (request.url.includes('/course-materials/') || request.url.includes('/storage/')) {
            event.respondWith(
                caches.match(request)
                    .then((cached) => {
                        if (cached) {
                            return cached;
                        }
                        return fetch(request)
                            .then((response) => {
                                if (response.status === 200) {
                                    const responseClone = response.clone();
                                    caches.open(DYNAMIC_CACHE)
                                        .then((cache) => cache.put(request, responseClone));
                                }
                                return response;
                            });
                    })
            );
        } 
        // For API calls - network-first with offline fallback
        else if (request.url.includes('/api/')) {
            event.respondWith(
                fetch(request)
                    .then((response) => {
                        if (response.status === 200) {
                            const responseClone = response.clone();
                            caches.open(DYNAMIC_CACHE)
                                .then((cache) => cache.put(request, responseClone));
                        }
                        return response;
                    })
                    .catch(() => caches.match(request))
            );
        }
        // For HTML pages - network-first, fallback to cache
        else {
            event.respondWith(
                fetch(request)
                    .then((response) => {
                        if (response.status === 200) {
                            const responseClone = response.clone();
                            caches.open(DYNAMIC_CACHE)
                                .then((cache) => cache.put(request, responseClone));
                        }
                        return response;
                    })
                    .catch(() => caches.match(request))
            );
        }
    } else {
        // For cross-origin requests, just fetch (no offline support)
        event.respondWith(fetch(request));
    }
});

// Background sync for offline submissions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-submissions') {
        event.waitUntil(syncPendingSubmissions());
    } else if (event.tag === 'sync-drafts') {
        event.waitUntil(syncPendingDrafts());
    }
});

// Sync pending submissions
async function syncPendingSubmissions() {
    try {
        const db = await openDB();
        const tx = db.transaction('pending_submissions', 'readonly');
        const store = tx.objectStore('pending_submissions');
        const submissions = await store.getAll();

        for (const submission of submissions) {
            try {
                const response = await fetch('/api/v1/submissions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${submission.token}`,
                    },
                    body: JSON.stringify(submission.data),
                });

                if (response.ok) {
                    // Remove from pending
                    const deleteTx = db.transaction('pending_submissions', 'readwrite');
                    await deleteTx.objectStore('pending_submissions').delete(submission.id);
                    
                    // Notify the app
                    self.clients.matchAll().then((clients) => {
                        clients.forEach((client) => {
                            client.postMessage({
                                type: 'SUBMISSION_SYNCED',
                                data: submission,
                            });
                        });
                    });
                }
            } catch (error) {
                console.error('Failed to sync submission:', error);
            }
        }
    } catch (error) {
        console.error('Sync failed:', error);
    }
}

// Sync pending drafts
async function syncPendingDrafts() {
    try {
        const db = await openDB();
        const tx = db.transaction('pending_drafts', 'readonly');
        const store = tx.objectStore('pending_drafts');
        const drafts = await store.getAll();

        for (const draft of drafts) {
            try {
                const response = await fetch(`/api/v1/submissions/${draft.submission_id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${draft.token}`,
                    },
                    body: JSON.stringify(draft.data),
                });

                if (response.ok) {
                    const deleteTx = db.transaction('pending_drafts', 'readwrite');
                    await deleteTx.objectStore('pending_drafts').delete(draft.id);
                }
            } catch (error) {
                console.error('Failed to sync draft:', error);
            }
        }
    } catch (error) {
        console.error('Draft sync failed:', error);
    }
}

// Open IndexedDB
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // Create object stores if they don't exist
            if (!db.objectStoreNames.contains('pending_submissions')) {
                db.createObjectStore('pending_submissions', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('pending_drafts')) {
                db.createObjectStore('pending_drafts', { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('cached_pages')) {
                db.createObjectStore('cached_pages', { keyPath: 'url' });
            }
            if (!db.objectStoreNames.contains('sync_queue')) {
                db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
            }
        };
    });
}

// Message handler for communication with the app
self.addEventListener('message', (event) => {
    if (event.data && event.data.type) {
        switch (event.data.type) {
            case 'CACHE_PAGE':
                cachePage(event.data.url, event.data.content);
                break;
            case 'GET_OFFLINE_STATUS':
                getOfflineStatus(event.ports[0]);
                break;
            case 'QUEUE_FOR_SYNC':
                queueForSync(event.data.type, event.data.data);
                break;
        }
    }
});

// Cache a page for offline viewing
async function cachePage(url, content) {
    try {
        const db = await openDB();
        const tx = db.transaction('cached_pages', 'readwrite');
        tx.objectStore('cached_pages').put({
            url,
            content,
            cachedAt: new Date().toISOString(),
        });
    } catch (error) {
        console.error('Failed to cache page:', error);
    }
}

// Get offline status
function getOfflineStatus(port) {
    port.postMessage({
        online: navigator.onLine,
        lastSync: localStorage.getItem('lastSyncTime') || null,
    });
}

// Queue data for sync when back online
async function queueForSync(type, data) {
    try {
        const db = await openDB();
        const tx = db.transaction('sync_queue', 'readwrite');
        tx.objectStore('sync_queue').add({
            type,
            data,
            createdAt: new Date().toISOString(),
            retries: 0,
        });

        // Try to sync immediately if online
        if (navigator.onLine) {
            self.dispatchEvent(new SyncEvent('sync', { tag: `sync-${type}` }));
        }
    } catch (error) {
        console.error('Failed to queue for sync:', error);
    }
}

// Push notification handler
self.addEventListener('push', (event) => {
    if (event.data) {
        const data = event.data.json();
        
        const options = {
            body: data.body || 'New notification',
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-72x72.png',
            vibrate: [100, 50, 100],
            data: {
                url: data.url || '/dashboard',
                dateOfArrival: Date.now(),
                primaryKey: data.id || '1',
            },
            actions: [
                { action: 'view', title: 'View' },
                { action: 'dismiss', title: 'Dismiss' },
            ],
        };

        event.waitUntil(
            self.registration.showNotification(data.title || 'UniAcademic', options)
        );
    }
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'view' || !event.action) {
        const urlToOpen = event.notification.data?.url || '/dashboard';

        event.waitUntil(
            clients.matchAll({ type: 'window' })
                .then((clientList) => {
                    // If a window is already open, focus it
                    for (const client of clientList) {
                        if (client.url === urlToOpen && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    // Otherwise open a new window
                    if (clients.openWindow) {
                        return clients.openWindow(urlToOpen);
                    }
                })
        );
    }
});

// Background sync for offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'uniflow-sync') {
        event.waitUntil(syncOfflineActions());
    }
    if (event.tag === 'sync-notifications') {
        event.waitUntil(syncNotifications());
    }
});

async function syncOfflineActions() {
    // Open IndexedDB and process queue
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('UniFlowDB', 1);

        request.onerror = () => reject(request.error);

        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['offline_queue'], 'readwrite');
            const store = transaction.objectStore('offline_queue');
            const index = store.index('synced');
            const pendingRequest = index.getAll(false);

            pendingRequest.onsuccess = async () => {
                const pending = pendingRequest.result;
                for (const action of pending) {
                    try {
                        await syncActionToServer(action);
                        action.synced = true;
                        action.synced_at = Date.now();
                        store.put(action);
                    } catch (error) {
                        console.error('Failed to sync action:', action.type, error);
                        action.retry_count = (action.retry_count || 0) + 1;
                        if (action.retry_count > 5) {
                            store.delete(action.id);
                        } else {
                            store.put(action);
                        }
                    }
                }
                // Clean up synced records
                const cleanupRequest = index.openCursor(true);
                cleanupRequest.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        cursor.delete();
                        cursor.continue();
                    } else {
                        resolve();
                    }
                };
            };
        };

        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('offline_queue')) {
                const store = db.createObjectStore('offline_queue', { keyPath: 'id', autoIncrement: true });
                store.createIndex('type', 'type', { unique: false });
                store.createIndex('timestamp', 'timestamp', { unique: false });
                store.createIndex('synced', 'synced', { unique: false });
            }
        };
    });
}

async function syncActionToServer(action) {
    const { type, data } = action;
    const token = await getCsrfToken();

    const response = await fetch(getEndpointForAction(type, data), {
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

function getEndpointForAction(type, data) {
    const endpoints = {
        submission_create: `/courses/${data.course_id}/submissions`,
        submission_update: `/submissions/${data.submission_id}`,
        reply_create: `/courses/${data.course_id}/discussions/${data.discussion_id}/reply`,
        discussion_create: `/courses/${data.course_id}/discussions`,
    };
    return endpoints[type];
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// Push notifications
self.addEventListener('push', (event) => {
    const data = event.data?.json() || {};
    const options = {
        body: data.body || 'New notification',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: data.data || {},
        actions: [
            { action: 'view', title: 'View' },
            { action: 'close', title: 'Close' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'UniFlow', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'view' || !event.action) {
        const url = event.notification.data.url || '/dashboard';
        event.waitUntil(
            clients.matchAll({ type: 'window' })
                .then((clientList) => {
                    for (const client of clientList) {
                        if (client.url === url && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    return clients.openWindow(url);
                })
        );
    }
});
