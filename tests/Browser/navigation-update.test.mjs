// Navigation / update-notice regression test (PWA enabled, local server).
// Usage: start the app (e.g. php artisan serve --port=8124), sign-in user with
// password "password", then:
//   NAV_TEST_URL=http://127.0.0.1:8124 NAV_TEST_EMAIL=student@example.test \
//   NAV_TEST_WORKER=/absolute/path/to/public/serviceworker.js node --test tests/Browser/navigation-update.test.mjs
// The test temporarily rewrites WORKER_VERSION in serviceworker.js to simulate
// a deploy and always restores the original file.
import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const BASE = process.env.NAV_TEST_URL || 'http://127.0.0.1:8124';
const EMAIL = process.env.NAV_TEST_EMAIL || 'demo.student@acadsyntra.test';
const SW = process.env.NAV_TEST_WORKER || new URL('../../public/serviceworker.js', import.meta.url).pathname;

test('navigation is never blocked by the update notice or the global loader', { timeout: 180000 }, async () => {
  const orig = fs.readFileSync(SW, 'utf8');
  const bump = (v) => fs.writeFileSync(SW, orig.replace(/const WORKER_VERSION = '[^']+';/, `const WORKER_VERSION = '${v}';`));
  try {
  const b = await chromium.launch(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {});
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 } });
  const p = await ctx.newPage();
  const errors = []; p.on('pageerror', e => errors.push(e.message));
  await p.goto(BASE + '/login'); await p.fill('input[name=email]', EMAIL); await p.fill('input[name=password]', 'password');
  await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
  for (let i = 0; i < 60 && !(await p.evaluate(async () => (await navigator.serviceWorker.getRegistration())?.active?.state === 'activated')); i++) await p.waitForTimeout(250);
  console.log('step: worker active');
  await p.reload(); await p.waitForFunction(() => !!navigator.serviceWorker.controller, null, { polling: 250, timeout: 10000 });
  console.log('step: controlled');
  const loader = () => p.evaluate(() => document.getElementById('acadflow-global-loader')?.dataset.state);

  // 1. Cancelled navigation ("Stay" on an unsaved-work prompt)
  p.once('dialog', d => d.dismiss());
  await p.evaluate(() => window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = ''; }));
  await p.evaluate(() => document.querySelector('a[href$="/courses"]').click());
  await p.waitForTimeout(1500);
  assert.equal(p.url(), BASE + '/dashboard'); assert.equal(await loader(), 'hidden');
  assert.equal(await p.evaluate(() => document.documentElement.dataset.navigating), undefined);
  console.log('PASS 1 cancelled navigation leaves no overlay or progress state');
  await p.evaluate(() => { window.onbeforeunload = null; }); await p.reload();

  // 2. Deploy: notice appears, worker does NOT take over without the user
  await p.evaluate(() => { window.__ctrl = 0; navigator.serviceWorker.addEventListener('controllerchange', () => window.__ctrl++); });
  bump('test-a');
  await p.evaluate(async () => (await navigator.serviceWorker.getRegistration()).update());
  await p.waitForSelector('#acadsyntra-update-toast', { timeout: 15000 });
  const text = (await p.locator('#acadsyntra-update-toast').innerText()).replace(/\s+/g, ' ');
  assert.match(text, /has been updated/); assert.match(text, /Refresh now/); assert.match(text, /Later/);
  assert.equal(await p.evaluate(() => window.__ctrl), 0); assert.equal(await loader(), 'hidden');
  console.log('PASS 2 notice shown, no automatic worker takeover:', text);

  // 3. Navigation continues normally while the update waits; repeated transitions
  const times = [];
  for (const path of ['/courses', '/profile', '/dashboard', '/notifications', '/dashboard']) {
    const t = Date.now();
    await Promise.all([p.waitForURL(BASE + path), p.evaluate(h => { const a = [...document.querySelectorAll('a')].find(x => x.getAttribute('href') === h || x.href === location.origin + h); if (a) a.click(); else location.href = h; }, path)]);
    await p.waitForLoadState('load'); times.push(Date.now() - t);
    assert.equal(await loader(), 'hidden');
  }
  console.log('PASS 3 navigation with a pending update, click->load ms:', times.join(', '));

  // 4. Later is remembered for this version across pages
  await p.waitForSelector('#acadsyntra-update-toast');
  await p.click('[data-update-later]');
  await p.goto(BASE + '/courses'); await p.waitForTimeout(2500);
  assert.equal(await p.locator('#acadsyntra-update-toast').count(), 0);
  console.log('PASS 4 dismissed version stays dismissed after navigation');

  // 5. A newer version is offered again; unsaved form warns; Refresh now reloads exactly once
  bump('test-b');
  await p.evaluate(async () => (await navigator.serviceWorker.getRegistration()).update());
  await p.waitForSelector('#acadsyntra-update-toast', { timeout: 15000 });
  await p.evaluate(() => { const f = document.createElement('form'); f.method = 'post'; const i = document.createElement('input'); f.append(i); document.body.append(f); i.dispatchEvent(new Event('input', { bubbles: true })); });
  await p.click('[data-update-refresh]');
  assert.match(await p.locator('[data-update-message]').innerText(), /unsaved changes/);
  let loads = 0; p.on('load', () => loads++);
  await p.click('[data-update-refresh]');
  await p.waitForTimeout(5000);
  assert.equal(loads, 1, 'exactly one reload');
  assert.equal(await p.evaluate(async () => { const r = await navigator.serviceWorker.getRegistration(); return !!r.waiting; }), false);
  assert.equal(await p.locator('#acadsyntra-update-toast').count(), 0);
  console.log('PASS 5 unsaved-change warning, then one reload onto the new worker');
  assert.deepEqual(errors, []);
  console.log('PASS no page errors');
  await b.close();
  } finally {
    fs.writeFileSync(SW, orig);
  }
});
