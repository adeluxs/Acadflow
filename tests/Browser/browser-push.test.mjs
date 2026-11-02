import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { existsSync } from 'node:fs';
import { chromium } from 'playwright';

const workerSource = await readFile(new URL('../../public/push-events.js', import.meta.url), 'utf8');
const browserSource = await readFile(new URL('../../resources/js/browser-push.js', import.meta.url), 'utf8');

function worker() {
    const events = {}, shown = [], opened = [];
    const context = { URL, self: {
        location: { origin: 'https://acadflow.test' }, addEventListener: (type, handler) => { events[type] = handler; },
        registration: { showNotification: async (title, options) => shown.push({ title, options }) },
        clients: { matchAll: async () => [], openWindow: async (url) => opened.push(url) },
    } };
    vm.runInNewContext(workerSource, context);
    return { events, shown, opened };
}

test('worker displays received payload and rejects external notification destinations', async () => {
    const { events, shown, opened } = worker();
    let pending;
    events.push({ data: { json: () => ({ title: 'Assignment', body: 'Due tomorrow', data: { url: 'https://attacker.test' } }) }, waitUntil: (task) => { pending = task; } });
    await pending;
    assert.equal(shown[0].title, 'Assignment');
    assert.equal(shown[0].options.body, 'Due tomorrow');
    events.notificationclick({ notification: { data: shown[0].options.data, close() {} }, waitUntil: (task) => { pending = task; } });
    await pending;
    assert.deepEqual(opened, ['https://acadflow.test/notifications']);
});

test('worker tolerates empty or non-JSON push data', async () => {
    const { events, shown } = worker();
    let pending;
    events.push({ data: { json: () => { throw new Error('not JSON'); }, text: () => 'Plain text' }, waitUntil: (task) => { pending = task; } });
    await pending;
    assert.equal(shown[0].options.body, 'Plain text');
    events.push({ waitUntil: (task) => { pending = task; } });
    await pending;
    assert.equal(shown[1].title, 'AcadSyntra');
});

async function pageFixture({ available = true, permission = 'default', failSubscribe = false, subscribed = false, previousAccount = null, invitation = false } = {}) {
    const localChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
    const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || (existsSync(localChrome) ? localChrome : undefined);
    const browser = await chromium.launch({ headless: true, executablePath });
    const page = await browser.newPage();
    await page.route('https://acadflow.test/**', (route) => route.fulfill({ contentType: 'text/html', body: `
        <form action="/logout" method="POST"><button data-logout>Sign out</button></form><meta name="push-account-id" content="1"><meta name="csrf-token" content="test"><meta name="push-config-url" content="/browser-push/configuration"><meta name="push-worker-url" content="/pushworker.js">
        <section data-browser-push ${invitation?'data-push-invitation hidden':''}><p data-push-status></p><button data-push-enable disabled>Enable</button><button data-push-disable disabled>Disable</button><button data-push-test disabled>Test</button><button data-push-later>Not Now</button></section>` }));
    await page.goto('https://acadflow.test/notifications/settings');
    await page.evaluate(({ available, permission, failSubscribe, subscribed, previousAccount }) => {
        window.pushCalls = []; window.permissionPrompts = 0; window.failSubscribe = failSubscribe;
        let current = null;
        const fakeSubscription = { endpoint: 'https://fcm.googleapis.com/send/test', options: {}, toJSON: () => ({ endpoint: 'https://fcm.googleapis.com/send/test', keys: { p256dh: 'key', auth: 'auth' } }), unsubscribe: async () => { window.pushCalls.push('device-unsubscribe'); current = null; return true; } };
        if (subscribed) current = fakeSubscription;
        if (previousAccount) localStorage.setItem('acadflow_push_account', previousAccount);
        const registration = { pushManager: { getSubscription: async () => current, subscribe: async () => { current = fakeSubscription; return current; } } };
        Object.defineProperty(navigator, 'serviceWorker', { value: { register: async (url) => { window.workerUrl = url; return registration; }, ready: Promise.resolve(registration), getRegistration: async () => registration, addEventListener() {} } });
        Object.defineProperty(window, 'PushManager', { value: function () {} });
        Object.defineProperty(window, 'Notification', { value: { permission, requestPermission: async () => { window.permissionPrompts++; window.Notification.permission = 'granted'; return 'granted'; } } });
        window.fetch = async (url) => {
            window.pushCalls.push(url);
            if (url.endsWith('configuration')) return { ok: true, json: async () => ({ available, publicKey: 'BAECAwQF' }) };
            if (url.endsWith('subscribe') && window.failSubscribe) return { ok: false, json: async () => ({ message: 'Temporary enrollment failure' }) };
            return { ok: true, json: async () => ({ success: true }) };
        };
    }, { available, permission, failSubscribe, subscribed, previousAccount });
    await page.addScriptTag({ type: 'module', content: browserSource.replaceAll('export ', '') + '\nawait initBrowserPush(); window.pushReady = true;' });
    await page.waitForFunction(() => window.pushReady);
    return { browser, page };
}

