import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { existsSync } from 'node:fs';
import { mkdir } from 'node:fs/promises';

const base = process.env.PUBLIC_WEBSITE_TEST_URL || 'http://127.0.0.1:8012';
const localChrome = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || (existsSync(localChrome) ? localChrome : undefined);
const paths = ['/', '/features', '/how-it-works', '/about', '/contact', '/faqs', '/help', '/privacy', '/terms', '/cookies', '/disclaimer', '/information/documentation', '/information/security'];
const artifacts = process.env.PUBLIC_WEBSITE_ARTIFACTS || '/private/tmp/acadflow-public-qa';

async function scrollPage(page) {
    await page.evaluate(async () => {
        const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
        for (let y = 0; y < document.body.scrollHeight; y += innerHeight * .8) {
            window.scrollTo({ top: y, behavior: 'instant' });
            await new Promise((done) => setTimeout(done, reduce ? 5 : 30));
        }
        window.scrollTo({ top: 0, behavior: 'instant' });
    });
    await page.waitForTimeout(550);
}

async function browserTest(fn) {
    const browser = await chromium.launch({ executablePath, headless: true });
    try { await fn(browser); } finally { await browser.close(); }
}

test('public routes, assets and responsive layouts work from 320px to desktop', { timeout: 240_000 }, async () => {
    await mkdir(artifacts, { recursive: true });
    await browserTest(async (browser) => {
        const failures = [];
        const page = await browser.newPage();
        page.on('pageerror', (error) => failures.push(error.message));
        page.on('response', (response) => { if (response.status() >= 400) failures.push(`${response.status()} ${response.url()}`); });
        const metadata = new Map();
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: width < 768 ? 844 : 1000 });
            for (const path of paths) {
                const response = await page.goto(base + path, { waitUntil: 'networkidle' });
                assert.equal(response.status(), 200, `${path} at ${width}`);
                await scrollPage(page);
                const dimensions = await page.evaluate(() => ({ viewport: innerWidth, scroll: document.documentElement.scrollWidth, badImages: [...document.images].filter((img) => !img.complete || img.naturalWidth === 0).map((img) => img.src) }));
                assert.ok(dimensions.scroll <= width + 1, `${path} overflows at ${width}: ${JSON.stringify(dimensions)}`);
                assert.deepEqual(dimensions.badImages, [], `${path} has missing images`);
                assert.equal(await page.locator('h1').count(), 1);
                assert.ok((await page.locator('meta[name="description"]').getAttribute('content')).length > 35);
                assert.equal(await page.locator('link[rel="canonical"]').count(), 1);
                const title = await page.title();
                if (width === 1440) metadata.set(path, title);
                if (['/', '/features', '/contact'].includes(path) && [390, 1440].includes(width)) {
                    await page.screenshot({ path: `${artifacts}/${path === '/' ? 'home' : path.slice(1)}-${width}.png`, fullPage: true });
                }
            }
        }
        assert.equal(new Set(metadata.values()).size, paths.length, 'Each canonical public page needs a distinct title');
        assert.deepEqual(failures, []);
    });
});

test('mobile menu is modal, keyboard accessible, closes on navigation and restores focus', { timeout: 30_000 }, async () => {
    await browserTest(async (browser) => {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.goto(base, { waitUntil: 'networkidle' });
        const trigger = page.getByRole('button', { name: 'Open navigation' });
        await trigger.click();
        const dialog = page.getByRole('dialog', { name: 'Mobile navigation' });
        assert.equal(await dialog.evaluate((el) => el.open && el.matches(':modal')), true);
        assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => !document.querySelector('#mobile-navigation').open && document.activeElement.matches('[data-menu-open]'));
        assert.equal(await page.locator('#mobile-navigation').evaluate((el) => el.open), false);
        assert.equal(await trigger.evaluate((el) => el === document.activeElement), true);
        await trigger.click();
        await dialog.getByRole('link', { name: 'Features' }).click();
        await page.waitForURL(base + '/features');
        assert.equal(await page.locator('#mobile-navigation').evaluate((el) => el.open), false);
        await page.getByRole('button', { name: 'Open navigation' }).click();
        await page.getByRole('button', { name: 'Close navigation' }).click();
        assert.equal(await page.locator('body').evaluate((el) => el.classList.contains('menu-is-open')), false);
    });
});

