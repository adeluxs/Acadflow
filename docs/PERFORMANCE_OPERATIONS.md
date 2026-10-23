# Navigation performance and capacity verification

Use PHP 8.2+ (the system PHP on this Mac is 7.3 and cannot run this application). Local validation used `/Applications/MAMP/bin/php/php8.5.2/bin/php`.

## Production configuration

Run on PHP-FPM with OPcache, a supported MySQL/MariaDB server, and Redis where available. The built-in PHP development server is a diagnostic tool, not a production capacity baseline. Keep `APP_DEBUG=false`. Set the PHP-FPM worker count from measured worker memory and available RAM; the database connection ceiling must accommodate web workers, queue workers, scheduler and operational headroom. Do not enable persistent PDO connections just to increase concurrency.

```dotenv
CACHE_STORE=redis
QUEUE_CONNECTION=redis
AI_QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=360
DB_QUEUE_RETRY_AFTER=360
SESSION_DRIVER=database
PERFORMANCE_SERVER_TIMING=false
PERFORMANCE_LOG_SLOW_REQUESTS=true
PERFORMANCE_SLOW_REQUEST_MS=1000
PERFORMANCE_QUEUED_EXPORT_BYTES=250000
RESEARCH_EXPORT_DISK=local
```

Database sessions preserve the current concurrent-session enforcement, which queries the sessions table. The new composite session index supports that check. Redis sessions would require extending that enforcement first. Database-backed queues/cache remain supported where Redis is unavailable. Reservation expiry is at least 360 seconds so a second worker cannot reserve a running 240-second export or 300-second worker task prematurely. Set SQS visibility above the worker timeout separately if using SQS.

For multiple application nodes, configure a **private shared filesystem disk** for `RESEARCH_EXPORT_DISK` and share the cache used for export status and locks. Local export storage works when the web server and workers share that filesystem. Existing filesystem adapters remain unchanged. No private HTML, session response or authenticated API response should be cached by a CDN.

After reviewing and deploying the changes:

```sh
php artisan migrate --force
npm ci
npm run build
npm run performance:bundles
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Run separate supervised processes so expensive work cannot occupy every worker. These are starting configurations, not capacity promises; adjust process counts after measurement:

```sh
php artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=300 --max-jobs=1000 --memory=192
php artisan queue:work redis --queue=notifications --sleep=1 --tries=5 --timeout=300 --max-jobs=1000 --memory=192
php artisan queue:work redis --queue=ai --sleep=1 --tries=3 --timeout=300 --max-jobs=200 --memory=256
php artisan queue:work redis --queue=indexing,analytics --sleep=1 --tries=3 --timeout=300 --max-jobs=1000 --memory=192
php artisan queue:work redis --queue=exports --sleep=1 --tries=2 --timeout=300 --max-jobs=100 --memory=384
```

Use the connection actually configured on the host instead of `redis` when necessary. Preserve a default worker even during large AI/export workloads. Keep the scheduler running once per minute (`php artisan schedule:run`); it includes daily pruning of private export artifacts older than two days. Export status expires after one day. Monitor queue age, failed jobs, worker RSS, PHP-FPM queue depth, CPU, database active connections/locks and Redis latency/evictions. Retry failed jobs after resolving their cause.

Serve Vite's hashed `/build/assets/` directly through the web server/CDN with compression and `Cache-Control: public, max-age=31536000, immutable`. Use revalidation or shorter caching for unhashed public assets, service workers and manifests. Apply these rules only to public static locations. Existing service workers continue to avoid caching authenticated HTML/API data. Shared layout logos use the existing 708-byte SVG rather than the 26-KB raster version.

## Automated checks

```sh
php vendor/bin/phpunit
php scripts/check-performance-optimization.php
php scripts/check-mysql-identifiers.php
npm run build
npm run performance:bundles
npm run performance:harness
node --test tests/Browser/loader-logout.test.mjs
```

The bundle check follows **static imports**, so moving an optional dependency into another statically imported file cannot bypass the 8-KiB gzip JavaScript budget. Global CSS has a 25-KiB gzip budget. Feature tests cover bounded admin pages, deferred tenant-scoped lookups, small creation records, resource/reference pagination, active-chapter history pagination, queued export deduplication, requester-only access, failure/retry and artifact cleanup.

The loader/logout browser check uses local fixtures and Chrome (override `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` if needed). It verifies centered branding, navigation, canceled actions, downloads, duplicate submissions and stalled notification APIs. After preparing and starting the isolated server below, set `LOGOUT_TEST_URL=http://127.0.0.1:8013` for its real authenticated logout check. Logout never waits for browser push cleanup; the server removes this session's tracked push subscription and invalidates the session in the normal POST request.

