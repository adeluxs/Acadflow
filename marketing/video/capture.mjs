// Capture every curated page of the AcadFlow demo as PNG screenshots.
//
//   node marketing/video/capture.mjs
//
// Requires the demo app to be running (see `npm run video:demo`) and the demo
// ids file written by MarketingDemoSeeder.

import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import {
  artifactsDir,
  authDir,
  baseUrl,
  buildAuthStates,
  demoIds,
  ensureDir,
  FREEZE_CSS,
  GLOBAL_MASKS,
  launch,
  ROLES,
  runId,
  viewports,
} from './lib.mjs';

const ids = demoIds();
const course = ids.databaseCourseId;
const aiCourse = ids.aiCourseId;
const essay = ids.taskIds.essay;
const lab = ids.taskIds.lab;

/** Curated capture manifest. */
const MANIFEST = [
  // Public
  { section: 'public', name: 'landing', path: '/', role: 'public', viewports: ['desktop', 'mobile'], folds: ['fold', 'full'], waitFor: 'body' },
  { section: 'public', name: 'login', path: '/login', role: 'public', viewports: ['desktop'], folds: ['fold'], waitFor: 'form' },
  { section: 'public', name: 'register', path: '/register', role: 'public', viewports: ['desktop'], folds: ['fold'], waitFor: 'form' },

  // Student
  { section: 'student', name: 'dashboard', path: '/dashboard', role: 'student', viewports: ['desktop', 'mobile'], folds: ['fold', 'full'], waitFor: 'main, .container, body' },
  { section: 'student', name: 'courses', path: '/courses', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'course-detail', path: `/courses/${course}`, role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'assignments-list', path: `/courses/${course}/assignments/stdview`, role: 'student', viewports: ['desktop', 'mobile'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'assignment-detail', path: `/courses/${course}/assignments/${lab}/showForStudent`, role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'submission-create-text', path: `/submissions/create?task_id=${essay}`, role: 'student', viewports: ['desktop', 'mobile'], folds: ['full'], waitFor: 'form#submissionForm' },
  { section: 'student', name: 'submission-create-file', path: `/submissions/create?task_id=${lab}`, role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'form#submissionForm' },
  { section: 'student', name: 'submissions-dashboard', path: '/submissions/dashboard', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'ai-assistant', path: '/ai-assistant', role: 'student', viewports: ['desktop'], folds: ['fold'], waitFor: 'form#assistantForm, body' },
  { section: 'student', name: 'flashcards', path: '/flashcards', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'practice-quizzes', path: '/practice-quizzes', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'study-plans', path: '/study-plans', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'mastery', path: '/student/mastery', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'deadline-coach', path: '/student/deadline-coach', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'reflections', path: '/student/reflections', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'study-notes', path: '/study-notes', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'learning-profile', path: '/student/learning-profile', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'student', name: 'profile', path: '/profile', role: 'student', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },

  // Lecturer
  { section: 'lecturer', name: 'courses', path: '/lecturer/courses', role: 'lecturer', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'lecturer', name: 'assignment-manage', path: `/courses/${course}/assignments`, role: 'lecturer', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'lecturer', name: 'assignment-create', path: `/courses/${course}/assignments/create`, role: 'lecturer', viewports: ['desktop'], folds: ['full'], waitFor: 'form' },
  { section: 'lecturer', name: 'submissions-index', path: '/lecturer/submissions', role: 'lecturer', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
  { section: 'lecturer', name: 'submission-review', path: `/lecturer/submissions/${ids.gradedSubmissionId}/review`, role: 'lecturer', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },

  // Admin
  { section: 'admin', name: 'settings', path: '/admin/settings', role: 'admin', viewports: ['desktop'], folds: ['full'], waitFor: 'body' },
];

function sha256(file) {
  return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex').slice(0, 16);
}

async function main() {
  ensureDir(artifactsDir);
  console.log(`AcadFlow capture → ${baseUrl}`);
  console.log(`Run: ${runId}\n`);

  const browser = await launch();

  // (Re)build auth states every run so cookies never go stale.
  console.log('Authenticating demo roles…');
  await buildAuthStates(browser);

  const contexts = {};
  for (const role of ['public', ...Object.keys(ROLES)]) {
    contexts[role] =
      role === 'public'
        ? await browser.newContext()
        : await browser.newContext({ storageState: path.join(authDir, `${role}.json`) });
  }

  const records = [];
  for (const shot of MANIFEST) {
    for (const viewportName of shot.viewports) {
      for (const fold of shot.folds) {
        const record = { ...shot, viewport: viewportName, fold, file: null, status: 'ok' };
        try {
          const viewport = viewports[viewportName];
          const context = contexts[shot.role];
          const page = await context.newPage();
          await page.setViewportSize(viewport);
          await page.addInitScript(() => {
            try {
              Object.defineProperty(window, 'navigator', {
                value: { ...window.navigator, serviceWorker: undefined },
                configurable: true,
              });
            } catch {}
          });
          await page.goto(`${baseUrl}${shot.path}`, { waitUntil: 'domcontentloaded', timeout: 30000 });
          await page.addStyleTag({ content: FREEZE_CSS }).catch(() => {});
          await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
          if (shot.waitFor) {
            await page.locator(shot.waitFor).first().waitFor({ state: 'visible', timeout: 12000 }).catch(() => {});
          }
          for (const action of shot.actions ?? []) {
            if (action.click) await page.locator(action.click).first().click({ timeout: 8000 }).catch(() => {});
            if (action.wait) await page.waitForTimeout(action.wait);
          }
          await page.waitForTimeout(400);
          await page.evaluate(() => document.fonts?.ready);

          const dir = path.join(artifactsDir, 'raw', shot.section);
          ensureDir(dir);
          const file = path.join(dir, `${shot.name}__${viewportName}__${fold}.png`);
          await page.screenshot({
            path: file,
            fullPage: fold === 'full',
            mask: GLOBAL_MASKS.map((sel) => page.locator(sel)),
            maskColor: '#e2e8f0',
            animations: 'disabled',
          });
          record.file = path.relative(artifactsDir, file).replaceAll(path.sep, '/');
          record.bytes = fs.statSync(file).size;
          record.hash = sha256(file);
          console.log(`  ✓ ${record.file}`);
          await page.close();
        } catch (error) {
          record.status = 'failed';
          record.error = String(error).split('\n')[0];
          console.log(`  ✗ ${shot.section}/${shot.name} (${viewportName}/${fold}): ${record.error}`);
        }
        records.push(record);
      }
    }
  }

  for (const ctx of Object.values(contexts)) await ctx.close();
  await browser.close();

  const manifest = {
    runId,
    baseUrl,
    capturedAt: new Date().toISOString(),
    demo: { course, aiCourse, essay, lab, gradedSubmission: ids.gradedSubmissionId },
    viewports,
    count: records.filter((r) => r.status === 'ok').length,
    failed: records.filter((r) => r.status !== 'ok').length,
    shots: records,
  };
  fs.writeFileSync(path.join(artifactsDir, 'manifest.json'), JSON.stringify(manifest, null, 2));

  console.log(`\nDone: ${manifest.count} captured, ${manifest.failed} failed.`);
  console.log(`Manifest: ${path.relative(process.cwd(), path.join(artifactsDir, 'manifest.json'))}`);
  if (manifest.failed > 0) process.exitCode = 1;
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
