import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const root = new URL('../../', import.meta.url);
let browser, server, base;
let logoutResponseReady;
const posts = [];

before(async () => {
    const manifest = JSON.parse(await readFile(new URL('public/build/manifest.json', root), 'utf8'));
    const css = await readFile(new URL(`public/build/${manifest['resources/css/app.css'].file}`, root));
    const loader = (await readFile(new URL('resources/views/partials/global-loader.blade.php', root), 'utf8'))
        .replace(/\{\{ asset\('branding\/acadsyntra-mark.svg'\) \}\}/g, '/brand.svg');
    server = createServer(async (request, response) => {
        const path = new URL(request.url, 'http://fixture').pathname;
        if (path.endsWith('.js')) {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(await readFile(new URL(`resources/js/${path.slice(1)}`, root)));
        } else if (path === '/app.css') {
            response.setHeader('Content-Type', 'text/css'); response.end(css);
        } else if (path === '/brand.svg') {
            response.setHeader('Content-Type', 'image/svg+xml');
            response.end(await readFile(new URL('public/branding/acadsyntra-mark.svg', root)));
        } else if (path === '/push/config') {
            response.setHeader('Content-Type', 'application/json'); response.end('{"available":false}');
        } else if (path === '/logout') {
            let body = ''; for await (const chunk of request) body += chunk;
            posts.push(new URLSearchParams(body));
            // Hold the fixture response until feedback is observed. A short fixed
            // delay can finish before Chrome paints when browser suites run together.
            const finish = () => { clearTimeout(watchdog); response.writeHead(302, { Location: '/login' }); response.end(); };
            const watchdog = setTimeout(finish, 8000);
            logoutResponseReady?.(finish);
        } else if (path === '/login') {
            response.end('<h1>Signed out</h1>');
        } else if (path === '/download/file') {
            response.writeHead(200, { 'Content-Disposition': 'attachment; filename="example.txt"' }); response.end('example');
        } else if (path === '/slow') {
            setTimeout(() => { response.setHeader('Content-Type', 'text/html'); response.end('<h1>Arrived</h1>'); }, 700);
        } else {
            response.setHeader('Content-Type', 'text/html');
            response.end(`<!doctype html><meta name="csrf-token" content="fixture"><meta name="push-config-url" content="/push/config">
                <link rel="stylesheet" href="/app.css">${loader}
                <a id="navigate" href="/slow">Navigate</a><a id="cancel" href="/slow">Cancel</a>
                <a id="hash" href="#here">Hash</a><a id="download" href="/download/file">Download</a>
                <form id="cancel-form" method="post" action="/slow"><button>Cancel form</button></form>
                <form id="get-form" action="/slow"><button>Search</button></form>
                <form id="logout" method="post" action="/logout"><button name="intent" value="logout">Logout</button></form>
                <script type="module">
                    import { initGlobalLoader } from '/global-loader.js';
                    import { initPerformanceUX } from '/performance.js';
                    import { initBrowserPush } from '/browser-push.js';
                    initGlobalLoader(); initPerformanceUX();
                    document.querySelector('#cancel').onclick = event => event.preventDefault();
                    document.querySelector('#cancel-form').onsubmit = event => event.preventDefault();
                    window.fixtureReady = true;
                    initBrowserPush().catch(error => { window.pushError = error.message; });
                </script>`);
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    base = `http://127.0.0.1:${server.address().port}`;
    browser = await chromium.launch({
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
    });
});

after(async () => {
    await browser?.close();
    if (server) await new Promise(resolve => server.close(resolve));
});

async function fixture(mode = 'default') {
    const page = await browser.newPage();
    await page.addInitScript((mode) => {
        window.addEventListener('pageshow', () => { window.fixtureShown = true; });
        const pending = () => new Promise(() => {});
        window.pushReads = 0;
        const subscription = { endpoint: 'https://fcm.googleapis.com/fcm/send/fixture', unsubscribe: () => mode === 'unsubscribe-reject' ? Promise.reject(new Error('Unavailable')) : pending() };
        const registration = { pushManager: { getSubscription: () => {
            window.pushReads++;
            return mode === 'subscription-hang' ? pending() : Promise.resolve(subscription);
        } } };
        Object.defineProperty(window, 'Notification', { value: { permission: mode === 'default' ? 'default' : 'granted' } });
        Object.defineProperty(window, 'PushManager', { value: function () {} });
        Object.defineProperty(navigator, 'serviceWorker', { value: {
            getRegistration: () => { window.registrationReads = (window.registrationReads || 0) + 1; return mode === 'registration-hang' ? pending() : Promise.resolve(registration); },
            addEventListener() {},
        } });
    }, mode);
    await page.goto(base);
    page.setDefaultTimeout(4000);
    await page.waitForFunction(() => window.fixtureReady && window.fixtureShown);
    return page;
}

test('branded loader covers and centers on desktop and mobile, then resets', async () => {
    const page = await fixture();
    try {
        for (const width of [1440, 390]) {
            await page.setViewportSize({ width, height: 844 });
            await page.evaluate(() => window.AcadFlowLoader.show());
            const bounds = await page.locator('.acadflow-loader-card').boundingBox();
            assert.ok(Math.abs(bounds.x + bounds.width / 2 - width / 2) < 1);
            assert.ok(Math.abs(bounds.y + bounds.height / 2 - 422) < 1);
            assert.equal(await page.locator('.acadflow-loader-mark').evaluate(img => img.complete && img.naturalWidth > 0), true);
            assert.equal(await page.locator('#acadflow-global-loader').evaluate(el => getComputedStyle(el).pointerEvents), 'auto');
            await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
            assert.equal(await page.locator('#acadflow-global-loader').getAttribute('data-state'), 'hidden');
        }
    } finally { await page.close(); }
});

test('canceled actions, hashes and downloads leave no stuck overlay', async () => {
    const page = await fixture();
    try {
        for (const selector of ['#cancel', '#cancel-form button', '#hash']) {
            await page.locator(selector).click();
            await page.waitForTimeout(350);
            assert.equal(await page.locator('#acadflow-global-loader').getAttribute('data-state'), 'hidden', selector);
        }
        const download = page.waitForEvent('download');
        await page.locator('#download').click(); await download;
        await page.waitForTimeout(350);
        assert.equal(await page.locator('#acadflow-global-loader').getAttribute('data-state'), 'hidden');
    } finally { await page.close(); }
});

for (const selector of ['#navigate', '#get-form button']) {
    test(`native navigation shows branded feedback: ${selector}`, async () => {
        const page = await fixture();
        try {
            const visible = page.waitForFunction(() => document.querySelector('#acadflow-global-loader')?.dataset.state === 'visible');
            await Promise.all([visible, page.locator(selector).click()]);
            await page.waitForURL(url => url.pathname === '/slow');
            assert.equal(await page.locator('h1').innerText(), 'Arrived');
        } finally { await page.close(); }
    });
}

for (const mode of ['default', 'registration-hang', 'subscription-hang', 'unsubscribe-hang', 'unsubscribe-reject']) {
    test(`logout submits once without waiting for push cleanup: ${mode}`, { timeout: 10000 }, async () => {
        const page = await fixture(mode);
        try {
            if (mode !== 'default') await page.waitForFunction(() => window.registrationReads > 0);
            if (mode.startsWith('unsubscribe')) await page.waitForFunction(() => window.pushReads > 0);
            const before = posts.length;
            const responseReady = new Promise(resolve => { logoutResponseReady = resolve; });
            const submitted = page.waitForRequest(request => request.method() === 'POST' && request.url() === `${base}/logout`, { timeout: 1500 });
            const visible = page.waitForFunction(() => {
                const loader = document.querySelector('#acadflow-global-loader');
                return loader?.dataset.state === 'visible' && loader.querySelector('[data-acadflow-loader-label]').textContent;
            });
            // Two synchronous submissions exercise the production duplicate-submit protection.
            await page.evaluate(() => { const form = document.querySelector('#logout'); const button = form.querySelector('button'); form.requestSubmit(button); form.requestSubmit(button); });
            await submitted;
            assert.equal(await (await visible).jsonValue(), 'Signing out…');
            (await responseReady)();
            await page.waitForURL('**/login', { timeout: 2500 });
            assert.equal(posts.length - before, 1);
            assert.equal(posts.at(-1).get('intent'), 'logout');
            assert.equal(posts.at(-1).get('push_endpoint'), mode.startsWith('unsubscribe') ? 'https://fcm.googleapis.com/fcm/send/fixture' : null);
        } finally { await page.close(); }
    });
}

test('authenticated application logout completes with a stalled service worker', { skip: !process.env.LOGOUT_TEST_URL, timeout: 60000 }, async () => {
    const app = process.env.LOGOUT_TEST_URL;
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(() => {
        Object.defineProperty(window, 'Notification', { value: { permission: 'granted' } });
        Object.defineProperty(window, 'PushManager', { value: function () {} });
        Object.defineProperty(navigator, 'serviceWorker', { value: {
            getRegistration: () => { window.hungPushLookups = (window.hungPushLookups || 0) + 1; return new Promise(() => {}); },
            getRegistrations: async () => [],
            register: async () => ({ addEventListener() {} }),
            addEventListener() {},
        } });
    });
    try {
        await page.goto(`${app}/login`);
        await page.locator('#email').fill('studio.owner@acadsyntra.test');
        await page.locator('#password').fill('StudioExample123!');
        await page.getByRole('button', { name: 'Sign in to AcadSyntra' }).click();
        await page.waitForURL(/dashboard|onboarding/);
        await page.waitForFunction(() => window.hungPushLookups > 0);
        await page.locator('button[onclick="toggleUserDropdown()"]').filter({ visible: true }).first().click();
        const requests = [];
        page.on('request', request => { if (request.method() === 'POST' && new URL(request.url()).pathname === '/logout') requests.push(request); });
        const started = Date.now();
        await page.getByRole('button', { name: 'Logout', exact: true }).click();
        await page.waitForURL(`${app}/login`, { timeout: 4000 });
        assert.equal(requests.length, 1);
        assert.ok(await page.locator('#email').isVisible());
        const protectedPage = await page.goto(`${app}/dashboard`);
        assert.equal(new URL(protectedPage.url()).pathname, '/login');
        assert.deepEqual(errors, []);
        console.log(`Authenticated logout and protected-page check completed in ${Date.now() - started}ms.`);
    } finally { await page.close(); }
});
