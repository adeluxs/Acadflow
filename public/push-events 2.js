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
            try {
                if (client.url !== target && 'navigate' in client) await client.navigate(target);
                return await client.focus();
            } catch (_) { /* Try another client or open a fresh application window. */ }
        }
        return self.clients.openWindow(target);
    })());
});
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        try {
            const response = await fetch('/browser-push/renew-context', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Sign in to reconnect notifications.');
            const context = await response.json();
            if (!context.available || !context.publicKey) return;
            const raw = atob(context.publicKey.replace(/-/g, '+').replace(/_/g, '/'));
            const key = Uint8Array.from(raw, char => char.charCodeAt(0));
            const subscription = event.newSubscription || await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            const saved = await fetch('/browser-push/subscribe', {
                method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': context.csrf_token }, body: JSON.stringify(subscription.toJSON()),
            });
            if (!saved.ok) throw new Error('Subscription renewal could not be saved.');
            if (event.oldSubscription && event.oldSubscription.endpoint !== subscription.endpoint) {
                await fetch('/browser-push/unsubscribe', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': context.csrf_token }, body: JSON.stringify({ endpoint: event.oldSubscription.endpoint }) });
            }
        } catch (_) {
            const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            windows.forEach(client => client.postMessage({ type: 'PUSH_SUBSCRIPTION_CHANGED' }));
        }
    })());
});