test('feature and FAQ searches, filters and product preview keyboard tabs work', { timeout: 40_000 }, async () => {
    await browserTest(async (browser) => {
        const page = await browser.newPage();
        await page.goto(base + '/features', { waitUntil: 'networkidle' });
        await page.getByRole('searchbox', { name: 'Search features' }).fill('supervisor');
        assert.equal(await page.locator('[data-search-item]:visible').count(), 1);
        await page.getByRole('searchbox', { name: 'Search features' }).fill('zzzzunknown');
        assert.equal(await page.locator('[data-search-empty]').isVisible(), true);
        await page.getByRole('searchbox', { name: 'Search features' }).fill('');
        await page.getByRole('button', { name: 'Institutions', exact: true }).click();
        assert.equal(await page.locator('[data-search-item]:visible').count(), 1);
        await page.goto(base + '/faqs', { waitUntil: 'networkidle' });
        await page.getByRole('searchbox', { name: 'Search questions' }).fill('password');
        assert.ok(await page.locator('details[open]:visible').count() >= 1);
        await page.getByRole('searchbox', { name: 'Search questions' }).fill('');
        await page.getByRole('button', { name: 'Getting started', exact: true }).click();
        assert.equal(await page.locator('[data-search-item]:visible').count(), 3);
        await page.goto(base, { waitUntil: 'networkidle' });
        const first = page.getByRole('tab', { name: 'Research Studio' });
        await first.focus();
        await page.keyboard.press('ArrowRight');
        assert.equal(await page.getByRole('tab', { name: 'Courses & learning' }).getAttribute('aria-selected'), 'true');
        assert.equal(await page.locator('#showcase-learning').isVisible(), true);
        await page.keyboard.press('End');
        assert.equal(await page.locator('#showcase-knowledge').isVisible(), true);
        await page.keyboard.press('Home');
        assert.equal(await page.locator('#showcase-research').isVisible(), true);
    });
});

test('header, footer, CTAs and section anchors resolve to real pages or targets', { timeout: 60_000 }, async () => {
    await browserTest(async (browser) => {
        const page = await browser.newPage();
        await page.goto(base, { waitUntil: 'networkidle' });
        const hrefs = [...new Set(await page.locator('a[href]').evaluateAll((links) => links.map((a) => a.getAttribute('href'))))];
        for (const href of hrefs) {
            if (href.startsWith('#')) { assert.equal(await page.locator(href).count(), 1, href); continue; }
            if (!href.startsWith(base)) continue;
            const url = new URL(href);
            const response = await page.request.get(url.origin + url.pathname);
            assert.ok(response.status() < 400, `${href}: ${response.status()}`);
            if (url.hash) {
                await page.goto(href);
                assert.equal(await page.locator(url.hash).count(), 1, href);
            }
        }
    });
});

test('contact form validates in browser, enforces CSRF and saves a real isolated enquiry', { timeout: 40_000 }, async () => {
    assert.ok(base.includes('127.0.0.1:8012') || process.env.PUBLIC_WEBSITE_ALLOW_CONTACT === '1', 'Contact submission requires an explicitly approved test installation');
    await browserTest(async (browser) => {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
        await page.goto(base + '/contact', { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Send message', exact: true }).click();
        assert.equal(await page.locator('form[data-contact-form]').evaluate((form) => form.checkValidity()), false);
        const noCsrf = await page.request.post(base + '/contact', { form: { name: 'Test' }, maxRedirects: 0 });
        assert.ok([302, 419].includes(noCsrf.status()), `Unexpected CSRF response ${noCsrf.status()}`);
        const html = await noCsrf.text();
        assert.ok(noCsrf.status() === 419 || (noCsrf.headers().location || '').includes('/login'), 'CSRF failure must not reach the contact handler');
        await page.getByLabel('Your name', { exact: true }).fill('Website QA visitor');
        await page.getByLabel('Email address', { exact: true }).fill('qa@example.test');
        await page.getByLabel('What can we help with?').selectOption('product');
        await page.getByLabel('Your message', { exact: true }).fill('An isolated browser test of the AcadSyntra contact enquiry workflow.');
        await page.getByRole('checkbox').check();
        await page.waitForTimeout(2100);
        await page.getByRole('button', { name: 'Send message', exact: true }).click();
        await page.waitForSelector('[data-contact-success]');
        assert.match(await page.locator('[data-contact-success] code').textContent(), /^[0-9a-f-]{36}$/);
        assert.equal(await page.locator('[data-contact-success]').isVisible(), true);
        await page.screenshot({ path: `${artifacts}/contact-success-390.png`, fullPage: true });
    });
});

test('navigation and content remain usable without JavaScript and with reduced motion', { timeout: 30_000 }, async () => {
    await browserTest(async (browser) => {
        const page = await browser.newPage({ javaScriptEnabled: false, viewport: { width: 320, height: 740 }, reducedMotion: 'reduce' });
        await page.goto(base + '/faqs');
        await page.locator('summary').first().click();
        assert.equal(await page.locator('details').first().getAttribute('open'), '');
        assert.equal(await page.locator('a.no-js-nav').isVisible(), true);
        await page.locator('a.no-js-nav').click();
        assert.ok(page.url().endsWith('#footer-navigation'));
        assert.equal(await page.locator('footer a', { hasText: 'Contact us' }).isVisible(), true);
    });
});
