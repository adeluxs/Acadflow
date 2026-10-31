# Implementation report — 31 October 2026

Scope: slow navigation and update notice (priority 1), completing the Campus Ambassador programme, homepage visibility, security and performance.
Environment: local copy with an SQLite demo database, PHP 8.3 (vendor platform check bypassed locally only), Chromium via Playwright. Production (cPanel/MySQL, real traffic) was not accessible.

## A. Previous work found and completed

The previous agent's uncommitted work (not yet in git) already covered most of the programme:

- Applications with Super Admin review.
- Referral links with attribution at web and API registration.
- Hashed anti-fraud signals.
- Study-day qualification and referral review.
- Self-reported activity reports.
- Audited rewards (wallet credit through the existing ledger, external delivery references).
- Paginated admin screens and 25 feature tests.

All 25 tests pass.

Missing or incomplete work, now done:

- **Homepage and menu.** The programme was only in the footer. It now has a main-menu link, a homepage section and a full public page.
- **Application lifecycle.**
  - No way to withdraw an application: added.
  - No profile pre-fill: added.
  - Any non-admin could apply despite the "I am a student" terms: now student accounts only.
  - Faculty and an optional profile link were not captured: added.
  - The application had no institution link: added, set only from an approved membership.
- **Admin-managed activities and campaigns.** Did not exist. Added create/edit/open/close, a dashboard list, report linking and an evidence-required rule.
- **Evidence uploads.** Only external links were supported. Private, content-checked files are now supported.
- **Admin tools.**
  - No institution or date filters: added.
  - No "withdrawn" filter: added.
  - No programme audit view: added.
  - No confirmations on sensitive actions: added.

## B. Homepage integration

- Main navigation link **Campus Ambassador** on desktop and in the mobile menu. It points to the public `/ambassadors` page and needs no login.
- A homepage section "Become an AcadSyntra Campus Ambassador", placed after the product sections and before the FAQ and final call to action, with **Become a Campus Ambassador** and **Learn more** buttons.
  - No images or animation, so there is no layout shift.
  - Responsive (screenshots at 1440 px and 390 px; no horizontal scroll).
- The public page covers:
  - what the programme is, what ambassadors do and why to join
  - six steps to join, and who can apply
  - 10 FAQs, with FAQ structured data and Open Graph/Twitter metadata
- The call to action follows the visitor's state:
  - guest → join
  - student with no application → apply
  - pending → View application status
  - approved → Open ambassador dashboard
  - rejected/withdrawn → Update and resubmit
  - suspended → View programme status
- `/ambassadors/join` keeps the destination through sign-in (intended URL). After registration and onboarding, the dashboard offers a "Finish your Campus Ambassador application" link.
- All links hide when the **Campus ambassadors** feature is disabled.

## C. Navigation bug: root causes

Confirmed by reproduction in Chromium, before and after the fix.

1. **The full-screen loader never cleared after a cancelled navigation.**
   - Cause: `resources/js/global-loader.js` showed a blocking overlay (`pointer-events: auto`, z-index 100000) on every link click and removed it only on `pageshow`. If the browser did not leave the page, the overlay stayed until a manual reload. This happens when the user answers "Stay" on an unsaved-work prompt (Research Studio registers one), or on Esc/Stop, a 204 response or an attachment.
   - Reproduced: after a cancelled navigation, the loader was `visible` indefinitely.
   - Fixed: loader state is `hidden`, the progress bar and submit buttons are restored, and a 10 s watchdog for links (45 s for form posts) releases the overlay if the browser is still waiting.
