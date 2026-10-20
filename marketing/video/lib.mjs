// Shared helpers for the AcadSyntra screenshot → video pipeline.
//
//   MARKETING_BASE_URL       base URL of the running demo app (default http://127.0.0.1:8123)
//   MARKETING_RUN_ID         run folder name (default: ISO timestamp)
//   MARKETING_BROWSER_CHANNEL  Playwright browser channel (default 'chrome')
//
// Prefers the installed Chrome channel so no browser download is required
// (Playwright's bundled Chromium is unsupported on some older macOS releases).

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const here = path.dirname(fileURLToPath(import.meta.url));

export const root = path.resolve(here, '..', '..'); // acadflow-web
export const baseUrl = process.env.MARKETING_BASE_URL || 'http://127.0.0.1:8123';
export const runId =
  process.env.MARKETING_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
export const artifactsDir = path.join(here, 'artifacts', runId);
export const framesDir = path.join(here, 'frames', runId);
export const authDir = process.env.MARKETING_AUTH_DIR || path.join(here, '.auth');
export const videoDir = here;

export const viewports = {
  desktop: { width: 1440, height: 900 },
  wide: { width: 1920, height: 1080 },
  tablet: { width: 834, height: 1112 },
  mobile: { width: 390, height: 844 },
};

export function demoIds() {
  const file = process.env.MARKETING_FIXTURE_FILE || path.join(root, 'storage', 'app', 'marketing-demo.json');
  if (!fs.existsSync(file)) {
    throw new Error(
      `Demo ids not found at ${file}. Run "npm run video:demo" first (seeds the demo database).`
    );
  }
  return JSON.parse(fs.readFileSync(file, 'utf8'));
}

export async function launch() {
  const attempts = [
    { channel: process.env.MARKETING_BROWSER_CHANNEL || 'chrome' },
    {}, // bundled Chromium, if installed
  ];
  let lastError;
  for (const opts of attempts) {
    try {
      return await chromium.launch({ ...opts, args: ['--disable-dev-shm-usage', '--no-sandbox'] });
    } catch (error) {
      lastError = error;
    }
  }
  throw lastError;
}

export const ROLES = {
  student: ['demo.student@acadsyntra.test', 'password'],
  lecturer: ['demo.lecturer@acadsyntra.test', 'password'],
  admin: ['demo.admin@acadsyntra.test', 'password'],
};

/** Log in each demo role and persist its storage state for reuse. */
export async function buildAuthStates(browser) {
  fs.mkdirSync(authDir, { recursive: true });

  // Each role gets its own isolated context: reusing one context would keep a
  // session and redirect /login away before the next role can sign in.
  for (const [role, [email, password]] of Object.entries(ROLES)) {
    const context = await browser.newContext({ serviceWorkers: 'block' });
    const page = await context.newPage();
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await Promise.all([
      page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 25000 }),
      page.click('button[type="submit"]'),
    ]);
    await context.storageState({ path: path.join(authDir, `${role}.json`) });
    console.log(`  auth ok: ${role} (${email})`);
    await context.close();
  }
}

export function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

/** CSS injected before every capture: freeze motion and hide noisy chrome. */
export const FREEZE_CSS = `
  *, *::before, *::after { animation-duration: 0s !important; animation-delay: 0s !important;
    transition-duration: 0s !important; transition-delay: 0s !important; caret-color: transparent !important; }
  html { scroll-behavior: auto !important; }
  [data-reveal] { opacity: 1 !important; transform: none !important; }
  ::-webkit-scrollbar { display: none !important; }
`;

/** Common selectors masked in every screenshot (volatile or private data). */
export const GLOBAL_MASKS = ['[data-marketing-mask]', '.js-last-login', '.js-notification-time'];
