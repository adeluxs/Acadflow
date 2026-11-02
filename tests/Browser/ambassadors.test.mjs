import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { readFile, mkdir } from 'node:fs/promises';

const base = process.env.AMBASSADOR_TEST_URL || 'http://127.0.0.1:8027';
const fixture = JSON.parse(await readFile('/private/tmp/acadsyntra-ambassadors-fixture.json', 'utf8'));
const artifacts = '/private/tmp/acadsyntra-ambassadors-qa';

test('ambassador recruitment, application, reporting and admin rewards work on mobile and desktop', { timeout: 300000 }, async () => {
    const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
    await mkdir(artifacts, { recursive: true });
    const errors = [];
    const contexts = [];
    async function pageFor(role) {
        const context = await browser.newContext();
        contexts.push(context);
        if (role) await context.addCookies([fixture[role]]);
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        return page;
    }
    async function checkLayout(page, path, label) {
        for (const width of [320, 390, 1440]) {
            await page.setViewportSize({ width, height: 1000 });
            const response = await page.goto(base + path, { waitUntil: 'networkidle' });
            assert.equal(response.status(), 200, `${label} at ${width}`);
            await page.screenshot({ path: `${artifacts}/${label}-${width}.png`, fullPage: true });
            const overflow = await page.evaluate(() => ({
                width: document.documentElement.scrollWidth,
                elements: [...document.querySelectorAll('body *')].filter(el => el.getBoundingClientRect().right > innerWidth + 1)
                    .slice(0, 12).map(el => ({ tag: el.tagName, class: el.className, width: el.getBoundingClientRect().width })),
            }));
            assert.ok(overflow.width <= width + 1, `${label} overflows at ${width}: ${JSON.stringify(overflow)}`);
            assert.ok(await page.locator('.amb-shell h1, .page-heading h1').count() > 0 || await page.locator('h1').count() > 0);
        }
    }
    try {
        const publicPage = await pageFor();
        await checkLayout(publicPage, '/ambassadors', 'recruitment');
        await publicPage.goto(base + '/r/' + fixture.referralCode);
        assert.match(publicPage.url(), /\/register$/);
        assert.equal(await publicPage.getByText('An ambassador invited you.', { exact: false }).count(), 1);

        const applicant = await pageFor('applicant');
        await checkLayout(applicant, '/ambassador', 'application');
        await applicant.getByLabel('Institution', { exact: true }).fill('Preview Campus');
        await applicant.getByLabel('Department / course').fill('Computer Science');
        await applicant.getByLabel('Level / year of study').fill('200 level');
        await applicant.getByLabel('Contact phone with country code').fill('+2348000000000');
        await applicant.getByLabel('Students you could help').fill('30');
        await applicant.getByLabel('Your first-month plan').fill('I will host a short study demo and help interested classmates get started.');
        await applicant.getByRole('checkbox').check();
        await Promise.all([applicant.waitForURL(base + '/ambassador'), applicant.getByRole('button', { name: 'Submit application', exact: true }).click()]);
        await applicant.getByText('Your application is with the platform team.', { exact: false }).waitFor();

        const ambassador = await pageFor('ambassador');
        await checkLayout(ambassador, '/ambassador', 'ambassador');
        assert.match(await ambassador.getByRole('link', { name: 'Share on WhatsApp' }).getAttribute('href'), /^https:\/\/wa.me\/\?text=/);
        await ambassador.getByLabel('Title', { exact: true }).fill('Browser demo session');
        await ambassador.getByLabel('Participants', { exact: true }).fill('12');
        await ambassador.getByLabel('What happened? What did you learn?').fill('We demonstrated the study tools and collected feedback from classmates.');
        await ambassador.getByRole('button', { name: 'Submit report', exact: true }).click();
        await ambassador.getByRole('heading', { name: 'Browser demo session', exact: true }).waitFor();

        const referred = await pageFor('referred');
        await checkLayout(referred, '/ambassador', 'invitation-progress');
        await referred.getByText('Email verified.', { exact: true }).waitFor();

        const admin = await pageFor('admin');
        await checkLayout(admin, '/admin/ambassadors?status=approved', 'admin-list');
        await checkLayout(admin, '/admin/ambassadors/' + fixture.application, 'admin-review');
        const referral = admin.locator('#referral-reviews article').first();
        await referral.getByLabel('Private findings and verification evidence').fill('Confirmed a separate student using independent campus records.');
        await referral.getByRole('checkbox').check();
        await referral.getByRole('button', { name: 'Qualify referral', exact: true }).click();
        await admin.getByText('Referral review recorded.', { exact: true }).waitFor();
        const report = admin.locator('article').filter({ has: admin.getByRole('heading', { name: 'Browser demo session', exact: true }) });
        await report.getByLabel('Review note', { exact: true }).fill('Reviewed the demonstration and feedback.');
        await report.getByRole('button', { name: 'Approve report' }).click();
        await admin.getByText('Activity report reviewed.', { exact: true }).waitFor();
        await admin.getByLabel('Award reference', { exact: true }).fill('browser-demo-' + Date.now());
        await admin.getByLabel('Title', { exact: true }).fill('Browser study reward');
        await admin.getByLabel('Amount', { exact: false }).fill('100');
        await admin.getByLabel('Reason and agreed delivery details').fill('A study credit for the reviewed campus demo.');
        await admin.getByRole('button', { name: 'Record award', exact: true }).click();
        await admin.getByRole('button', { name: 'Credit study wallet now', exact: true }).click();
        await admin.getByText('Reward fulfilled.', { exact: true }).waitFor();
        await admin.getByLabel('Reward type', { exact: false }).selectOption('performance_bonus');
        await admin.getByLabel('Qualified referral ID', { exact: false }).fill(String(fixture.referralId));
        await admin.getByLabel('Award reference', { exact: true }).fill('browser-referral-' + Date.now());
        await admin.getByLabel('Title', { exact: true }).fill('Reviewed referral bonus');
        await admin.getByLabel('Amount', { exact: false }).fill('100');
        await admin.getByLabel('Reason and agreed delivery details').fill('A bonus for one independently reviewed referral.');
        await admin.getByRole('button', { name: 'Record award', exact: true }).click();
        const bonus = admin.locator('#rewards article').filter({ has: admin.getByRole('heading', { name: 'Reviewed referral bonus', exact: true }) });
        await bonus.getByLabel('Payment / delivery reference', { exact: false }).fill('test-external-delivery');
        await bonus.getByRole('button', { name: 'Mark delivered', exact: true }).click();
        await admin.getByText('Reward fulfilled.', { exact: true }).waitFor();
        await ambassador.reload();
        await ambassador.getByRole('heading', { name: 'Browser study reward', exact: true }).waitFor();
        assert.equal(await ambassador.getByText('independent campus records', { exact: false }).count(), 0);
        await ambassador.getByRole('heading', { name: 'Reviewed referral bonus', exact: true }).waitFor();
        assert.deepEqual(errors, []);
    } finally {
        await Promise.all(contexts.map(context => context.close()));
        await browser.close();
    }
});
