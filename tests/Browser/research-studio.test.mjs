import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';

const base = process.env.RESEARCH_STUDIO_TEST_URL || 'http://127.0.0.1:8013';
const artifacts = '/private/tmp/acadsyntra-studio-browser-artifacts';
const launch = () => chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
async function login(page, email = 'studio.owner@acadsyntra.test') {
    await page.goto(`${base}/login`);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('StudioExample123!');
    await page.getByRole('button', { name: 'Sign in to AcadSyntra' }).click();
    await page.waitForURL(/dashboard|onboarding|admin\/faculties/);
}
async function createProject(page, methodology, mode = 'research') {
    await page.goto(`${base}/research-studio/create?studio_mode=${mode}`);
    await page.locator('[data-setup-controls]').waitFor({ state: 'visible' });
    await page.locator('[name="methodology"]').selectOption(methodology);
    await page.locator('[name="discipline"]').fill(methodology === 'doctrinal_legal' ? 'Law' : 'Research methods');
    await page.locator('[data-setup-next]').click();
    await page.locator('[name="research_type_id"]').selectOption({ label: 'Independent Research Project' });
    await page.locator('[name="title"]').fill(`Browser ${methodology} ${Date.now()}`);
    await page.locator('[name="objectives"]').fill('Evaluate actual supplied evidence and report limitations.');
    await page.locator('[data-setup-next]').click();
    await page.locator('[data-setup-submit]').click();
    await page.waitForURL(new RegExp('/research-studio/[a-f0-9-]{36}$'));
    return page.url();
}
async function noOverflow(page) {
    const width = await page.evaluate(() => ({ viewport: innerWidth, actual: document.documentElement.scrollWidth }));
    assert.ok(width.actual <= width.viewport + 1, `Overflow: ${JSON.stringify(width)} on ${page.url()}`);
}

test('institutional options load on demand, retry safely, and preserve typed inputs', { timeout: 90_000 }, async () => {
    const browser = await launch();
    try {
        const page = await browser.newPage({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
        await login(page, 'studio.institution@acadsyntra.test');
        const lookups = [];
        page.on('request', request => { if (request.url().includes('/create/options')) lookups.push(request.url()); });
        await page.goto(base + '/research-studio/create', { waitUntil: 'networkidle' });
        assert.equal(lookups.length, 0);
        await page.locator('[name="discipline"]').fill('Preserve my research discipline');
        let failed = false;
        await page.route('**/create/options?**', route => {
            if (!failed && route.request().url().includes('kind=departments')) { failed = true; return route.abort(); }
            return route.continue();
        });
        await page.locator('#institutional-project-toggle').check();
        await page.locator('[data-option-more="departments"]').filter({ hasText: 'Retry' }).click();
        await page.locator('#institutional-department option', { hasText: 'Test Research Department' }).waitFor({ state: 'attached' });
        await page.locator('#supervisor-invitee option', { hasText: 'Test Supervisor' }).waitFor({ state: 'attached' });
        assert.equal(await page.locator('[name="discipline"]').inputValue(), 'Preserve my research discipline');
        await page.locator('[data-option-search="supervisors"]').fill('No matching lecturer');
        await page.locator('[data-option-status="supervisors"]').filter({ hasText: 'No matching options' }).waitFor();
        await noOverflow(page);
    } finally { await browser.close(); }
});

test('auth, setup and research workspaces fit mobile, tablet and desktop', { timeout: 180_000 }, async () => {
    const browser = await launch();
    try {
        await mkdir(artifacts, { recursive: true });
        const page = await browser.newPage({ serviceWorkers: 'block' });
        const errors = []; page.on('pageerror', e => errors.push(e.message));
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            for (const path of ['/login', '/register', '/forgot-password']) {
                const response = await page.goto(base + path, { waitUntil: 'networkidle' });
                assert.equal(response.status(), 200); await noOverflow(page);
            }
            await page.goto(base + '/login');
            await page.getByRole('button', { name: 'Show password', exact: true }).click();
            assert.equal(await page.locator('#password').getAttribute('type'), 'text');
            await page.getByRole('button', { name: 'Hide password', exact: true }).click();
            assert.equal(await page.locator('#password').getAttribute('type'), 'password');
        }
        await login(page);
        const url = await createProject(page, 'software');
        for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            for (const suffix of ['', '/plan', '/workspace']) {
                const response = await page.goto(url + suffix, { waitUntil: 'networkidle' });
                assert.equal(response.status(), 200); await noOverflow(page);
            }
            await page.screenshot({ path: `${artifacts}/overview-${width}.png`, fullPage: true });
        }
        await page.goto(url + '/plan'); await page.screenshot({ path: `${artifacts}/plan-desktop.png`, fullPage: true });
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});

test('rich writing autosaves and a stale second tab cannot overwrite it', { timeout: 90_000 }, async () => {
    const browser = await launch();
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        const page = await context.newPage(); await login(page);
        const url = await createProject(page, 'interviews');
        const stale = await context.newPage(); await stale.goto(url, { waitUntil: 'networkidle' });
        await page.locator('.ql-editor').fill('Actual interview interpretation supported by anonymised source notes.');
        await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.textContent.includes('Saved · Version 2'));
        await stale.locator('.ql-editor').fill('This stale draft must remain protected from overwriting newer evidence.');
        await stale.waitForFunction(() => document.querySelector('[data-autosave-status]')?.textContent.includes('Another session saved'));
        await page.reload({ waitUntil: 'networkidle' });
        assert.match(await page.locator('.ql-editor').innerText(), /Actual interview interpretation/);
        await page.getByRole('button', { name: 'Insert 3 × 3 table' }).click();
        assert.equal(await page.locator('.ql-editor table').count(), 1);
        await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.textContent.includes('Saved · Version 3'));
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await page.locator('.ql-editor table').count(), 1);
        await page.getByText('Section version history', { exact: true }).click();
        assert.ok(await page.getByText(/Version 2 ·/).count());
        await page.getByText('Export document', { exact: true }).click();
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Download DOCX', exact: true }).click();
        const download = await downloadPromise; assert.match(download.suggestedFilename(), /\.docx$/);
    } finally { await browser.close(); }
});

