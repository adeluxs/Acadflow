# AcadSyntra Performance Optimization Report — 2026-09-12

## Scope

This pass optimized the existing AcadSyntra application incrementally. It did not rebuild the project, remove product functionality, weaken authentication/authorization, or replace the AI/monetization architecture. The baseline was the latest source containing the AI post-provider resilience fix and complete runtime monetization.

## Major bottlenecks found

- A wildcard Blade view composer recalculated shared settings/feature state for every view/partial in a response.
- Settings groups performed repeated per-key cache/database work instead of resolving related keys together.
- Several dashboards/analytics methods issued one query per card/status and course-performance logic issued additional grade queries per course.
- Some controllers/views still contained query-in-loop or view-time queries.
- Large CSV exports, invoice generation, notification retries/broadcasts and scheduled scans materialized large result sets in memory.
- Rich-editor JavaScript was part of the main static bundle although only editor pages use it.
- Two closure web routes prevented safe production route caching.
- A few frequently used filters lacked composite indexes matching their real query shape.
- External notification work for research meetings could run while a database transaction was still open.

## Changes implemented

### Database/query optimization

- `AnalyticsService` submission cards: **7 aggregate queries → 1**.
- `AnalyticsService` attendance cards: **6 aggregate queries → 1**.
- `AnalyticsService` billing cards: **6 aggregate queries → 1**.
- Late-submission calculation: **2 aggregate queries → 1**.
- Course performance: replaced the approximate **1 + 3N** query pattern with **2 grouped queries**.
- AI analytics scalar metrics are aggregated in SQL instead of issuing repeated scalar queries.
- Admin report-card/status counts use conditional aggregates.
- Attendance and submission API analytics use conditional aggregates.
- Citation graph citation totals use eager `withCount()` rather than one count query per node.
- Mention resolution, conflict version lookup, plagiarism current-version access, assignment statistics, research configuration counts, student-import semester loading, literature references and PDF current-version loading were moved out of query-in-loop/view-time patterns.
- Learning-path progress rows are inserted idempotently in bulk rather than queried/created lesson by lesson.
- Seminar/research panel membership validation/upsert uses batched queries.

### New audited indexes

Migration: `database/migrations/2026_09_12_213000_add_hot_path_performance_indexes.php`

- `notifications(user_id, read_at)` — unread notification lookups.
- `notifications(user_id, created_at)` — recent notification activity.
- `invoices(user_id, status)` — user invoice state filtering.
- `invoices(user_id, semester_id)` — semester invoice lookups.
- `attendance_records(session_id, status)` — attendance aggregation by session/state.

Existing migrations were reviewed before adding these; the new indexes complement existing single-column foreign-key indexes rather than duplicating the same composite definitions.

### Memory/large-dataset optimization

- Admin CSV export uses `lazyByIdDesc(500)` instead of building the full export dataset in memory.
- Batch PDF/ZIP export uses lazy iteration and deletes temporary ZIP files after response delivery.
- Invoice generation processes users in 250-row chunks.
- Scheduled large scans/dispatches use 250-row chunks instead of materializing all IDs.
- System-announcement recipient processing uses 250-row chunks.
- Failed-notification retries use 100-row chunks.
- Assignment extension, course/admin and enrollment lists that can grow large use pagination/database filtering rather than large PHP collections.
- Reputation recalculation uses database aggregates instead of hydrating a creator's complete publication history and large ID lists.

### Settings/cache optimization

`SettingService` now provides `getMany()` for batched hot-path resolution:

1. request-local cache is checked first;
2. shared cache values are fetched with one `Cache::many()` call;
3. remaining settings are loaded with one `whereIn()` database query, including institution overrides when needed;
4. resolved values keep the existing per-key cache keys and **one-hour TTL**.

Cache invalidation still occurs through the centralized setting mutation methods. Request-local `SettingService` and `FeatureAccessService` caches are flushed at HTTP termination and after/when queue jobs finish or fail, preventing stale values in long-lived workers.

Bulk settings updates now prefetch matching setting rows once instead of repeatedly looking them up per submitted field.

### Backend/runtime optimization

- Replaced `View::composer('*', ...)` with targeted composers for the actual layouts that consume shared runtime state.
- Student dashboard no longer runs unused invoice/group queries.
- Lecturer academic performance uses grouped SQL aggregation rather than hydrating every grade row.
- Notification setting objects are reused when deciding channels instead of being looked up repeatedly for the same recipient.
- Research-meeting database transactions now commit before potentially slow notification delivery.
- Financial/AI correctness and idempotency protections were left intact; sensitive wallet/billing balances were not made stale through unsafe caching.
- Query work was removed from the audited Blade/PDF templates and moved to controllers/services.

