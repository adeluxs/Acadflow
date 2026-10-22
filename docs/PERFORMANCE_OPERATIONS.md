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