## Isolated browser fixtures and profiling

Never run load fixtures against a development or production database containing user data. `prepare-research-studio.php`, `prepare-load.php` and `benchmark.php` reject databases outside the isolated test namespace.

Set these variables in the shell used for every fixture/server command (use an absolute PHP 8.2+ binary where needed):

```sh
export APP_ENV=testing
export APP_CONFIG_CACHE=/private/tmp/acadsyntra-studio-config.php
export APP_ROUTES_CACHE=/private/tmp/acadsyntra-studio-routes.php
export DB_CONNECTION=sqlite
export DB_DATABASE=/private/tmp/acadsyntra-studio-perf.sqlite
export CACHE_STORE=file
export CACHE_PREFIX=studio_performance_
export SESSION_DRIVER=database
export QUEUE_CONNECTION=database
export AI_QUEUE_CONNECTION=database
export MAIL_MAILER=array
export PERFORMANCE_SERVER_TIMING=true
php tests/Browser/prepare-research-studio.php
php scripts/performance/prepare-load.php
php artisan route:cache
```

Start `php artisan serve --host=127.0.0.1 --port=8013 --no-reload`, or use PHP's multiworker server on supported operating systems. **The Laravel router must run from the public directory**:

```sh
cd public
PHP_CLI_SERVER_WORKERS=4 php -d opcache.enable_cli=1 -S 127.0.0.1:8013 -t . ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

The shell variables above must be inherited by the server. Verify `/login` contains the actual email/password form before benchmarking. The load runner checks expected authenticated page content in addition to status, preventing PHP error documents with HTTP 200 from being counted as successes.

In a separate shell:

```sh
node --test tests/Browser/research-studio.test.mjs
PERFORMANCE_ASSERT_BUDGETS=1 npm run performance:browser
```

Set `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` when Chrome is elsewhere. Browser checks cover 320/390/768/1440-pixel layouts, onboarding, research/final-year creation, autosave/version conflicts, guidelines, supplied CSV analysis, downloads and deferred lookup failures/retries. Metrics include navigation TTFB, DOM-ready, FCP, LCP, layout shifts and resource requests. An interaction observer is included, but a zero observed event duration is **not** a field INP measurement.

For query shapes and repeated request timings, with the isolated environment variables still set:

```sh
php scripts/performance/benchmark.php --user=1 --route=/research-studio/create --runs=10
```

This separates application bootstrap from repeated in-process request timings. It is not a substitute for browser/HTTP measurements.

## Load testing

The load fixture creates 1,000 separate authenticated test users/sessions and stores encrypted test cookies in `/private/tmp/acadsyntra-studio-load-cookies.json` with mode 0600. Never commit this file or reuse these sessions outside the isolated server. Each virtual user navigates among project lists, Research Studio creation and Final-Year Project creation with think time. Status, content validity, complete response latency, throughput, errors, peak requests in flight and optional server timing are recorded. Cookies and bodies are excluded from the results.

```sh
PERFORMANCE_RAMP_MS=30000 PERFORMANCE_HOLD_MS=60000 PERFORMANCE_THINK_MS=5000 npm run performance:load
```

This runs 100, 500 and 1,000 virtual-user stages. Defaults include cooldown between stages and 5-second request timeouts. Override `PERFORMANCE_LEVELS`, `PERFORMANCE_TIMEOUT_MS`, `PERFORMANCE_COOLDOWN_MS` and `PERFORMANCE_LOAD_OUTPUT` when needed. `PERFORMANCE_ASSERT_BUDGETS=1` makes error rates above 1% or successful-request p95 above 1.5 seconds fail the run. Production API p95 must be measured independently against representative authenticated APIs; these HTML page checks do not prove an API's 300-ms target.

Repeat on a staging environment matching production PHP-FPM, database, Redis, storage, dataset sizes and network conditions. Use `PERFORMANCE_ALLOW_REMOTE=1` only for your authorized staging host and supply staging-created session fixtures with `PERFORMANCE_COOKIES`. Local fixture sessions cannot authenticate on another environment. Include heavier read/write mixes, AI/provider stubs with realistic latency, file processing and export workers, and sustained runs of at least 15 minutes. Track database plans, rows examined, lock waits, PHP-FPM listen queue/worker RSS, Redis and queue age alongside client metrics. Stop at rising errors, queue growth or exhausted resources. Size infrastructure from these results; do not extrapolate a short laptop run into a production 1,000-user guarantee.

## Remaining deployment-specific work

No live deployment, Redis/MySQL service, CDN or production monitoring connection was changed. Indexes were validated on isolated SQLite; review MySQL execution plans and index build impact on staging before rollout. Large PDF/DOCX exports are queued, and AI validation already uses its AI queue. Small uploads and bounded CSV calculations retain their existing synchronous behavior; PDF/DOCX guideline extraction remains synchronous with existing file/archive limits. If real monitoring identifies guideline extraction or full reviewed-library synthesis as significant costs, move them to dedicated jobs using the same status/retry pattern. No meaningful field INP or long-duration production capacity measurement is claimed by the local checks.


## Admin management optimization — 2026-10-22

Users, faculties, institutions, audit logs, commercial accounts, pricing history, lecturer assignment and student import forms now use bounded records or deferred authorised searches. User filters support name/email, role, status and department, and retain their query string when paging. Organisation administrators filter approved memberships rather than a person's self-declared primary department. Their existing update policy still applies, and permitted account-edit links direct them to membership management.

The shared admin lookup returns at most 20 options plus a next-page marker, with only `id` and `label`. Search is debounced and pending requests are cancelled; dependent dean/semester selections clear when the institution/department changes. Catalogues load on focus/search. Permissions and membership revocations are checked on every request; responses are private and not stored. Ordinary pages do not load the optional 0.89-KB gzip lookup module. The global JS dependency graph remains below 8 KiB gzip.

Faculties use department counts instead of hydrating department trees. Audit history uses 50-row previous/next pagination, indexable inclusive date bounds and short display previews; complete old/new values remain in the database. Action filtering accepts arbitrary recorded action names, with common suggestions, avoiding an entire-history distinct scan. Accounts use 20-row pages and pricing history uses 50-row pages. Account setup independently reloads the selected account, including off-page balances, currency and terms. Revenue reporting streams allocations in chunks of 500 and retains each allocation's reversal clamp and currency filter; financial balances and permissions are never cached. AI revenue/margin and department membership cards use combined aggregates.

Recent authenticated activity skips redundant database writes during the five-minute tracking window. Conditional updates still serialize concurrent requests, and a visit just after midnight records the new activity day. Audit writes inspect schema compatibility once rather than four times. Settings editing uses the existing batched settings resolver, retaining explicit global settings for platform administrators and institution overrides for institution administrators.

### Local measurements

The fixture contains 120 faculties, 360 departments, 250 lecturers, 250 audit events and 60 commercial accounts. Measurements are medians of three warm, sequential full HTTP renders using SQLite in memory and the feature-test Vite fake. They measure local application rendering, not a production capacity or browser latency guarantee. Raw samples, response sizes and hydrated model counts are in [the benchmark report](performance/admin-management-2026-10-22.json).

| Page | Before median | After median | Queries before → after | HTML before → after |
| --- | ---: | ---: | ---: | ---: |
| Users | 62.83 ms | 36.31 ms | 6 → 4 | 126,682 → 70,218 bytes |
| Faculties | 106.27 ms | 39.89 ms | 8 → 5 | 141,963 → 64,083 bytes |
| Institutions | 32.71 ms | 34.91 ms | 5 → 4 | 61,076 → 61,717 bytes |
| Audit logs | 80.47 ms | 43.20 ms | 9 → 4 | 135,247 → 91,529 bytes |
| Monetization | 107.25 ms | 45.67 ms | 18 → 16 | 116,419 → 88,857 bytes |

The institution page was already paginated; its additional search controls add a small amount of markup, and no render-time improvement is claimed. The faculties page now hydrates 25 faculties and one institution in this fixture, compared with 120 faculties, 360 departments, 62 institutions and 250 possible deans. The users page no longer hydrates all 360 department options. Audit actor hydration drops from 300 to at most 51 for its 50 visible rows and pagination lookahead. Eighty cold settings are resolved in a bounded number of reads rather than one cache/database round trip per setting.

The restartable `2026_10_22_190000_add_admin_management_indexes` migration adds ordering/lookup indexes for users, institutions, faculties, departments and audit history. SQLite query-plan tests confirm that newest-user pagination uses the covering index without a temporary order sort. The migration tolerates partially completed index creation. Apply it on the live host with `php artisan migrate --force`, then run the build/cache/worker commands above. Review MySQL query plans and index build impact on a representative deployment; local SQLite timings do not establish MySQL production throughput. Leading-wildcard search, deep offset pages and whole-history financial totals can still grow with data volume.

### Reproduce the admin checks

```sh
ADMIN_PERFORMANCE_REPORT=/private/tmp/acadsyntra-admin-samples.json php vendor/bin/phpunit tests/Feature/AdminManagementPerformanceTest.php
```

For the real-browser check, use the isolated environment variables documented above, with `DB_DATABASE=/private/tmp/acadsyntra-studio-admin-speed.sqlite`, a separate routes/config cache path, and `APP_URL=http://127.0.0.1:8015`. Run `php tests/Browser/prepare-admin-management.php` and wait for it to finish before starting the test. It creates a disposable test session in a mode-0600 file. Start PHP from `public/` with the Laravel router, then run:

