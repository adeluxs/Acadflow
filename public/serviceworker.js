/**
 * AcadFlow PWA Service Worker
 *
 * Performance/correctness rules:
 * - never cache authenticated HTML navigations;
 * - never cache private API responses unless explicitly marked public;
 * - use navigation preload so the service worker does not add a network round trip;
 * - cache immutable/static assets only;
 * - preserve offline write queue/background sync support.
 */

const CACHE_NAME = 'acadflow-pwa-v2';
const STATIC_CACHE = 'acadflow-static-v2';
const API_CACHE = 'acadflow-api-v2';

// Only stable public files belong in the install cache. Vite assets are hashed
// and are cached lazily when requested; /css/app.css and /js/app.js are not
// stable paths in a Vite production build.
const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/offline.html',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => Promise.all(STATIC_ASSETS.map(async (asset) => {
                try { await cache.add(asset); } catch (_) { /* optional asset */ }
            })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    const validCaches = [CACHE_NAME, STATIC_CACHE, API_CACHE];
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.map((name) => validCaches.includes(name) ? null : caches.delete(name)));
        if (self.registration.navigationPreload) {
            try { await self.registration.navigationPreload.enable(); } catch (_) {}
        }
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) return;

    // Let online writes go directly to Laravel. If the browser is offline, the
    // existing sync queue can accept the write for later replay.
    if (request.method !== 'GET') {
        if (self.navigator?.onLine === false) {
            event.respondWith(
                queueOfflineAction(request).then(() => new Response(JSON.stringify({
                    success: false,
                    message: 'Action queued for sync when online',
                    queued: true,
                }), { status: 202, headers: { 'Content-Type': 'application/json' } }))
            );
        }
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(event));
        return;
    }

    if (url.pathname.startsWith('/api/')) {
        // Dynamic/private API reads should not be served stale from a service
        // worker. Network is the source of truth; the API may opt into public
        // caching later with an explicit response contract.
        event.respondWith(fetch(request).catch(() => new Response(JSON.stringify({
            error: 'You are offline. Please check your connection.',
            offline: true,
        }), { status: 503, headers: { 'Content-Type': 'application/json' } })));
        return;
    }

    if (['script', 'style', 'image', 'font'].includes(request.destination)) {
        event.respondWith(handleStaticAsset(request));
    }
});

async function handleNavigationRequest(event) {
    try {
        // Navigation preload starts the request in parallel with service-worker
        // startup, removing the worker startup delay from normal page changes.
        const preloaded = await event.preloadResponse;
        if (preloaded) return preloaded;
        return await fetch(event.request);
    } catch (_) {
        return (await caches.match('/offline.html')) || new Response(
            'You are offline. Please check your connection.',
            { status: 503, headers: { 'Content-Type': 'text/plain' } }
        );
    }
}

async function handleStaticAsset(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone()).catch(() => {});
        }
        return response;
    } catch (_) {
        if (request.destination === 'image') {
            return (await caches.match('/icons/icon-192x192.png')) || new Response('', { status: 503 });
        }
        return new Response('Asset not available offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
    }
}

// Queue offline action for sync
async function queueOfflineAction(request) {
    const db = await openSyncDB();
    const action = {
        url: request.url,
        method: request.method,
        headers: Array.from(request.headers.entries()),
        body: await request.text(),
        timestamp: Date.now(),
        id: generateId(),
    };
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['syncQueue'], 'readwrite');
        const store = transaction.objectStore('syncQueue');
        const request2 = store.add(action);
        
        request2.onsuccess = () => resolve();
        request2.onerror = () => reject(request2.error);
    });
}

// Background sync event
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync triggered:', event.tag);
    
    if (event.tag === 'sync-queue') {
        event.waitUntil(syncQueuedActions());
    }
});

// Sync queued actions when online
async function syncQueuedActions() {
    if (!navigator.onLine) {
        console.log('[SW] Still offline, skipping sync');
        return;
    }
    
    const db = await openSyncDB();
    
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['syncQueue'], 'readonly');
        const store = transaction.objectStore('syncQueue');
        const request = store.getAll();
        
        request.onsuccess = async () => {
            const actions = request.result;
            console.log('[SW] Syncing', actions.length, 'queued actions');
            
            for (const action of actions) {
                try {
                    const response = await fetch(action.url, {
                        method: action.method,
                        headers: new Headers(action.headers),
                        body: action.method !== 'GET' ? action.body : null,
                    });
                    
                    if (response.ok) {
                        // Remove from queue
                        await removeFromQueue(db, action.id);
                        console.log('[SW] Synced action:', action.url);
                        
                        // Notify client
                        notifyClients({
                            type: 'SYNC_SUCCESS',
                            action: action,
                        });
                    } else {
                        console.error('[SW] Sync failed for:', action.url, response.status);
                    }
                } catch (error) {
                    console.error('[SW] Sync error for:', action.url, error);
                }
            }
            
            resolve();
        };
        
        request.onerror = () => reject(request.error);
    });
}

// Remove action from sync queue
async function removeFromQueue(db, id) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['syncQueue'], 'readwrite');
        const store = transaction.objectStore('syncQueue');
        const request = store.delete(id);
        
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
}

// Notify all clients
function notifyClients(message) {
    self.clients.matchAll().then((clients) => {
        clients.forEach((client) => {
            client.postMessage(message);
        });
    });
}

// Open IndexedDB for sync queue
function openSyncDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('UniFlowSyncDB', 1);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('syncQueue')) {
                const store = db.createObjectStore('syncQueue', { keyPath: 'id' });
                store.createIndex('timestamp', 'timestamp', { unique: false });
            }
            
            if (!db.objectStoreNames.contains('offlineData')) {
                const offlineStore = db.createObjectStore('offlineData', { keyPath: 'key' });
                offlineStore.createIndex('expiry', 'expiry', { unique: false });
            }
        };
        
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

// Generate unique ID
function generateId() {
    return Date.now().toString(36) + Math.random().toString(36).substr(2);
}

// Listen for messages from clients
self.addEventListener('message', (event) => {
    const { data } = event;
    
    if (data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(DYNAMIC_CACHE).then((cache) => {
                return cache.addAll(data.urls);
            })
        );
    }
});

console.log('[SW] Service worker loaded');
