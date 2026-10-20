// Browser notifications work independently of offline/PWA caching.
importScripts('/push-events.js');
self.addEventListener('install', (event) => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
