# Scholarly Search Timeout + Global AcadFlow Branding Fix — 2026-09-16

## Why the 30-second fatal error returned

Research Studio literature discovery queried multiple scholarly providers sequentially. Each provider could wait for a long timeout and retry before the next provider started. After those network calls, discovered papers were persisted with one `updateOrCreate()` query per result. The PHP request could therefore approach or exceed `max_execution_time=30`, with the fatal often surfacing inside Laravel's database `Connection.php` simply because a database operation happened to be running when the request clock expired.

## Implemented timeout fix

- Replaced sequential interactive provider calls with Laravel HTTP request pooling.
- Independent scholarly providers now execute concurrently.
- Default interactive provider timeout: 5 seconds.
- Default connect timeout: 2 seconds.
- Removed retry chains from the interactive literature-discovery path.
- PubMed remains a bounded two-wave flow because its summary request depends on IDs returned by its first request.
- Provider failures are partial/non-fatal: one slow provider no longer blocks successful providers.
- Successful discovery payloads are cached for 300 seconds by normalized query/filter/provider set.
- Empty/total-outage results are not cached, allowing immediate retry after transient network failure.
- Replaced per-record `updateOrCreate()` persistence with batch `upsert()` in chunks of 25 followed by one retrieval query.
- Existing scholarly-record UUIDs are preserved during upsert so saved project references remain stable.

### Environment controls

```env
SCHOLARLY_SEARCH_TIMEOUT=5
SCHOLARLY_CONNECT_TIMEOUT=2
SCHOLARLY_SEARCH_CACHE_SECONDS=300
```

The application no longer depends on increasing PHP's 30-second execution limit for normal literature discovery.

## AcadFlow brand implementation

Generated and installed a new AcadFlow identity based on an academic-book / rising-learning-path mark.

Assets:

- `public/branding/acadflow-logo.png`
- `public/branding/acadflow-mark.png`
- `public/branding/acadflow-icon-32.png`
- `public/branding/acadflow-icon-180.png`
- `public/branding/acadflow-icon-192.png`
- `public/branding/acadflow-icon-512.png`
- `public/favicon.ico`

The mark is now used in the main application shell, auth layout, onboarding layout, landing page, public information pages, PWA manifest, favicon, Apple touch icon, and service-worker offline image fallback.

## Global branded loader

A centralized global loader is implemented through:

- `resources/views/partials/global-loader.blade.php`
- `resources/js/global-loader.js`
- shared styles in `resources/css/app.css`

It uses the AcadFlow mark inside an animated orbit and appears automatically for:

- same-origin page navigation;
- normal GET search/filter form submissions;
- POST/PUT/PATCH/DELETE form submissions;
- foreground non-GET `fetch()` requests;
- foreground Axios mutation requests;
- AI actions and other foreground API mutations that use those clients.

Background sync, offline replay, and QR/heartbeat-style polling are excluded so the full-screen loader does not flash during background work.

## PWA changes

The manifest now uses the AcadFlow PNG icons. Service-worker cache version was advanced to v3 and pre-caches the new mark/icons. Old hard-coded `/icons/icon-192x192.png` and `/icons/icon-512x512.png` runtime references were removed.

## Verification

Use:

```bash
php scripts/check-scholarly-timeout-and-branding.php
php artisan optimize:clear
npm ci
npm run build
php artisan test
```

The source release intentionally does not contain `vendor/` or `node_modules/`.
