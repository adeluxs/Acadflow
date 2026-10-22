import test, { before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { chromium } from 'playwright';

const root = new URL('../../', import.meta.url);
const worker = await readFile(new URL('public/serviceworker.js', root), 'utf8');
const push = await readFile(new URL('public/push-events.js', root), 'utf8');
const layout = await readFile(new URL('resources/views/layouts/app.blade.php', root), 'utf8');
const recovery = layout.slice(layout.indexOf("navigator.serviceWorker.addEventListener('controllerchange'"), layout.indexOf("            window.addEventListener('load', () => {", layout.indexOf("navigator.serviceWorker.addEventListener('controllerchange'")));
const stylesheet = '/build/assets/app-AbCd1234.css';
let browser, server, base;
let stylesheetReads = 0;
let documentReads = 0;
let poisonNextStylesheet = false;

before(async () => {
    server = createServer((request, response) => {
        const url = new URL(request.url, 'http://fixture');
        if (url.pathname === '/serviceworker.js' || url.pathname === '/push-events.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(url.pathname === '/serviceworker.js' ? worker : push);
        } else if (url.pathname === '/legacy-worker.js') {
            response.setHeader('Content-Type', 'text/javascript');
            response.end(`self.addEventListener('install',e=>e.waitUntil(self.skipWaiting()));self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));self.addEventListener('fetch',e=>{if(e.request.destination==='style')e.respondWith(new Response('<h1>Old cached page</h1>',{headers:{'Content-Type':'text/html'}}));});`);
        } else if (url.pathname === stylesheet) {
            stylesheetReads++;
            if (poisonNextStylesheet) {
                poisonNextStylesheet = false;
                response.setHeader('Content-Type', 'text/html');
                response.setHeader('Cache-Control', 'public, max-age=3600');
                response.end('<h1>Temporary fallback in the HTTP cache</h1>');
                return;
            }
            response.setHeader('Content-Type', 'text/css');
            response.setHeader('Cache-Control', 'public, max-age=0');
            response.end('.style-probe { color: rgb(23, 45, 67); }');
        } else if (url.pathname === '/setup') {
            response.setHeader('Content-Type', 'text/html');
            const path = url.searchParams.get('worker') === 'legacy' ? '/legacy-worker.js' : '/serviceworker.js' + (url.searchParams.has('dev') ? '?dev=1' : '');
            response.end(`<!doctype html><a href="/page2" id="next">Next page</a><script>navigator.serviceWorker.register(${JSON.stringify(path)},{updateViaCache:'none'});</script>`);
        } else if (url.pathname === '/page2') {
            documentReads++;
            response.setHeader('Content-Type', 'text/html');
            response.setHeader('Cache-Control', 'private, no-store');
            response.end(`<!doctype html><link rel="stylesheet" href="${stylesheet}"><p class="style-probe">Styled after navigation</p><a href="/page2?again=1" id="next">Next page</a>${url.searchParams.has('upgrade') ? `<script>${recovery}window.addEventListener('load',()=>navigator.serviceWorker.register('/serviceworker.js',{updateViaCache:'none'}));</script>` : ''}`);
        } else if (url.pathname === '/offline.html') {
            response.setHeader('Content-Type', 'text/html'); response.end('<h1>Offline</h1>');
        } else {
            response.writeHead(404); response.end('Not found');
        }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    base = `http://127.0.0.1:${server.address().port}`;
    browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
});

after(async () => { await browser?.close(); if (server) await new Promise(resolve => server.close(resolve)); });

async function fixture(query = '') {
    const context = await browser.newContext({ serviceWorkers: 'allow' });
    const page = await context.newPage();
    await page.goto(base + '/setup' + query);
    await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    await page.evaluate(async () => { await navigator.serviceWorker.ready; });
    return { page, context };
}

test('a poisoned stylesheet cache recovers on the next click without a manual refresh', async () => {
    const { page, context } = await fixture();
    try {
        const before = stylesheetReads;
        await page.evaluate(async (path) => {
            const name = (await caches.keys()).find(name => name.startsWith('acadflow-static-'));
            await (await caches.open(name)).put(path, new Response('<h1>Cached login</h1>', { headers: { 'Content-Type': 'text/html' } }));
        }, stylesheet);
        await page.locator('#next').click();
        await page.waitForURL('**/page2');
        assert.equal(await page.locator('.style-probe').evaluate(el => getComputedStyle(el).color), 'rgb(23, 45, 67)');
        assert.equal(stylesheetReads - before, 1);
        await page.locator('#next').click();
        await page.waitForURL('**/page2?again=1');
        assert.equal(await page.locator('.style-probe').evaluate(el => getComputedStyle(el).color), 'rgb(23, 45, 67)');
        assert.equal(stylesheetReads - before, 1, 'valid hashed CSS should remain cached on later navigation');
    } finally { await context.close(); }
});

test('the local development worker bypasses a stale CSS entry', async () => {
    const { page, context } = await fixture('?dev=1');
    try {
        await page.evaluate(async (path) => {
            const name = (await caches.keys()).find(name => name.startsWith('acadflow-static-'));
            await (await caches.open(name)).put(path, new Response('.style-probe { color: red }', { headers: { 'Content-Type': 'text/css' } }));
        }, stylesheet);
        await page.locator('#next').click();
        await page.waitForURL('**/page2');
        assert.equal(await page.locator('.style-probe').evaluate(el => getComputedStyle(el).color), 'rgb(23, 45, 67)');
    } finally { await context.close(); }
});

test('repairing a poisoned worker entry also bypasses a poisoned browser HTTP cache', async () => {
    const { page, context } = await fixture();
    try {
        poisonNextStylesheet = true;
        await page.evaluate(async (path) => {
            // A fetch with destination=empty reaches the normal HTTP cache.
            const response = await fetch(path);
            if (!response.headers.get('Content-Type').includes('text/html')) throw new Error('Expected a poisoned HTTP fixture');
            const name = (await caches.keys()).find(name => name.startsWith('acadflow-static-'));
            await (await caches.open(name)).put(path, response);
        }, stylesheet);
        await page.locator('#next').click();
        await page.waitForURL('**/page2');
        assert.equal(await page.locator('.style-probe').evaluate(el => getComputedStyle(el).color), 'rgb(23, 45, 67)');
    } finally { await context.close(); }
});

test('an old worker upgrade repairs missing CSS in the current document without reloading it', async () => {
    const { page, context } = await fixture('?worker=legacy');
    try {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const before = documentReads;
        await page.locator('#next').evaluate(link => { link.href = '/page2?upgrade=1'; });
        await page.locator('#next').click();
        await page.waitForURL('**/page2?upgrade=1');
        try {
            await page.waitForFunction(() => getComputedStyle(document.querySelector('.style-probe')).color === 'rgb(23, 45, 67)', undefined, { timeout: 10000 });
        } catch (error) {
            const state = await page.evaluate(() => ({
                worker: navigator.serviceWorker.controller?.scriptURL,
                style: [...document.querySelectorAll('link[rel=stylesheet]')].map(link => ({ href: link.href, sheet: !!link.sheet, rules: link.sheet?.cssRules.length, retried: link.dataset.workerStyleRetry })),
                ready: document.readyState,
            }));
            console.log(JSON.stringify({ state, errors }));
            throw error;
        }
        assert.equal(documentReads - before, 1);
        assert.equal(await page.locator('link[rel=stylesheet]').getAttribute('data-worker-style-retry'), '1');
        assert.deepEqual(errors, []);
    } finally { await context.close(); }
});
