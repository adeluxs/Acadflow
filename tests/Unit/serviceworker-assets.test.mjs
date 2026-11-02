import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = await readFile(new URL('../../public/serviceworker.js', import.meta.url), 'utf8');
const origin = 'https://acadsyntra.test';
const css = (body = 'body { color: blue }') => new Response(body, { headers: { 'Content-Type': 'text/css' } });

function worker({ development = false, storageUnavailable = false, network = async () => css() } = {}) {
    const events = {}, stores = new Map(), reads = [];
    const caches = {
        async open(name) {
            if (storageUnavailable) throw new Error('Cache storage unavailable');
            if (!stores.has(name)) stores.set(name, new Map());
            const data = stores.get(name);
            return {
                async match(request) { return data.get(typeof request === 'string' ? request : request.url)?.clone(); },
                async put(request, response) { data.set(typeof request === 'string' ? request : request.url, response.clone()); },
                async delete(request) { return data.delete(typeof request === 'string' ? request : request.url); },
                async add() {},
            };
        },
        async match(request) {
            for (const name of stores.keys()) {
                const value = await (await caches.open(name)).match(request);
                if (value) return value;
            }
        },
        async keys() { return [...stores.keys()]; },
        async delete(name) { return stores.delete(name); },
    };
    const self = {
        location: new URL('/serviceworker.js' + (development ? '?dev=1' : ''), origin),
        addEventListener(type, callback) { events[type] = callback; },
        registration: {}, clients: { async claim() {} }, async skipWaiting() {},
    };
    const context = vm.createContext({ self, caches, URL, Request, Response, Headers, console: { log() {} }, importScripts() {}, fetch: async (request) => { reads.push(request.url); return network(request); } });
    vm.runInContext(source, context);
    const cacheName = vm.runInContext('STATIC_CACHE', context);
    const request = (path, options = {}) => {
        const req = new Request(new URL(path, origin), options);
        Object.defineProperty(req, 'destination', { value: options.destination || 'style' });
        return req;
    };
    return { stores, caches, cacheName, reads, request, async fetch(req) {
        let response;
        const pending = [];
        events.fetch({ request: req, respondWith(value) { response = value; }, waitUntil(task) { pending.push(task); } });
        const result = await response;
        await Promise.all(pending);
        return result;
    }, async activate() {
        let pending;
        events.activate({ waitUntil(task) { pending = task; } });
        await pending;
    } };
}

test('a cached HTML response cannot keep a stylesheet broken on subsequent navigation', async () => {
    const w = worker();
    const request = w.request('/build/assets/app-AbCd1234.css');
    await (await w.caches.open(w.cacheName)).put(request, new Response('<h1>Login</h1>', { headers: { 'Content-Type': 'text/html' } }));
    const response = await w.fetch(request);
    assert.equal(response.headers.get('Content-Type'), 'text/css');
    assert.equal(w.reads.length, 1);
});

test('a hard reload bypasses a previously cached immutable stylesheet', async () => {
    const w = worker();
    const request = w.request('/build/assets/app-AbCd1234.css', { cache: 'reload' });
    await (await w.caches.open(w.cacheName)).put(request, css('old'));
    assert.equal(await (await w.fetch(request)).text(), 'body { color: blue }');
    assert.equal(w.reads.length, 1);
});

test('unhashed styles are refreshed on navigation while immutable styles stay cached', async () => {
    const w = worker();
    const cache = await w.caches.open(w.cacheName);
    const mutable = w.request('/css/mobile-optimized.css');
    const immutable = w.request('/build/assets/app-AbCd1234.css');
    await cache.put(mutable, css('old mobile rules'));
    await cache.put(immutable, css('hashed rules'));
    assert.equal(await (await w.fetch(mutable)).text(), 'body { color: blue }');
    assert.equal(await (await w.fetch(immutable)).text(), 'hashed rules');
    assert.equal(w.reads.length, 1);
});

test('an HTML network response is not stored as a stylesheet and the next request can recover', async () => {
    let attempt = 0;
    const w = worker({ network: async () => ++attempt === 1 ? new Response('<h1>Temporary fallback</h1>', { headers: { 'Content-Type': 'text/html' } }) : css() });
    const request = w.request('/build/assets/app-AbCd1234.css');
    await w.fetch(request);
    assert.equal(await (await w.caches.open(w.cacheName)).match(request), undefined);
    assert.equal((await w.fetch(request)).headers.get('Content-Type'), 'text/css');
});

test('local development and Vite development modules bypass worker asset caching', async () => {
    const w = worker({ development: true });
    assert.equal(await w.fetch(w.request('/build/assets/app-AbCd1234.css')), undefined);
    const production = worker();
    assert.equal(await production.fetch(production.request('/resources/js/app.js', { destination: 'script' })), undefined);
    assert.equal(production.reads.length, 0);
});

test('offline immutable styles still work and private images are never intercepted', async () => {
    const w = worker({ network: async () => { throw new TypeError('offline'); } });
    const request = w.request('/build/assets/app-AbCd1234.css');
    await (await w.caches.open(w.cacheName)).put(request, css('offline style'));
    assert.equal(await (await w.fetch(request)).text(), 'offline style');
    assert.equal(await w.fetch(w.request('/private/avatar/1', { destination: 'image' })), undefined);
});

test('activation retires old application asset caches and preserves unrelated origin caches', async () => {
    const w = worker();
    await w.caches.open('acadflow-static-v1');
    await w.caches.open('unrelated-cache');
    await w.activate();
    assert.ok(!w.stores.has('acadflow-static-v1'));
    assert.ok(w.stores.has('unrelated-cache'));
});

test('a browser cache storage failure does not stop CSS from loading from the network', async () => {
    const w = worker({ storageUnavailable: true });
    const response = await w.fetch(w.request('/build/assets/app-AbCd1234.css'));
    assert.equal(response.headers.get('Content-Type'), 'text/css');
    assert.equal(w.reads.length, 1);
});

test('fresh CSS marked private is displayed without storing it or reusing older mobile rules', async () => {
    const w = worker({ network: async () => new Response('fresh private css', { headers: { 'Content-Type': 'text/css', 'Cache-Control': 'private, no-store' } }) });
    const request = w.request('/css/mobile-optimized.css');
    const cache = await w.caches.open(w.cacheName);
    await cache.put(request, css('old rules'));
    assert.equal(await (await w.fetch(request)).text(), 'fresh private css');
    assert.equal(await cache.match(request), undefined);
});
