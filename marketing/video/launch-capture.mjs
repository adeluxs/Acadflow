// Capture current Research Studio screens using the isolated browser fixtures.
import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';
import { chromium } from 'playwright';

const output = path.resolve(process.env.MARKETING_OUTPUT || 'marketing/video/launch/2026-10-21');
const base = process.env.MARKETING_DEMO_URL || 'http://127.0.0.1:8013';
if (!['127.0.0.1', 'localhost'].includes(new URL(base).hostname)) throw new Error('Use an isolated local demo server.');
await fs.mkdir(path.join(output, 'screens'), { recursive: true });
const previous = path.resolve(process.env.MARKETING_SOURCE_RUN || 'marketing/video/artifacts/2026-10-15T13-46-39-409Z');
const manifest = JSON.parse(await fs.readFile(path.join(previous, 'manifest.json'), 'utf8'));
const copies = {
    landing: 'public/landing__desktop__fold.png',
    dashboard: 'student/dashboard__desktop__fold.png',
    assignments: 'student/assignments-list__desktop__full.png',
    feedback: 'lecturer/submission-review__desktop__full.png',
    'mobile-dashboard': 'student/dashboard__mobile__fold.png',
};
for (const [name, source] of Object.entries(copies)) {
    if (!manifest.shots.some(shot => shot.status === 'ok' && shot.file === `raw/${source}`)) throw new Error(`Unverified screenshot: ${source}`);
    const image = sharp(path.join(previous, 'raw', source));
    const metadata = await image.metadata();
    await image.extract({ left: 0, top: 0, width: metadata.width, height: Math.min(metadata.height, name.startsWith('mobile') ? 844 : 900) })
        .png().toFile(path.join(output, 'screens', `${name}.png`));
}
const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
try {
    const page = await browser.newPage({ serviceWorkers: 'block', viewport: { width: 1440, height: 900 } });
    await page.goto(`${base}/login`);
    await page.locator('#email').fill('studio.owner@acadsyntra.test');
    await page.locator('#password').fill('StudioExample123!');
    await page.getByRole('button', { name: 'Sign in to AcadSyntra' }).click();
    await page.waitForURL(/dashboard|onboarding/);
    await page.goto(`${base}/research-studio/create`);
    await page.locator('[data-setup-controls]').waitFor({ state: 'visible' });
    await page.locator('[name="methodology"]').selectOption('interviews');
    await page.locator('[name="discipline"]').fill('Education & student experience');
    await page.locator('[data-setup-next]').click();
    await page.locator('[name="research_type_id"]').selectOption({ label: 'Independent Research Project' });
    await page.locator('[name="title"]').fill('Designing better campus study spaces');
    await page.locator('[name="objectives"]').fill('Explore how students describe the spaces they use to study, and document design considerations for future investigation.');
    await page.locator('[data-setup-next]').click(); await page.locator('[data-setup-submit]').click();
    await page.waitForURL(/\/research-studio\/[a-f0-9-]{36}$/);
    const project = page.url();
    const selected = await page.locator('[data-writing-chapter]').first().getAttribute('href');
    await page.goto(selected);
    await page.locator('.ql-editor').fill('Research purpose\n\nA thoughtful study starts with a clear question. This project explores how students describe the places they choose for independent and collaborative learning.\n\nOur approach\n\nWe will use interviews to understand participants’ experiences, record the reasoning behind our choices, and keep the evidence connected to each chapter.\n\nNext steps\n\nRefine the interview guide, confirm the research requirements, and prepare for participant recruitment.');
    await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.dataset.state === 'saved');
    await page.getByRole('button', { name: 'Focus writing', exact: true }).click();
    await page.evaluate(() => scrollTo(0, 0));
    await page.screenshot({ path: path.join(output, 'screens/writing.png'), animations: 'disabled' });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.evaluate(() => scrollTo(0, 0));
    await page.screenshot({ path: path.join(output, 'screens/mobile-writing.png'), animations: 'disabled' });
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${project}/plan`);
    const add = page.locator('form[action$="/resources"]');
    await add.locator('[name="kind"]').selectOption('instrument');
    await add.locator('[name="title"]').fill('Interview guide · student study experiences');
    await add.getByRole('button', { name: 'Add to library' }).click();
    await page.locator('.ql-editor').fill('Interview guide\n\nOpening question\nTell me about a place where you find it easy to concentrate.\n\nExplore the experience\nWhat makes this space work for you? How does it support individual or group study?\n\nFollow-up prompts\nDescribe a recent study session. What helped, and what got in the way?\n\nResearcher notes\nRecord decisions, keep source locators, and refine the guide after review.');
    await page.waitForFunction(() => document.querySelector('[data-autosave-status]')?.dataset.state === 'saved');
    await page.evaluate(() => scrollTo(0, 0));
    await page.screenshot({ path: path.join(output, 'screens/library.png'), animations: 'disabled' });
    await page.goto(`${project}/workspace`, { waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(output, 'screens/control-center.png'), animations: 'disabled' });
    await fs.copyFile('public/branding/acadsyntra-mark.svg', path.join(output, 'screens/brand.svg'));
    await fs.writeFile(path.join(output, 'sources.json'), JSON.stringify({ capturedAt: new Date().toISOString(), archivedCaptureDate: manifest.capturedAt, demoOnly: true, note: 'All accounts and academic material are demonstration fixtures. Previously verified demo screenshots plus fresh Research Studio captures.', screens: Object.keys(copies).concat(['writing', 'mobile-writing', 'library', 'control-center']) }, null, 2));
    console.log(`Launch screens ready: ${output}`);
} finally { await browser.close(); }
