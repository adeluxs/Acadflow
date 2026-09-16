# Route Cache / PHPUnit Memory Fix — 2026-09-14

## Problem

On Windows with PHP `memory_limit=128M`, running PHPUnit after generating Laravel's production route cache could fail during application bootstrap with:

`Allowed memory size of 134217728 bytes exhausted ... bootstrap/cache/routes-v7.php`

AcadFlow has a large legitimate route table. PHPUnit, Collision, Laravel and the hydrated compiled route collection can together exceed 128 MB even though the uncached test suite runs successfully.

## Fix implemented

- Added `tests/bootstrap.php` and changed `phpunit.xml` to use it before the application boots.
- PHPUnit now points `APP_ROUTES_CACHE`, `APP_CONFIG_CACHE`, and `APP_EVENTS_CACHE` at isolated test-only paths.
- Any stale test-only cache file is removed before Composer/Laravel bootstraps.
- Production route/config/event caches are left untouched and are not loaded by tests.
- Added `scripts/check-test-cache-isolation.php` to prevent regression.
- Added `RESET_LOCAL_LARAVEL_CACHES.bat` to remove a pre-existing generated local route/config/event cache without requiring Laravel to boot first.
- Updated the Windows/performance documentation to make route caching optional on constrained local environments.

## Existing machine recovery

Run once from the project root:

```bat
RESET_LOCAL_LARAVEL_CACHES.bat
php artisan optimize:clear
php artisan test
```

You can also manually remove `bootstrap\cache\routes-v7.php` before running Artisan if necessary.

## Verification

- 25/25 project preflight checks passed.
- 813/813 PHP/Blade files passed parser validation.
- 0 generated bootstrap cache artifacts are included in the release ZIP.
- 0 closure-based web routes remain.