test('table removal targets the selected table, supports undo and persists on reload', { timeout: 120_000 }, async () => {
    const browser = await launch();
    try {
        const page = await browser.newPage({ serviceWorkers: 'block', hasTouch: true });
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await login(page); await createProject(page, 'interviews');
        const editor = page.locator('.ql-editor');
        const remove = page.getByRole('button', { name: 'Remove table', exact: true });
        const insert = page.getByRole('button', { name: 'Insert 3 × 3 table' });
        const saved = () => page.waitForFunction(() => {
            const form = document.querySelector('[data-research-autosave]');
            return form?.querySelector('[data-autosave-status]')?.dataset.state === 'saved'
                && form.querySelector('textarea[name="body"]').value === form.querySelector('.ql-editor').innerHTML;
        });
        const caretAtEnd = async (locator) => {
            await locator.click();
            await locator.evaluate(node => {
                const range = document.createRange(); range.selectNodeContents(node); range.collapse(false);
                const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
            });
        };
        await editor.waitFor();
        assert.equal(await remove.isDisabled(), true);
        await editor.fill('Paragraph before the tables.');
        await page.keyboard.press('Enter'); await page.keyboard.press('Enter');
        await page.keyboard.insertText('Paragraph after the tables.');
        await saved();
        await editor.locator('p').nth(1).click();
        await insert.click();
        await editor.locator('table td').first().click();
        await page.keyboard.insertText('Keep this first table');
        await caretAtEnd(editor.locator('p').filter({ hasText: 'Paragraph after the tables.' }));
        await page.keyboard.press('Enter');
        await insert.click();
        await editor.locator('table').last().locator('td').first().click();
        await page.keyboard.insertText('Remove this second table');
        await saved();
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await editor.locator('table').count(), 2);
        assert.equal(await editor.locator('table').first().locator('tr').count(), 3);
        assert.equal(await editor.locator('table').last().locator('tr').count(), 3);
        const version = await page.locator('[data-research-autosave] [name="expected_version"]').inputValue();
        await page.waitForTimeout(2000);
        assert.equal(await page.locator('[data-research-autosave] [name="expected_version"]').inputValue(), version, 'opening a saved table must not create an edit');
        await editor.locator('p').filter({ hasText: 'Paragraph before the tables.' }).click();
        assert.equal(await remove.isDisabled(), true);
        await editor.locator('table').last().locator('td').first().click();
        await page.waitForFunction(() => [...document.querySelectorAll('button')].some(button => button.textContent === 'Remove table' && !button.disabled));
        // Keyboard activation must preserve the selected table when focus leaves the editor.
        await remove.focus(); await remove.press('Enter');
        assert.equal(await editor.locator('table').count(), 1);
        assert.match(await editor.innerText(), /Keep this first table/);
        assert.doesNotMatch(await editor.innerText(), /Remove this second table/);
        await page.keyboard.press('ControlOrMeta+z');
        assert.equal(await editor.locator('table').count(), 2);
        assert.equal(await editor.locator('table').last().locator('tr').count(), 3);
        assert.match(await editor.innerText(), /Remove this second table/);
        await page.keyboard.press('ControlOrMeta+Shift+z');
        assert.equal(await editor.locator('table').count(), 1);
        await saved();
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await editor.locator('table').count(), 1);
        assert.match(await editor.innerText(), /Keep this first table/);
        assert.doesNotMatch(await editor.innerText(), /Remove this second table/);
        await page.setViewportSize({ width: 390, height: 844 });
        await editor.locator('table td').first().tap();
        await remove.tap();
        assert.equal(await editor.locator('table').count(), 0);
        await saved();
        await page.reload({ waitUntil: 'networkidle' });
        assert.equal(await editor.locator('table').count(), 0);
        assert.match(await editor.innerText(), /Paragraph before the tables\./);
        assert.match(await editor.innerText(), /Paragraph after the tables\./);
        assert.equal(await remove.isDisabled(), true);
        await noOverflow(page);
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});