### Frontend/assets

The frontend was already comparatively small and Vue was already loaded conditionally. The main concrete bundle improvement was to remove `rich-editor.js` from the static `app.js` import graph and dynamically import it only when `[data-rich-editor]` exists.

- `resources/js/rich-editor.js` source size: **10,875 bytes**.
- This source is no longer part of the normal page's static import graph.
- No UI behavior or editor feature was removed.

A production Vite bundle-size measurement is intentionally not claimed: `npm ci` could not complete within the execution environment, so no fabricated minified/gzipped numbers are reported.

### Production boot/delivery

- Closure-based web routes were removed; the route source audit now finds no closure/arrow-function route handlers in `routes/`.
- This makes the optimized source compatible with Laravel route caching, subject to the deployment environment's normal framework checks.
- No Redis-only dependency was introduced. Existing cache/queue/session fallbacks remain usable.

## Verification

Executed on the exact optimized source tree before packaging:

- **24/24** `scripts/check-*.php` project preflight checks passed.
- Includes the dedicated `scripts/check-performance-optimization.php` assertions.
- **812/812** PHP/Blade files passed `php -l` parsing.
- Route source audit found **0** closure-based HTTP routes.
- No `.env` file is included; only `.env.example` is present.

### PHPUnit limitation

The source package does not contain `vendor/`, and Composer is unavailable in this execution environment. Therefore the full Laravel PHPUnit suite could not be executed here. Existing application tests were not weakened or removed; the package includes all tests and the project preflight/regression scripts passed.

## Before vs after — evidence available from this environment

| Area | Baseline | Optimized |
| --- | ---: | ---: |
| Submission stat queries | 7 | 1 |
| Attendance stat queries | 6 | 1 |
| Billing stat queries | 6 | 1 |
| Late-submission stat queries | 2 | 1 |
| Course-performance query shape | ~1 + 3N | 2 |
| Admin CSV export memory | all matching rows | lazy 500-row iteration |
| Invoice-generation memory | all matching students | 250-row chunks |
| Announcement recipient processing | potentially all users | 250-row chunks |
| Failed-notification retry processing | full selected set | 100-row chunks |
| Web HTTP route closures | 2 | 0 |
| Rich-editor source in static main import graph | 10,875 bytes | dynamically imported |

Wall-clock response times, Core Web Vitals and production database `EXPLAIN` timings are not stated because this environment does not have the application's installed PHP dependencies, production database, web server or representative production data. Those should be measured after deployment/staging rather than invented.

## Deployment requirements

After deploying this source:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
# Optional in production only after verifying PHP memory headroom:
# php artisan route:cache
php artisan view:cache
php artisan queue:restart
npm ci
npm run build
php artisan test
```

`php artisan migrate --force` applies the audited performance-index migration (and any earlier pending monetization migration). `queue:restart` ensures long-running workers load the new services/cache behavior. Build frontend assets from the committed lockfile.

If the deployment does not run queue workers, `queue:restart` is unnecessary. Redis remains optional unless the deployment already configures Redis for cache/queues/sessions.

## Files most materially optimized

The performance pass changes the hot paths across controllers/services/routes/frontend and adds one migration plus one regression preflight. The baseline-vs-optimized tree contains 51 code/resource/migration/script deltas, centered on:

- `app/Providers/AppServiceProvider.php`
- `app/Services/SettingService.php`
- `app/Services/AnalyticsService.php`
- `app/Services/AcademicPerformanceService.php`
- `app/Ai/AiAnalytics.php`
- dashboard/admin/API controllers
- notification, reputation, citation, engagement and research services
- export/billing/learning-path large-data workflows
- render-only Blade/PDF data preparation
- `resources/js/app.js`
- `routes/web.php`, `routes/console.php`
- `database/migrations/2026_09_12_213000_add_hot_path_performance_indexes.php`
- `scripts/check-performance-optimization.php`



### PHPUnit cache isolation

The optimized application has a large, legitimate route table. Loading a production compiled route cache inside PHPUnit can exceed a 128 MB PHP process once PHPUnit/Collision/Laravel are resident. `phpunit.xml` now boots through `tests/bootstrap.php`, which points route/config/event caches at test-only non-persistent paths before Laravel boots. Production cache files remain untouched and are not loaded by tests.

For local Windows development, route caching is optional. If a pre-existing `bootstrap/cache/routes-v7.php` prevents Artisan/PHPUnit from starting, run `RESET_LOCAL_LARAVEL_CACHES.bat` before `php artisan optimize:clear`.