```sh
ADMIN_TEST_URL=http://127.0.0.1:8015 node --test tests/Browser/admin-management.test.mjs
```

The browser test follows deferred/paged department selection through a filtered user page, switches a faculty's institution/dean, saves and reopens that faculty, and loads department-scoped import semesters. It checks console errors and user-page overflow at 390 and 1440 pixels. The fixture script refuses non-testing databases outside its isolated temporary namespace.

### Verification outcome

The complete 551-test PHP suite initially completed with three disk-space-related errors in the existing catalogue locking tests; the remaining tests passed. After removing the stopped, disposable migration-test MySQL data directory, all five catalogue locking tests passed on rerun. Three further regression tests cover lecturer/semester scope, a 501-allocation financial total and preservation of inactive selections, bringing the verified test inventory to 554. The final affected 46-test group passed with 257 assertions. Browser checks passed with no console errors and no user-page overflow at either viewport. The production build and bundle budgets passed (6,163-byte global JS and 19,742-byte global CSS, gzip). Local indexes were migrated, Laravel caches were cleared/rebuilt and the queue restart signal was issued.

| Browser boundary | Evidence |
| --- | --- |
| Initial page → catalogues | No lookup request until focus/search; initial user page returned HTTP 200 |
| Search → API → options | Twenty-option pages, next page and a specific department search rendered successfully |
| Selected option → filtered page | Submitted department ID and selected label survived navigation |
| Institution → dean → saved faculty | Changing institution cleared the dean; the matching dean was saved and retained when reopening the faculty |
| Department → import semester | Only the matching fixture semester appeared |
| Browser rendering | No JavaScript errors; no page overflow at 390/1440 pixels |