test('browser enrolls only after a click, uses standalone worker, and can disable again', async () => {
    const { browser, page } = await pageFixture();
    try {
        assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
        await page.locator('[data-push-enable]').click();
        await page.waitForFunction(() => document.querySelector('[data-push-status]').textContent.includes('This browser is enrolled.'));
        assert.equal(await page.evaluate(() => window.workerUrl), '/pushworker.js');
        assert.equal(await page.evaluate(() => window.permissionPrompts), 1);
        await page.locator('[data-push-disable]').click();
        await page.waitForFunction(() => document.querySelector('[data-push-status]').textContent.includes('disabled on this browser'));
        assert.equal(await page.locator('[data-push-test]').isDisabled(), true);
    } finally { await browser.close(); }
});

test('enrollment failure clears busy state and allows a successful retry', async () => {
    const { browser, page } = await pageFixture({ failSubscribe: true });
    try {
        await page.locator('[data-push-enable]').click();
        await page.waitForFunction(() => document.querySelector('[data-push-status]').textContent.includes('Temporary enrollment failure'));
        assert.equal(await page.locator('[data-push-enable]').isEnabled(), true);
        await page.evaluate(() => { window.failSubscribe = false; });
        await page.locator('[data-push-enable]').click();
        await page.waitForFunction(() => document.querySelector('[data-push-status]').textContent.includes('This browser is enrolled.'));
    } finally { await browser.close(); }
});

test('disabled delivery and denied browser permission do not offer enrollment', async () => {
    for (const options of [{ available: false }, { permission: 'denied' }]) {
        const { browser, page } = await pageFixture(options);
        try {
            assert.equal(await page.locator('[data-push-enable]').isDisabled(), true);
            assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
        } finally { await browser.close(); }
    }
});


test('changing accounts on a shared browser invalidates the earlier device subscription', async () => {
    const { browser, page } = await pageFixture({ available: false, permission: 'granted', subscribed: true, previousAccount: '2' });
    try {
        assert.equal(await page.evaluate(() => window.pushCalls.includes('device-unsubscribe')), true);
        assert.equal(await page.locator('[data-push-disable]').isDisabled(), true);
        assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
    } finally { await browser.close(); }
});

test('logout submits once immediately with endpoint metadata while device cleanup stays best effort', async () => {
    const { browser, page } = await pageFixture({ permission: 'granted', subscribed: true });
    try {
        await page.evaluate(() => document.querySelector('form[action="/logout"]').addEventListener('submit', event => {
            event.preventDefault();
            window.pushCalls.push('logout-submitted');
            window.logoutEndpoint = event.currentTarget.querySelector('[name="push_endpoint"]')?.value;
        }));
        await page.locator('[data-logout]').click();
        await page.waitForFunction(() => window.pushCalls.includes('logout-submitted'));
        assert.equal(await page.evaluate(() => window.pushCalls.filter(call => call==='logout-submitted').length), 1);
        assert.match(await page.evaluate(() => window.logoutEndpoint), /^https:\/\/fcm\.googleapis\.com/);
        assert.equal(await page.evaluate(() => window.pushCalls.includes('/browser-push/unsubscribe')), false);
    } finally { await browser.close(); }
});

test('the invitation waits for a click before permission and Not Now snoozes without prompting', async () => {
    const { browser, page } = await pageFixture({ invitation: true });
    try {
        assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
        await page.locator('[data-push-later]').click();
        assert.equal(await page.locator('[data-push-invitation]').isHidden(), true);
        assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
        assert.ok(await page.evaluate(() => window.pushCalls.includes('/browser-push/permission')));
    } finally { await browser.close(); }
});

test('a denied permission keeps the invitation hidden and never requests permission again', async () => {
    const { browser, page } = await pageFixture({ invitation: true, permission: 'denied' });
    try {
        assert.equal(await page.locator('[data-push-invitation]').isHidden(), true);
        assert.equal(await page.evaluate(() => window.permissionPrompts), 0);
        assert.equal(await page.evaluate(() => window.pushCalls.includes('/browser-push/configuration')), false);
    } finally { await browser.close(); }
});
