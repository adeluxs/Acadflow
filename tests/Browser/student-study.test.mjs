import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';

const base = process.env.STUDY_TEST_URL || 'http://127.0.0.1:8016';

test('student reads a source, resumes saved work, practises and imports a private note', { timeout: 120_000 }, async () => {
    const fixture = JSON.parse(await readFile('/private/tmp/acadsyntra-studio-study-fixture.json', 'utf8'));
    const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
    try {
        const context = await browser.newContext();
        await context.addCookies([fixture.cookie]);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        assert.equal((await page.goto(`${base}/study`)).status(), 200);
        await page.locator('#materials').getByRole('button', { name: 'Study →', exact: true }).click();
        await page.waitForURL(`${base}${fixture.workspace}`);
        assert.match(await page.locator('#explanation').textContent(), /An atomic value is a single value/);
        await page.locator('[data-study-source]').evaluate(element => {
            const range = document.createRange();
            range.selectNodeContents(element);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            element.dispatchEvent(new MouseEvent('mouseup', { bubbles: true }));
        });
        assert.match(await page.locator('#study-selection').inputValue(), /Every field holds one atomic value/);
        await page.getByRole('link', { name: /my saved practice/ }).click();
        await page.waitForURL(`${base}${fixture.quiz}`);
        await page.locator('input[name="answers[0]"][value="Atomic values"]').check();
        await page.locator('input[name="answers[1]"][value="Primary keys"]').check();
        await page.getByRole('button', { name: 'Submit answers', exact: true }).click();
        await page.locator('#feedback').waitFor();
        assert.match(await page.locator('#feedback').textContent(), /1 of 2 correct/);
        assert.match(await page.locator('#feedback').textContent(), /Revisit this/);
        assert.match(await page.locator('#feedback').textContent(), /Each field holds one value/);
        await page.goto(`${base}/dashboard`);
        assert.match(await page.locator('[data-study-today]').textContent(), /1\/2/);
        await page.goto(`${base}/study`);
        const form = page.locator('form[action$="/study/notes"]');
        const title = `My private browser note ${Date.now()}`;
        await form.locator('select[name="course_id"]').selectOption(String(fixture.course_id));
        await form.locator('input[name="title"]').fill(title);
        await form.locator('textarea[name="content"]').fill('First normal form uses one atomic value in each field. Repeating groups move to separate rows.');
        await form.getByRole('button', { name: 'Save and start studying →' }).click();
        await page.waitForURL(/\/study\/sessions\//);
        assert.match(await page.locator('[data-study-workspace]').textContent(), /Your private note/);
        assert.match(await page.locator('[data-study-source]').textContent(), /Repeating groups move/);
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            for (const path of ['/study', fixture.workspace, fixture.quiz, '/dashboard']) {
                assert.equal((await page.goto(`${base}${path}`)).status(), 200);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${path} overflowed at ${width}px`);
                assert.equal(await page.locator('h1').first().evaluate(el => getComputedStyle(el).fontWeight), '900', `${path} should retain its stylesheet`);
            }
        }
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ selected_passage: true, saved_explanation: true, graded_feedback: true, dashboard_progress: true, private_note_saved: true, viewports: [390, 1440], console_errors: errors }));
    } finally {
        await browser.close();
    }
});