test('chapter selection expands the writing space and focus preserves the live draft', { timeout: 120_000 }, async () => {
    const browser = await launch();
    try {
        await mkdir(artifacts, { recursive: true });
        const page = await browser.newPage({ serviceWorkers: 'block', viewport: { width: 1440, height: 1000 } });
        const errors = []; page.on('pageerror', error => errors.push(error.message));
        await login(page);
        const project = await createProject(page, 'software');
        const navigator = page.locator('[data-writing-chapters]');
        assert.equal(await navigator.evaluate(el => el.open), true);
        const chapter = page.locator('[data-writing-chapter]').nth(1);
        const href = await chapter.getAttribute('href');
        await chapter.click();
        await page.waitForURL(href);
        assert.equal(await navigator.evaluate(el => el.open), false, 'selected chapter should start with its navigator closed');
        const wide = await page.locator('.studio-writing-paper').boundingBox();
        await navigator.locator(':scope > summary').click();
        const narrow = await page.locator('.studio-writing-paper').boundingBox();
        assert.ok(wide.width - narrow.width >= 200, 'closing chapters should release the sidebar width');
        await navigator.locator(':scope > summary').click();
        for (const width of [320, 390, 768, 1024, 1280, 1440, 1920]) {
            await page.setViewportSize({ width, height: 900 });
            await noOverflow(page);
            const editor = await page.locator('.ql-editor').boundingBox();
            const document = await page.locator('.studio-writing-document').boundingBox();
            assert.ok(editor.width >= document.width - 85, `editor must use available width at ${width}px`);
            await navigator.locator(':scope > summary').click(); await noOverflow(page);
            await navigator.locator(':scope > summary').click();
            if ([390, 1440].includes(width)) await page.screenshot({ path: `${artifacts}/writing-wide-${width}.png`, fullPage: true });
        }
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.locator('.ql-editor').fill('A live draft remains intact while the workspace changes.');
        await page.locator('.ql-editor').evaluate(el => { el.dataset.draftIdentity = 'same-editor'; });
        await page.getByRole('button', { name: 'Focus writing', exact: true }).click();
        assert.equal(await page.locator('[data-writing-focus-toggle]').getAttribute('aria-pressed'), 'true');
        assert.equal(await navigator.isVisible(), false);
        assert.equal(await page.locator('[data-studio-guide]').isVisible(), false);
        assert.match(await page.locator('.ql-editor').innerText(), /A live draft remains intact/);
        assert.equal(await page.locator('.ql-editor').getAttribute('data-draft-identity'), 'same-editor');
        await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.textContent.includes('Saved · Version 2'));
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-writing-focus-toggle]').getAttribute('aria-pressed'), 'false');
        assert.equal(await navigator.isVisible(), true);
        await page.getByRole('link', { name: 'Writing tools', exact: true }).click();
        assert.equal(await page.locator('#writing-tools').evaluate(el => el.open), true);
        await page.locator('[data-editor-insert]').first().click();
        assert.equal(await page.locator('.ql-editor').getAttribute('data-draft-identity'), 'same-editor');
        await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.textContent.includes('Saved · Version 3'));
        await page.reload({ waitUntil: 'networkidle' });
        assert.match(await page.locator('.ql-editor').innerText(), /A live draft remains intact/);
        assert.ok(page.url().startsWith(project));
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
});