The local workstation still had only about 230 MiB free after cleanup. More free disk space is needed for reliable development/database performance. No live host or infrastructure setting was changed.


## Styles missing after local page navigation — 2026-10-22

Laravel/Composer cache clearing does not clear a browser service worker's Cache Storage. The previous worker cached any successful asset response without validating its MIME type, read across all cache namespaces, returned unhashed CSS forever, and ignored reload/no-cache request directives. A same-origin HTML fallback can even produce an apparently loaded CSSStyleSheet with zero rules in Chrome. This explains why checking only whether a stylesheet's `sheet` exists is insufficient for recovering an already-open page.

Local environments now register `/serviceworker.js?dev=1`; this worker leaves stylesheet/script/image/font requests to the normal browser loader. Push enrollment uses the same worker URL and root registration, preserving its subscription. Production retains caching for validated public assets. Vite's hashed assets remain cache-first; unhashed files revalidate. Invalid cached CSS is evicted and fetched with HTTP-cache bypass, so a poisoned HTTP cache cannot immediately restore it. Private/no-store/redirected/HTML responses are not stored as CSS; storage/quota failures fall through to the network. Authenticated media and Vite development modules are not intercepted.

Worker v8 retires older AcadFlow asset caches, preserving unrelated caches and IndexedDB/offline queued work. Registration bypasses HTTP caching of the worker/imports. On controller upgrade, the existing document retries missing or empty local styles once rather than reloading the page or discarding form state. Existing offline navigation, explicit sync queues and browser notifications continue to use their established paths.

