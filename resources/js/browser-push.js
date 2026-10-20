export function decodeApplicationKey(value) {
    const raw = atob(value.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (char) => char.charCodeAt(0));
}

export async function initBrowserPush() {
    const configUrl = document.querySelector('meta[name="push-config-url"]')?.content;
    if (!configUrl) return;
    const panel = document.querySelector('[data-browser-push]');
    const status = panel?.querySelector('[data-push-status]');
    const enable = panel?.querySelector('[data-push-enable]');
    const disable = panel?.querySelector('[data-push-disable]');
    const test = panel?.querySelector('[data-push-test]');
    const supported = window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    let busy = false;
    let config;
    const show = (message) => { if (status) status.textContent = message; };
    const api = async (url, data, timeout = 12000) => {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetch(url, {
                method: data === undefined ? 'GET' : 'POST', credentials: 'same-origin', signal: controller.signal,
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                ...(data === undefined ? {} : { body: JSON.stringify(data) }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'The request could not be completed. Please try again.');
            return result;
        } finally { clearTimeout(timer); }
    };
    const registration = async () => {
        await navigator.serviceWorker.register(document.querySelector('meta[name="push-worker-url"]').content, { scope: '/', updateViaCache: 'none' });
        return Promise.race([navigator.serviceWorker.ready, new Promise((_, reject) => setTimeout(() => reject(new Error('Browser notifications are still starting. Try again shortly.')), 10000))]);
    };
    const existing = async () => (await navigator.serviceWorker.getRegistration('/'))?.pushManager.getSubscription();
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
    // Remove the current browser subscription before ending its authenticated session.
    document.querySelectorAll('form[action]').forEach((form) => {
        if (new URL(form.action).pathname !== '/logout') return;
        form.addEventListener('submit', async (event) => {
            if (form.dataset.pushCleaned || !supported) return;
            event.preventDefault();
            try {
                const subscription = await existing();
                if (subscription) {
                    try { await api('/browser-push/unsubscribe', { endpoint: subscription.endpoint }, 4000); } catch (_) {}
                    await subscription.unsubscribe();
                }
            } finally { form.dataset.pushCleaned = 'true'; form.requestSubmit(); }
        });
    });
    if (!supported) { show('Browser notifications require HTTPS and a supported browser. On iPhone or iPad, add AcadFlow to your Home Screen first.'); updateButtons(null); return; }
    try {
        const accountId = document.querySelector('meta[name="push-account-id"]')?.content;
        const previousId = localStorage.getItem('acadflow_push_account');
        if (accountId && previousId && previousId !== accountId) {
            const subscription = await existing();
            if (subscription) await subscription.unsubscribe();
        }
        if (accountId) localStorage.setItem('acadflow_push_account', accountId);
    } catch (_) { /* Storage may be disabled by browser privacy settings. */ }
    try { config = await api(configUrl); }
    catch (_) { show('Notification availability could not be checked. Reload to try again.'); updateButtons(await existing()); return; }
    if (!config.available) {
        show('Browser notifications are currently unavailable. Your administrator must enable and configure push delivery.');
        updateButtons(await existing());
    } else {
        // No permission prompt on page load. Reconcile an already opted-in browser
        // with its current signed-in account, including switching shared accounts.
        try {
            const subscription = await existing();
            if (subscription && Notification.permission === 'granted') {
                await registration();
                await api('/browser-push/subscribe', subscription.toJSON());
            }
            show(Notification.permission === 'denied' ? 'Notifications are blocked in browser settings. Allow them there before enabling this device.' : subscription ? 'This browser is enrolled for notifications.' : 'Enable this browser to receive notifications when AcadFlow is closed.');
        } catch (_) { show('This browser needs to reconnect. Use Enable this browser to try again.'); }
        updateButtons(await existing());
    }
    enable?.addEventListener('click', () => {
        if (busy) return;
        // Request permission immediately in the user's click, before any network await.
        const permission = Notification.requestPermission();
        void run(async () => {
            if (await permission !== 'granted') { show('Permission was not granted. You can enable notifications later.'); return; }
            const worker = await registration();
            let subscription = await worker.pushManager.getSubscription();
            const key = decodeApplicationKey(config.publicKey);
            if (subscription && subscription.options?.applicationServerKey
                && String(new Uint8Array(subscription.options.applicationServerKey)) !== String(key)) {
                await api('/browser-push/unsubscribe', { endpoint: subscription.endpoint });
                await subscription.unsubscribe(); subscription = null;
            }
            subscription ||= await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
            await api('/browser-push/subscribe', { ...subscription.toJSON(), enablePreference: true });
            const preference = document.querySelector('input[type="checkbox"][name="push_enabled"]');
            if (preference) preference.checked = true;
            show('This browser is enrolled. Use Send test notification to check delivery.');
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
