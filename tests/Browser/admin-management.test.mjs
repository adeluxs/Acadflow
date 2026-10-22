import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';

const base = process.env.ADMIN_TEST_URL || 'http://127.0.0.1:8015';

test('management selectors defer catalogues, page results and save dependent selections', { timeout: 120_000 }, async () => {
    const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
    try {
        const context = await browser.newContext({ serviceWorkers: 'block' });
        await context.addCookies([JSON.parse(await readFile('/private/tmp/acadsyntra-studio-admin-cookie.json', 'utf8'))]);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const lookupRequests = [];
        page.on('request', (request) => { if (request.url().includes('/admin/lookups')) lookupRequests.push(request.url()); });
        assert.equal((await page.goto(`${base}/admin/users`)).status(), 200);
        assert.equal(lookupRequests.length, 0, 'initial render must not fetch form catalogues');
        const department = page.locator('form[method="GET"] [data-admin-lookup]');
        await department.locator('input').focus();
        await department.locator('select option').nth(20).waitFor({ state: 'attached' });
        assert.equal(await department.locator('select option').count(), 21);
        await department.getByRole('button', { name: 'More results' }).click();
        await department.locator('select option').nth(40).waitFor({ state: 'attached' });
        await department.locator('input').fill('Department Alpha 29');
        await department.locator('select option').filter({ hasText: 'Department Alpha 29' }).waitFor({ state: 'attached' });
        const departmentId = await department.locator('select option').filter({ hasText: 'Department Alpha 29' }).getAttribute('value');
        await department.locator('select').selectOption(departmentId);
        await page.getByRole('button', { name: 'Filter', exact: true }).click();
        await page.waitForURL(/department_id=/);
        assert.equal(new URL(page.url()).searchParams.get('department_id'), departmentId);
        assert.match(await page.locator('form[method="GET"] select[name="department_id"] option:checked').textContent(), /Alpha 29/);

        await page.goto(`${base}/admin/faculties`);
        assert.equal(await page.locator('tbody tr').count(), 25);
        await page.getByRole('button', { name: 'Add Faculty', exact: true }).click();
        const form = page.locator('#facultyCreateModal form');
        const university = form.locator('[data-admin-lookup]:has(select[name="university_id"])');
        await university.locator('input').fill('Speed Alpha');
        const alpha = university.locator('select option').filter({ hasText: 'Speed Alpha' });
        await alpha.waitFor({ state: 'attached' });
        await university.locator('select').selectOption(await alpha.getAttribute('value'));
        const dean = form.locator('[data-admin-lookup]:has(select[name="dean_id"])');
        await dean.locator('input').fill('Dean29');
        const alphaDean = dean.locator('select option').filter({ hasText: 'Alpha Dean29' });
        await alphaDean.waitFor({ state: 'attached' });
        await dean.locator('select').selectOption(await alphaDean.getAttribute('value'));
        await university.locator('input').fill('Speed Beta');
        const beta = university.locator('select option').filter({ hasText: 'Speed Beta' });
        await beta.waitFor({ state: 'attached' });
        await university.locator('select').selectOption(await beta.getAttribute('value'));
        assert.equal(await dean.locator('select').inputValue(), '', 'changing institution must clear the previous dean');
        await dean.locator('input').fill('Dean29');
        const betaDean = dean.locator('select option').filter({ hasText: 'Beta Dean29' });
        await betaDean.waitFor({ state: 'attached' });
        await dean.locator('select').selectOption(await betaDean.getAttribute('value'));
        const code = `B${Date.now().toString(36)}`;
        await form.locator('input[name="name"]').fill(`Browser faculty ${code}`);
        await form.locator('input[name="short_name"]').fill(code);
        await form.locator('input[name="code"]').fill(code);
        await form.getByRole('button', { name: 'Create faculty' }).click();
        await page.waitForURL(`${base}/admin/faculties`);
        await page.goto(`${base}/admin/faculties?q=${code}`);
        assert.match(await page.locator('tbody').textContent(), /Beta Dean29/);
        await page.locator('tbody').getByRole('link', { name: 'Edit' }).click();
        assert.match(await page.locator('select[name="dean_id"] option:checked').textContent(), /Beta Dean29/);

        await page.goto(`${base}/admin/students/import`);
        const importDepartment = page.locator('[data-admin-lookup]:has(select[name="department_id"])');
        await importDepartment.locator('input').fill('Department Alpha 29');
        await importDepartment.locator('select option').filter({ hasText: 'Department Alpha 29' }).waitFor({ state: 'attached' });
        await importDepartment.locator('select').selectOption(departmentId);
        await page.locator('select[name="semester_id"] option').filter({ hasText: 'Speed first semester' }).waitFor({ state: 'attached' });
        for (const width of [390, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            await page.goto(`${base}/admin/users`);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `users page overflowed at ${width}px`);
        }
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({ deferred_catalogues: true, paged_lookup: true, preserved_filter: true, dependent_dean: true, saved_faculty: true, scoped_semester: true, console_errors: errors }));
    } finally {
        await browser.close();
    }
});
