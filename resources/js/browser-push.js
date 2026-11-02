export function decodeApplicationKey(value) {
    const raw = atob(value.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

export async function initBrowserPush() {
    const configUrl = document.querySelector('meta[name="push-config-url"]')?.content;
    if (!configUrl) return;
    const panel = document.querySelector('[data-browser-push]');
    const status = panel?.querySelector('[data-push-status]');
    const invitation = panel?.hasAttribute('data-push-invitation');
    const enable = panel?.querySelector('[data-push-enable]');
    const disable = panel?.querySelector('[data-push-disable]');
    const test = panel?.querySelector('[data-push-test]');
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    let busy = false;
    let config;
    let currentSubscription = null;
    const show = (message) => { if (status) status.textContent = message; };
    const api = async (url, data, timeout = 12000) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetch(url, {
                method: data === undefined ? 'GET' : 'POST', credentials: 'same-origin', signal: controller.signal,
                // Background housekeeping must never raise the full-screen loader.
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'X-AcadFlow-Loader': 'silent' },
                ...(data === undefined ? {} : { body: JSON.stringify(data) }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'The request could not be completed. Please try again.');
            return result;
        } finally { clearTimeout(timer); }
    };
    const registration = async () => {
        // Both AcadSyntra workers handle push. Re-registering a different script
        // at the same scope would replace the active worker on every page and
        // surface a false "new version" notice, so reuse whichever is active.
        const current = await navigator.serviceWorker.getRegistration('/');
        if (!current?.active) {
            await navigator.serviceWorker.register(document.querySelector('meta[name="push-worker-url"]').content, { scope: '/', updateViaCache: 'none' });
        }
        return Promise.race([navigator.serviceWorker.ready, new Promise((_, reject) => setTimeout(() => reject(new Error('Browser notifications are still starting. Try again shortly.')), 10000))]);
    };
    const existing = async () => {
        const result = (async () => (await navigator.serviceWorker.getRegistration('/'))?.pushManager.getSubscription())();
        currentSubscription = await Promise.race([result, new Promise(resolve => setTimeout(() => resolve(null), 2500))]);
        return currentSubscription;
    };
    const updateButtons = (subscription) => {
        if (enable) enable.disabled = busy || !supported || !config?.available || Notification.permission === 'denied';
        if (disable) disable.disabled = busy || !subscription;
        if (test) test.disabled = busy || !subscription || !config?.available;
    };
    const run = async (action) => {
        if (busy) return;
        busy = true;
        try { updateButtons(await existing()); await action(); }
        catch (error) { show(error.name === 'AbortError' ? 'The connection timed out. Please try again.' : error.message); }
        finally {
            busy = false;
            let subscription = null;
            try { subscription = await existing(); } catch (_) {}
            updateButtons(subscription);
        }
    };
    // Logout proceeds natively. The server removes this session's push endpoint;
    // browser cleanup must never delay sign-out or trigger a second submission.
    document.querySelectorAll('form[action]').forEach((form) => {
        if (new URL(form.action).pathname !== '/logout') return;
        form.addEventListener('submit', (event) => {
            if (event.defaultPrevented || !currentSubscription) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'push_endpoint';
            input.value = currentSubscription.endpoint;
            form.append(input);
            // A remembered endpoint also covers sessions enrolled before server tracking.
            void Promise.resolve().then(() => currentSubscription.unsubscribe()).catch(() => {});
        });
    });
    const savePermission = (permission, snooze = false) => api('/browser-push/permission', { permission, snooze }).catch(() => {});
    panel?.querySelector('[data-push-later]')?.addEventListener('click', () => {
        panel.hidden = true;
        void savePermission(Notification.permission, true);
    });
    if (invitation && (!supported || Notification.permission === 'denied')) {
        void savePermission(supported ? 'denied' : 'unsupported');
        return;
    }
    if (!supported) { show('Browser notifications require HTTPS and a supported browser. On iPhone or iPad, add AcadSyntra to your Home Screen first.'); updateButtons(null); return; }
    try {
        const accountId = document.querySelector('meta[name="push-account-id"]')?.content;
        const previousId = localStorage.getItem('acadflow_push_account');
        if (accountId && previousId && previousId !== accountId) {
            const subscription = await existing();
            if (subscription) await subscription.unsubscribe();
        }
        if (accountId) localStorage.setItem('acadflow_push_account', accountId);
    } catch (_) { /* Storage may be disabled by browser privacy settings. */ }
    // Ordinary navigation needs no push configuration request for a browser
    // that has never opted in. Account-switch cleanup above still always runs.
    if (!panel && (Notification.permission !== 'granted' || !await existing())) return;
    try { config = await api(configUrl); }
    catch (_) { show('Notification availability could not be checked. Reload to try again.'); updateButtons(await existing()); return; }
    if (invitation && config.available && !await existing()) {
        setTimeout(() => { panel.hidden = false; }, 5000);
        void savePermission(Notification.permission);
    }
    if (!config.available) {
        show('Browser notifications are currently unavailable. Your administrator must enable and configure push delivery.');
        updateButtons(await existing());
    } else {
        // No permission prompt on page load. Reconcile an already opted-in browser
        // with its current signed-in account, including switching shared accounts.
        try {
            const subscription = await existing();
            if (subscription && Notification.permission === 'granted') {
                // Reconcile once per tab session per account/endpoint, not on every page view.
                const syncKey = `acadflow_push_synced:${document.querySelector('meta[name="push-account-id"]')?.content || ''}:${subscription.endpoint}`;
                let synced = false;
                try { synced = sessionStorage.getItem('acadflow_push_synced') === syncKey; } catch (_) {}
                if (!synced) {
                    await registration();
                    await api('/browser-push/subscribe', subscription.toJSON());
                    try { sessionStorage.setItem('acadflow_push_synced', syncKey); } catch (_) {}
                }
            }
            show(Notification.permission === 'denied' ? 'Notifications are blocked in browser settings. Allow them there before enabling this device.' : subscription ? 'This browser is enrolled for notifications.' : 'Enable this browser to receive notifications when AcadSyntra is closed.');
        } catch (_) { show('This browser needs to reconnect. Use Enable this browser to try again.'); }
        updateButtons(await existing());
    }
    enable?.addEventListener('click', () => {
        if (busy) return;
        // Request permission immediately in the user's click, before any network await.
        const permission = Notification.requestPermission();
        void run(async () => {
            const result = await permission;
            void savePermission(result);
            if (result !== 'granted') { show('Permission was not granted. You can enable notifications later.'); if (invitation) setTimeout(() => { panel.hidden = true; }, 2500); return; }
            const worker = await registration();
            let subscription = await worker.pushManager.getSubscription();
            const key = decodeApplicationKey(config.publicKey);
            if (subscription && subscription.options?.applicationServerKey
                && String(new Uint8Array(subscription.options.applicationServerKey)) !== String(key)) {
                await api('/browser-push/unsubscribe', { endpoint: subscription.endpoint });
                await subscription.unsubscribe(); subscription = null;
            }
            subscription ||= await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            currentSubscription = subscription;
            await api('/browser-push/subscribe', { ...subscription.toJSON(), enablePreference: true });
            const preference = document.querySelector('input[type="checkbox"][name="push_enabled"]');
            if (preference) preference.checked = true;
            show('This browser is enrolled. Use Send test notification to check delivery.');
            if (invitation) panel.hidden = true;
        });
    });
    disable?.addEventListener('click', () => void run(async () => {
        const subscription = await existing();
        if (subscription) {
            try { await api('/browser-push/unsubscribe', { endpoint: subscription.endpoint }); } catch (_) {}
            await subscription.unsubscribe();
        }
        show('Notifications are disabled on this browser. Other enrolled devices are unchanged.');
    }));
    test?.addEventListener('click', () => void run(async () => {
        await api('/browser-push/test', {});
        show('Test accepted by the push service. Check your device notifications.');
    }));
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'PUSH_SUBSCRIPTION_CHANGED') show('Your browser subscription changed. Use Enable this browser to reconnect.');
    });
}