test('the studio guide explains the tools across project pages and works without JavaScript', { timeout: 90_000 }, async () => {
    const browser = await launch();
    try {
        const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
        const page = await context.newPage(); await login(page);
        const project = await createProject(page, 'interviews');
        for (const path of [project, `${project}/plan`, `${project}/workspace`, `${base}/research-studio`, `${base}/research-studio/create`]) {
            await page.goto(path, { waitUntil: 'networkidle' });
            const guide = page.locator('[data-studio-guide]');
            assert.equal(await guide.evaluate(el => el.open), false);
            await guide.locator(':scope > summary').click();
            for (const title of ['Chapters, writing & focus mode', 'Autosave, versions & recovering work', 'Plan, library & institutional guidelines', 'Literature, review notes & citations', 'Preview, formatting & document export']) {
                const topic = guide.getByText(title, { exact: true });
                await topic.click();
                assert.equal(await topic.evaluate(el => el.parentElement.open), true);
            }
            await noOverflow(page);
        }
        const fallback = await browser.newContext({ javaScriptEnabled: false, storageState: await context.storageState(), viewport: { width: 390, height: 844 } });
        const basic = await fallback.newPage(); await basic.goto(project);
        await basic.locator('[data-studio-guide] > summary').click();
        assert.equal(await basic.locator('[data-studio-guide]').evaluate(el => el.open), true);
        const chapters = basic.locator('[data-writing-chapters]');
        await chapters.locator('[data-writing-chapter]').nth(1).click();
        assert.equal(await chapters.evaluate(el => el.open), false);
        assert.ok(await basic.locator('textarea[name="body"]').isVisible());
        await noOverflow(basic);
        await fallback.close();
    } finally { await browser.close(); }
});