2. **A deploy made a new service worker take over the open page.**
   - Cause: `public/serviceworker.js` called `skipWaiting()` on install. The worker update check runs on navigation, so a deploy installed and activated the new worker during the user's navigation. It took control (`controllerchange`) and raised the "New version available" popup, which had no dismiss.
   - Reproduced: `controllerchange` fired with no user action.
   - Fixed: the new worker waits and the page is unaffected. A non-blocking "AcadSyntra has been updated" notice offers **Refresh now / Later**:
     - Later is remembered per version for the tab session.
     - Unsaved forms get a warning first.
     - The page reloads exactly once.
     - Update checks are throttled to every 30 minutes and failures are ignored.
3. **Two different worker scripts were registered at the same scope.**
   - Cause: the onboarding layout and non-PWA browser-push code registered `/pushworker.js` while app pages registered `/serviceworker.js`. Each switch replaced the active worker and could trigger the update popup.
   - Fixed: browser push reuses whichever worker is active.
4. **Background push housekeeping raised the loader on every page.**
   - Cause: opted-in browsers sent a non-silent POST `/browser-push/subscribe` on every page load, which triggered the "Processing request…" overlay.
   - Fixed: the request is silent and runs once per tab session.

**Not reproduced: the "several minutes" delay.** Locally, server responses for the main pages take 9–60 ms, and navigation with a pending update took 137–191 ms per page. A multi-minute wait therefore most likely also involves production server time (PHP worker limits on shared hosting, slow queries on real data volumes). Causes 1 and 2 would make any such slow response look frozen.

Recommended next step: in production, enable `PERFORMANCE_LOG_SLOW_REQUESTS=true` (optionally with `PERFORMANCE_SLOW_REQUEST_MS=1000`) for a day and check the log for the slow routes.

## D. Security improvements

- **Referral links leaked whether a code was active.** Unknown or suspended codes returned 404, which revealed which codes were valid. Every guest now gets the same redirect, and only valid codes are remembered.
- **Server-side eligibility.** Applications are limited to students. Status, review fields, referral code and the institution link are not mass-assignable, and the institution link comes from an approved membership only.
- **Evidence files.**
  - The type is checked by content, not by extension. A script renamed `.png` is rejected (tested).
  - 5 MB limit; random file names on the private disk; no user-controlled paths.
  - Files are served only to the owner and Super Admin, with `nosniff`, `private, no-store` and a sandboxed Content-Security-Policy. Other users get 404.
  - Retried submissions store no duplicate file.
- **External links.** Resource and profile links must be HTTPS. Report links open with `noopener noreferrer nofollow` and are labelled as external.
- **Activity management** is limited to Super Admin by route middleware and controller checks (tested against student and university admin accounts).
- **Audit log** now also records withdrawals and activity creation and changes. Existing audits are unchanged: approve, reject, suspend, reinstate, referral review, report review, award, fulfil and cancel.
- **Confirmations** were added for reject, suspend, disqualify, cancel award and fulfil/credit.

## E. Performance

Local SQLite measurements: median of three requests, using the new `Server-Timing` db metric.

| Page | Before (ms / queries) | After (ms / queries) |
|---|---|---|
| `/` (guest) | 14.1 / 2 | 14.9 / 2 |
| `/ambassadors` (guest) | 13.1 / 2 | 12.9 / 2 |
| `/dashboard` (student) | 59.7 cold, 37.2 warm / 27 | 34.5 / 27 |
| `/research-studio` | 26.5 / 7 | 21.1 / 7 |
| `/research-studio/create` | 22.2 / 8 | 20.9 / 8 |
| `/knowledge-hub` | 24.3 / 19 | 24.4 / 19 |
| `/ambassador` (approved, with activity, report, rewards) | n/a | 19.5 / 13 |
| `/admin/ambassadors` | n/a | 24.4 / 15 |

- New features add no queries to public pages (feature status and settings are already cached per request).
- Ambassador lists are paginated, relations are eager-loaded, and the activity list on the dashboard is capped at 12.
- Navigation: fewer requests per page for opted-in push browsers. There is no service-worker re-registration on every load, and update checks run at most every 30 minutes.
- No significant server-side bottleneck showed on this dataset. Production volumes were not measured, so no production speed-up is claimed.

