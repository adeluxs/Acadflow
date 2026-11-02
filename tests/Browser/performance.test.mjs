import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { writeFile } from 'node:fs/promises';

const base = process.env.PERFORMANCE_TEST_URL || 'http://127.0.0.1:8013';

test('creation pages render without optional modules or blocking requests', { timeout: 120_000 }, async () => {
    const browser = await chromium.launch({
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        headless: true,
    });
    const results = [];
    try {
        const page = await browser.newPage({ serviceWorkers: 'block' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => {
            window.pageMetrics = { lcp: 0, cls: 0, interaction: 0 };
            new PerformanceObserver(list => {
                for (const e of list.getEntries()) window.pageMetrics.lcp = e.startTime;
            }).observe({ type: 'largest-contentful-paint', buffered: true });
            new PerformanceObserver(list => {
                for (const e of list.getEntries()) if (!e.hadRecentInput) window.pageMetrics.cls += e.value;
            }).observe({ type: 'layout-shift', buffered: true });
            new PerformanceObserver(list => {
                for (const e of list.getEntries()) if (e.interactionId) window.pageMetrics.interaction = Math.max(window.pageMetrics.interaction, e.duration);
            }).observe({ type: 'event', buffered: true, durationThreshold: 16 });
        });
        const loginResponse = await page.goto(`${base}/login`);
        assert.equal(loginResponse.status(), 200);
        assert.ok((await loginResponse.text()).includes('id="email"'), 'test server must return the actual login form');
        await page.locator('#email').fill('studio.owner@acadsyntra.test');
        await page.locator('#password').fill('StudioExample123!');
        await page.getByRole('button', { name: 'Sign in to AcadSyntra' }).click();
        await page.waitForURL(/dashboard|onboarding/);
        for (const path of ['/research-studio/create', '/research-studio/create?studio_mode=final_year']) {
            for (let sample = 0; sample < 5; sample++) {
                const response = await page.goto(base + path, { waitUntil: 'domcontentloaded', timeout: 25_000 });
                assert.equal(response.status(), 200);
                await page.locator('[data-setup-controls]').waitFor({ state: 'visible' });
                await page.locator('[name="methodology"]').selectOption('software');
                await page.getByText('Suggested instruments:', { exact: false }).waitFor();
                await page.waitForTimeout(300);
                const metrics = await page.evaluate(() => {
                    const nav = performance.getEntriesByType('navigation')[0];
                    return {
                        ...window.pageMetrics,
                        ttfb: nav.responseStart - nav.startTime,
                        dom: nav.domContentLoadedEventEnd - nav.startTime,
                        fcp: performance.getEntriesByName('first-contentful-paint')[0]?.startTime,
                        resources: performance.getEntriesByType('resource').map(r => ({ name: new URL(r.name).pathname, bytes: r.transferSize })),
                    };
                });
                results.push({ path, sample, bytes: (await response.body()).length, ...metrics });
                assert.ok((await response.body()).length < 60_000, 'creation HTML exceeded 60 KB');
                assert.ok(!metrics.resources.some(r => /rich-editor|runtime-core|NotificationCenter|FileUpload/.test(r.name)), 'creation must not load the editor or Vue');
                assert.ok(!metrics.resources.some(r => /browser-push\/configuration|create\/options/.test(r.name)), 'optional requests must wait for activation');
                if (process.env.PERFORMANCE_ASSERT_BUDGETS === '1') {
                    assert.ok(metrics.dom < 1500, `Meaningful display took ${metrics.dom}ms`);
                    assert.ok(metrics.lcp <= 2500, `LCP ${metrics.lcp}ms`);
                    assert.ok(metrics.cls <= 0.1, `CLS ${metrics.cls}`);
                }
            }
        }
        assert.deepEqual(errors, []);
    } finally {
        await writeFile(process.env.PERFORMANCE_OUTPUT || '/private/tmp/acadsyntra-browser-performance.json', JSON.stringify(results, null, 2));
        console.log(JSON.stringify(results.map(({ resources, ...metrics }) => metrics)));
        await browser.close();
    }
});