test('guidelines, coding, sample planning and supplied CSV analysis work end to end', { timeout: 120_000 }, async () => {
    const browser = await launch();
    try {
        const page = await browser.newPage({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
        await login(page); const url = await createProject(page, 'mixed_methods', 'final_year');
        await page.goto(url + '/plan');
        const add = page.locator(`form[action$="/resources"]`);
        await add.locator('[name="kind"]').selectOption('guideline');
        await add.locator('[name="title"]').fill('Example department guideline');
        await add.locator('[name="file"]').setInputFiles({ name: 'guide.txt', mimeType: 'text/plain', buffer: Buffer.from('Chapter One: Institutional Context\nChapter Two: Integrated Analysis\nReferences') });
        await add.getByRole('button', { name: 'Add to library' }).click();
        await page.getByRole('textbox', { name: 'Confirmed section headings' }).waitFor();
        assert.match(await page.locator('[name="headings"]').inputValue(), /Institutional Context/);
        await page.locator('[name="confirmed"]').check();
        await page.getByRole('button', { name: 'Apply confirmed headings' }).click();
        await page.getByText('Confirmed headings applied first.', { exact: false }).waitFor();
        // A real source document is created before an excerpt can be coded.
        await add.locator('[name="kind"]').selectOption('coding'); await add.locator('[name="title"]').fill('Anonymised source register');
        await add.locator('[name="file"]').setInputFiles([]); await add.getByRole('button', { name: 'Add to library' }).click();
        await page.getByText('Qualitative coding & themes', { exact: true }).click();
        const coding = page.locator('form[action$="/analysis/coding"]');
        await coding.locator('[name="source_uuid"]').selectOption({ label: 'Anonymised source register' });
        await coding.locator('[name="locator"]').fill('P01 lines 2–4'); await coding.locator('[name="excerpt"]').fill('A researcher-supplied excerpt for interface verification.');
        await coding.locator('[name="code"]').fill('Access barriers'); await coding.locator('[name="definition"]').fill('Statements about limited access to a service.');
        await coding.locator('[name="theme"]').fill('Access and inclusion'); await coding.locator('[name="deidentified"]').check();
        await coding.getByRole('button', { name: 'Save coded excerpt' }).click();
        await page.getByText('Qualitative coding & themes', { exact: true }).click();
        assert.equal(await page.getByRole('cell', { name: 'Access barriers', exact: true }).count(), 1);
        await page.getByText('Sample-size planning', { exact: true }).click();
        await page.getByRole('button', { name: 'Calculate & save assumptions' }).click();
        await page.getByText('completed responses: 385', { exact: false }).waitFor();
        await page.getByText('Analyse a CSV dataset', { exact: true }).click();
        const analyse = page.locator('form[action$="/analysis/describe"]');
        await analyse.locator('[name="file"]').setInputFiles({ name: 'observations.csv', mimeType: 'text/csv', buffer: Buffer.from('measurement,group\n1,A\n2,B\n3,A\n,B\n') });
        await analyse.locator('[name="deidentified"]').check(); await analyse.getByRole('button', { name: 'Compute descriptive summary' }).click();
        await page.getByText('Data completeness by column', { exact: true }).waitFor();
        await noOverflow(page); assert.match(await page.locator('.studio-table').first().innerText(), /2\.0000/);
        await page.screenshot({ path: `${artifacts}/analysis-mobile.png`, fullPage: true });
        await page.locator('[name="destination"]').selectOption({ index: 1 });
        await page.getByRole('button', { name: 'Insert saved version' }).click();
        await page.waitForURL(/section=/); assert.match(await page.locator('.ql-editor').innerText(), /Descriptive analysis/);
    } finally { await browser.close(); }
});

test('registration and resumable onboarding lead to a real first-project wizard', { timeout: 120_000 }, async () => {
    const browser = await launch();
    try {
        const page = await browser.newPage({ serviceWorkers: 'block', viewport: { width: 390, height: 844 } });
        const nonce = Date.now(); await page.goto(base + '/register');
        await page.locator('#account_type').selectOption('student'); await page.locator('#first_name').fill('New'); await page.locator('#last_name').fill('Researcher');
        await page.locator('#username').fill(`browser-${nonce}`); await page.locator('#email').fill(`browser-${nonce}@acadsyntra.test`);
        await page.locator('#password').fill('StudioExample123!'); await page.locator('#password_confirmation').fill('StudioExample123!'); await page.locator('[name="terms"]').check();
        await page.getByRole('button', { name: 'Create account and continue' }).click(); await page.waitForURL(/onboarding/);
        assert.match(await page.locator('body').innerText(), /Public identity/); await noOverflow(page);
        await page.locator('form[action$="/profile"]').getByRole('button', { name: 'Save and continue' }).click();
        await page.waitForURL(/step=3/);
        await page.getByRole('button', { name: 'Skip for now' }).click();
        await page.waitForURL(/step=4/); await page.reload();
        assert.match(await page.locator('body').innerText(), /Choose the knowledge/);
        await page.locator('[name="programme"]').fill('Public health'); await page.locator('[name="academic_level"]').fill('hnd');
        await page.locator('form[action$="/interests"]').getByRole('button', { name: 'Save and continue' }).click(); await page.waitForURL(/step=5/);
        await page.locator('form[action$="/preferences"]').getByRole('button', { name: 'Save and continue' }).click(); await page.waitForURL(/step=6/);
        await page.locator('[name="next_action"]').selectOption('final_year'); await page.getByRole('button', { name: 'Complete onboarding' }).click();
        await page.waitForURL(/research-studio\/create\?studio_mode=final_year/);
        assert.equal(await page.locator('[name="discipline"]').inputValue(), 'Public health');
        assert.equal(await page.locator('[name="research_level"]').inputValue(), 'hnd');
    } finally { await browser.close(); }
});