Verification uses actual service workers, rather than blocking them: browser tests cover poisoned CSS entries, a simultaneous poisoned HTTP cache, local cache bypass and old-worker upgrade recovery without a second document request. Valid hashed CSS requires only one network download across consecutive navigations. Isolated Laravel navigation with `APP_ENV=local` confirmed both stylesheets loaded on faculties, audit logs, profile and users after seeding a bad cache entry, with zero JavaScript errors. Fresh navigation did not reproduce the user's original browser state; the invalid-cache failure mechanism was reproduced explicitly and repaired.

```sh
node --test tests/Unit/serviceworker-assets.test.mjs
node --test tests/Browser/stylesheet-navigation.test.mjs
php vendor/bin/phpunit tests/Feature/StylesheetNavigationTest.php tests/Feature/NavigationPerformanceTest.php
```

Keep `APP_ENV=local` on the development server. Build assets and clear/rebuild Laravel caches after deployment as described above. Serve worker scripts with revalidation rather than immutable caching; immutable caching is reserved for hashed build assets. The updated worker replaces the old cache during normal page loading, so routine page navigation should not require manual refreshes.

## Student study workspace (2026-10-23)

The connected study flow adds a bounded Today panel to the student dashboard. A local comparison of the committed dashboard controller against the new controller, using the same current layout and isolated fixture, measured:

| Full HTTP render | Warm median | SQL queries | HTML bytes |
|---|---:|---:|---:|
| Previous dashboard controller | 56.40 ms | 13 | 36,026 |
| Dashboard with Today | 89.14 ms | 19 | 40,680 |
| Study hub, one source session/quiz | 75.06 ms | 13 | 36,749 |
| Study hub, 40 additional notes/sessions/quizzes | 107.51 ms | 14 | 42,503 |

Today adds six bounded queries and about 4.7 KB to this dashboard fixture. The hub gains one eager-load query when note cards exist; it does not add a query per source/history entry. Dashboard data selects no source content, saved explanation or question/answer JSON. Courses/materials/notes use 12/12/6-item simple pagination; Today loads three sessions and five due quizzes, with a SQL aggregate for seven-day practice. Relevant user/date and revision indexes are part of the restartable study migration.

These are eight warm samples after an initial cold sample, SQLite, PHP 8.5.2, array sessions and the compiled Vite bundle on this workstation. They measure added functionality and bounded work, not an improvement in every request’s latency, a production capacity guarantee or measured retention. Raw samples and the exact baseline method are in [student-study-2026-10-23.json](performance/student-study-2026-10-23.json).

For reproduction, use the isolated testing environment described earlier in this document, with an SQLite path beginning `/private/tmp/acadsyntra-studio-study-`, `CACHE_STORE=array`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=array`, and distinct testing config/route/event cache paths. Never seed the application’s normal database. Prepare `php tests/Browser/prepare-student-study.php`, then run `SESSION_DRIVER=array php scripts/performance/benchmark.php --user=1 --route=/study --runs=9`. Add `--history=40` to fixture preparation for the larger dataset. Use `/dashboard` for the dashboard measurement. The fixture resets only its isolated test session and selected quiz attempts; use a fresh database to reproduce the small dataset. Compare the previous controller in an isolated checkout against the same fixture/layout to reproduce the baseline.

For the browser scenario, use database sessions, set `APP_URL=http://127.0.0.1:8016`, prepare the fixture, serve the application from `public/` with Laravel’s development router, and run `node --test tests/Browser/student-study.test.mjs`. No live AI calls are made by this browser scenario. See [My Study Space](STUDENT_STUDY_SPACE.md) for deployment and behavior limits.
