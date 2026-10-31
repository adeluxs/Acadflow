/**
 * AcadSyntra PWA Service Worker
 *
 * Performance/correctness rules:
 * - never cache authenticated HTML navigations;
 * - never cache private API responses unless explicitly marked public;
 * - use navigation preload so the service worker does not add a network round trip;
 * - cache immutable/static assets only;
 * - preserve offline write queue/background sync support.
 */

// Bump WORKER_VERSION whenever this file changes. Pages show the "updated"
// notice once per version and only switch workers when the user chooses to.
const WORKER_VERSION = '2026-10-31.1';
const CACHE_NAME = 'acadflow-pwa-v8-asset-integrity';
const STATIC_CACHE = 'acadflow-static-v8-asset-integrity';
const API_CACHE = 'acadflow-api-v8-asset-integrity';
const DEVELOPMENT_MODE = new URL(self.location.href).searchParams.get('dev') === '1';

// Only stable public files belong in the install cache. Vite assets are hashed
// and are cached lazily when requested; /css/app.css and /js/app.js are not
// stable paths in a Vite production build.
const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/branding/acadsyntra-icon-192.png',
    '/branding/acadsyntra-icon-512.png',
    '/branding/acadsyntra-mark.png',
    '/offline.html',
];

self.addEventListener('install', (event) => {
    // No skipWaiting() here. An updated worker waits until the user chooses
    // "Refresh now" (SKIP_WAITING message) or every AcadSyntra tab is closed,
    // so a deploy never swaps the worker underneath an open page or an
    // in-flight navigation.
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => Promise.all(STATIC_ASSETS.map(async (asset) => {
                try { await cache.add(asset); } catch (_) { /* optional asset */ }
            })))
    );
});

self.addEventListener('activate', (event) => {
    const validCaches = [CACHE_NAME, STATIC_CACHE, API_CACHE];
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.map((name) => /^acadflow-(?:pwa|static|api)-/.test(name) && !validCaches.includes(name) ? caches.delete(name) : null));
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
        // Auth/session/payment/AI writes must always go directly to Laravel. A
        // stale or over-eager service worker must never convert login/logout or
        // assistant requests into offline queue JSON shown in the browser.
        if (shouldBypassWriteQueue(url)) return;

        // Only explicitly offline-sync endpoints are queueable. Generic form
        // submissions should fail normally when offline so users do not see a
        // JSON queue payload on pages such as login.
        if (isQueueableOfflineWrite(url, request) && self.navigator?.onLine === false) {
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

    if (url.pathname.startsWith('/ai/')) return;

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

    // Development CSS/modules must reach the browser's normal network loader.
    // Do not cache Vite development URLs or authenticated media/download URLs.
    if (!DEVELOPMENT_MODE && ['script', 'style', 'image', 'font'].includes(request.destination)
        && /^(?:\/build\/assets\/|\/branding\/|\/css\/|\/js\/|\/fonts\/)/.test(url.pathname)
        && !request.headers.has('Range')) {
        const writes = [];
        const response = handleStaticAsset(request, writes);
        event.respondWith(response);
        event.waitUntil(response.then(() => Promise.all(writes)).catch(() => {}));
    }
});

function shouldBypassWriteQueue(url) {
    return url.pathname.startsWith('/ai/')
        || url.pathname === '/login'
        || url.pathname === '/logout'
        || url.pathname === '/register'
        || url.pathname.startsWith('/password')
        || url.pathname.startsWith('/forgot-password')
        || url.pathname.startsWith('/reset-password')
        || url.pathname.startsWith('/two-factor')
        || url.pathname.startsWith('/payment')
        || url.pathname.startsWith('/commerce')
        || url.pathname.startsWith('/billing');
}

function isQueueableOfflineWrite(url, request) {
    return request.headers.get('X-AcadFlow-Offline-Queue') === '1'
        || url.pathname.startsWith('/sync/')
        || url.pathname.startsWith('/offline/');
}

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

function validStaticResponse(request, response) {
    if (!response || response.status !== 200 || response.redirected) return false;
    const type = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
    if (request.destination === 'style') return type === 'text/css';
    if (request.destination === 'script') return /^(?:text|application)\/(?:javascript|ecmascript)$/.test(type);
    if (request.destination === 'image') return type.startsWith('image/');
    return type.startsWith('font/') || /^(?:application\/(?:font-woff|vnd\.ms-fontobject|octet-stream))$/.test(type);
}

function cacheableStaticResponse(request, response) {
    return validStaticResponse(request, response) && !/\b(?:private|no-store)\b/i.test(response.headers.get('Cache-Control') || '');
}

async function handleStaticAsset(request, writes) {
    let cache, cached;
    let invalidCachedResponse = false;
    try {
        cache = await caches.open(STATIC_CACHE);
        cached = await cache.match(request);
        if (cached && !cacheableStaticResponse(request, cached)) {
            invalidCachedResponse = true;
            cached = null;
            await cache.delete(request);
        }
    } catch (_) { /* Storage/quota failures must not prevent network styles. */ }

    const immutable = /^\/build\/assets\/.+-[A-Za-z0-9_-]{8,}\.[^/]+$/.test(new URL(request.url).pathname);
    const bypass = ['reload', 'no-cache', 'no-store'].includes(request.cache);
    if (request.cache === 'no-store') cached = null;
    if (cached && immutable && !bypass && !/\bno-cache\b/i.test(cached.headers.get('Cache-Control') || '')) return cached;

    try {
        // Unhashed assets can change at the same URL. Revalidate them instead
        // of keeping old CSS/mobile rules until a manual browser refresh.
        const options = !bypass && invalidCachedResponse ? { cache: 'reload' }
            : !immutable && !bypass ? { cache: 'no-cache' } : undefined;
        const response = await fetch(request, options);
        if (cacheableStaticResponse(request, response) && cache && request.cache !== 'no-store') {
            writes.push(cache.put(request, response.clone()).catch(() => {}));
        } else if (cache && /\b(?:private|no-store)\b/i.test(response.headers.get('Cache-Control') || '')) {
            writes.push(cache.delete(request).catch(() => {}));
        }
        if (!validStaticResponse(request, response) && cached) return cached;
        return response;
    } catch (_) {
        if (cached) return cached;
        if (request.destination === 'image') {
            return (await cache?.match('/branding/acadsyntra-icon-192.png')) || new Response('', { status: 503 });
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

    if (data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (data?.type === 'GET_VERSION') {
        event.ports?.[0]?.postMessage({ version: WORKER_VERSION });
    }

    if (data?.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(CACHE_NAME).then((cache) => {
                return cache.addAll(data.urls);
            })
        );
    }
});

console.log('[SW] Service worker loaded');

importScripts('/push-events.js');
