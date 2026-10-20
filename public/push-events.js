/* Shared by the PWA worker and the standalone browser notification worker. */
function notificationTarget(value) {
    try {
        const url = new URL(value || '/notifications', self.location.origin);
        return url.origin === self.location.origin && ['http:', 'https:'].includes(url.protocol)
            ? url.href : new URL('/notifications', self.location.origin).href;
    } catch (_) { return new URL('/notifications', self.location.origin).href; }
}
self.addEventListener('push', (event) => {
    let payload = {};
    try { payload = event.data?.json() || {}; } catch (_) { payload = { body: event.data?.text() || '' }; }
    const content = payload.notification || payload;
    event.waitUntil(self.registration.showNotification(content.title || 'AcadSyntra', {
        body: content.body || 'You have a new notification.',
        icon: '/branding/acadsyntra-icon-192.png', badge: '/branding/acadsyntra-mark.png',
        tag: content.tag || 'acadflow-notification',
        data: { url: notificationTarget(content.data?.url) },
    }));
});
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = notificationTarget(event.notification.data?.url);
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of windows) {
            if (new URL(client.url).origin !== self.location.origin) continue;
            if (client.url !== target && 'navigate' in client) await client.navigate(target);
            return client.focus();
        }
        return self.clients.openWindow(target);
    })());
});
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        windows.forEach((client) => client.postMessage({ type: 'PUSH_SUBSCRIPTION_CHANGED' }));
    }));
});
