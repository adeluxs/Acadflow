/**
 * UniFlow PWA Service Worker
 * Handles caching, offline support, and background sync
 */

const CACHE_NAME = 'uniflow-v1.0.0';
const STATIC_CACHE = 'uniflow-static-v1.0.0';
const DYNAMIC_CACHE = 'uniflow-dynamic-v1.0.0';
const API_CACHE = 'uniflow-api-v1.0.0';

// Assets to cache on install
const STATIC_ASSETS = [
    '/',
    '/dashboard',
    '/manifest.webmanifest',
    '/css/app.css',
    '/js/app.js',
    '/icons/icon-192x192.png',
    '/icons/icon-512x512.png',
    '/offline.html',
];

// API endpoints to cache
const CACHEABLE_API_PATTERNS = [
    /\/api\/courses/,
    /\/api\/submissions/,
    /\/api\/materials/,
    /\/api\/notifications/,
    /\/api\/dashboard/,
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing service worker...');
    
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            }),
            caches.open(DYNAMIC_CACHE),
            caches.open(API_CACHE),
        ]).then(() => {
            console.log('[SW] Installation complete');
            return self.skipWaiting();
        })
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating service worker...');
    
    const validCaches = [STATIC_CACHE, DYNAMIC_CACHE, API_CACHE];
    
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (!validCaches.includes(cacheName)) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => {
            console.log('[SW] Activation complete');
            return self.clients.claim();
        })
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests for caching
    if (request.method !== 'GET') {
        // For POST/PUT/DELETE, try network first, queue if offline
        if (!navigator.onLine) {
            event.respondWith(
                queueOfflineAction(request).then(() => {
                    return new Response(JSON.stringify({
                        success: false,
                        message: 'Action queued for sync when online',
                        queued: true
                    }), {
                        status: 202,
                        headers: { 'Content-Type': 'application/json' }
                    });
                })
            );
        }
        return;
    }
    
    // Handle API requests
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(handleApiRequest(request));
        return;
    }
    
    // Handle navigation requests
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }
    
    // Handle static assets
    event.respondWith(handleStaticAsset(request));
});

// Handle API requests with cache-first for GET
async function handleApiRequest(request) {
    const url = new URL(request.url);
    const shouldCache = CACHEABLE_API_PATTERNS.some(pattern => pattern.test(url.pathname));
    
    if (shouldCache) {
        // Cache-first strategy for cachable APIs
        const cachedResponse = await caches.match(request);
        
        if (cachedResponse) {
            // Update cache in background
            fetchAndCache(request).catch(() => {});
            return cachedResponse;
        }
    }
    
    // Network-first for all API requests
    try {
        const response = await fetch(request);
        
        if (response.ok && shouldCache) {
            const clonedResponse = response.clone();
            const cache = await caches.open(API_CACHE);
            await cache.put(request, clonedResponse);
        }
        
        return response;
    } catch (error) {
        // Try cache as fallback
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline error for API requests
        return new Response(JSON.stringify({
            error: 'You are offline. Please check your connection.',
            offline: true
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Handle navigation requests
async function handleNavigationRequest(request) {
    try {
        const response = await fetch(request);
        
        // Cache successful HTML responses
        if (response.ok && response.headers.get('content-type')?.includes('text/html')) {
            const clonedResponse = response.clone();
            const cache = await caches.open(DYNAMIC_CACHE);
            await cache.put(request, clonedResponse);
        }
        
        return response;
    } catch (error) {
        // Try cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Fallback to offline page
        const offlineResponse = await caches.match('/offline.html');
        if (offlineResponse) {
            return offlineResponse;
        }
        
        // Ultimate fallback
        return new Response('You are offline. Please check your connection.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// Handle static assets
async function handleStaticAsset(request) {
    // Cache-first strategy for static assets
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        // Update cache in background
        fetchAndCache(request).catch(() => {});
        return cachedResponse;
    }
    
    // Not in cache, fetch from network
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const clonedResponse = response.clone();
            const cache = await caches.open(STATIC_CACHE);
            await cache.put(request, clonedResponse);
        }
        
        return response;
    } catch (error) {
        // For images, return a placeholder
        if (request.destination === 'image') {
            return caches.match('/icons/icon-192x192.png');
        }
        
        return new Response('Asset not available offline', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// Fetch and cache helper
async function fetchAndCache(request) {
    try {
        const response = await fetch(request);
        
        if (response.ok) {
            const clonedResponse = response.clone();
            const cache = await caches.open(STATIC_CACHE);
            await cache.put(request, clonedResponse);
        }
        
        return response;
    } catch (error) {
        console.log('[SW] Fetch failed for:', request.url);
        throw error;
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