## F. Key files

**Navigation fix**
- `public/serviceworker.js`: no `skipWaiting()` on install; `WORKER_VERSION`; `GET_VERSION` message; fixed an undefined cache name.
- `resources/views/layouts/app.blade.php`: non-blocking update notice and throttled registration and update checks.
- `resources/js/global-loader.js`: cancelled-navigation detection and the watchdog.
- `resources/js/performance.js`: restores the page after a cancelled navigation.
- `resources/js/browser-push.js`: silent, reuses the active worker, syncs once per session.
- `public/build/*`: rebuilt.

**Programme**
- Controllers: `app/Http/Controllers/AmbassadorController.php`, `Admin/AmbassadorManagementController.php`.
- Services: `app/Services/AmbassadorService.php`.
- Models: new `app/Models/AmbassadorTask.php`; changes to `AmbassadorApplication` and `AmbassadorReport`.
- Routes: `routes/ambassadors.php`, `routes/web.php`.
- Migration: `database/migrations/2026_10_31_150000_add_ambassador_activities_and_institution_links.php` (additive).
- Views: `resources/views/ambassadors/*`, `admin/ambassadors/*` (including the new `tasks.blade.php`).
- Styles: `public/css/ambassadors.css`.

**Homepage**
- `resources/views/landing.blade.php`, `layouts/public.blade.php`, `public/ambassadors.blade.php`.
- `resources/css/public.css`.
- `app/Services/PublicWebsite.php` (programme FAQ), `app/Http/Controllers/PublicPageController.php`, `config/public_website.php`.

**Instrumentation**
- `app/Http/Middleware/RequestCorrelationId.php`: query count and time in `Server-Timing` when `PERFORMANCE_SERVER_TIMING=true`.

**Tests and docs**
- `tests/Feature/AmbassadorProgrammeCompletionTest.php` (12 tests).
- `tests/Browser/navigation-update.test.mjs`.
- Two expectations updated in `AmbassadorProgrammeTest`.
- `docs/CAMPUS_AMBASSADORS.md`, `CHANGELOG.md`.

## G. Testing

- **Ambassador feature suites:** 37 tests passed (programme 15, anti-fraud 10, completion 12).
- **Full PHPUnit suite:** 631 tests, 621 passed. The 10 errors are push-notification tests that need the GMP or BCMath PHP extension, which is missing from this sandbox. They are unrelated to these changes.
- **Browser, navigation (`tests/Browser/navigation-update.test.mjs`), all passed:**
  - a cancelled navigation leaves no overlay
  - a deploy shows the notice without a takeover
  - five navigations with a pending update work normally
  - Later is remembered across pages
  - the unsaved-form warning appears, then exactly one reload
  - no page errors
- **Browser, ambassador end-to-end:** apply, admin approves, ambassador sees the activity, submits a report with a PNG, the owner can open the file and another user gets 404.
- **Screenshots** checked at 1440 px and 390 px: homepage section, menu, public page, application form, ambassador dashboard and admin pages.

## H. Outstanding items and decisions

- **Production diagnosis of slow responses.** Enable slow-request logging (section C), and check PHP worker limits and MySQL slow queries on the host.
- **Deploying the worker change.** A browser tab that was already open shows the old "New version available" popup once more. After that refresh, the new notice and behaviour apply.
- **Deploy steps:**
  1. `php artisan migrate --force`
  2. `php artisan optimize:clear` (or `config:cache` and `route:cache`)
  3. Upload `public/build`, `public/css/ambassadors.css` and `public/serviceworker.js`.
- **Business decisions still needed:** reward amounts and rules (none are invented or advertised); any certificate generation (not implemented); a leaderboard (not added, for privacy); data-retention periods for programme evidence.
- **Not done:** the browser-push PHP tests were not run here (sandbox limitation). Load testing against production MySQL was not performed.
