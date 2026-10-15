# Vite test-isolation fix — 2026-09-16

## Symptom

`php artisan test` returned HTTP 500 from otherwise unrelated feature tests with:

`Vite manifest not found at: public/build/manifest.json`

The source ZIP intentionally excludes generated frontend build artifacts, so a fresh test checkout may not have `public/build/manifest.json` until `npm run build` has been executed.

## Root cause

The shared Blade layouts correctly use Laravel's `@vite` directive for real application requests. PHPUnit feature tests render those same layouts. Without an active Vite development server or a production manifest, Laravel attempts to resolve the assets and throws `ViteManifestNotFoundException` before the test can assert application behavior.

## Fix

`tests/TestCase.php` now calls Laravel's framework-provided `$this->withoutVite()` helper from the base `setUp()` method. Every feature test extending `Tests\TestCase` therefore uses the empty test Vite handler automatically.

Production/development Blade files were not modified and continue to use the real `@vite` directive. A missing manifest remains a real deployment error outside PHPUnit.

## Regression protection

`scripts/check-test-vite-isolation.php` verifies that:

- the PHPUnit base class calls `parent::setUp()` and `$this->withoutVite()`;
- the production layout still contains the real Vite entrypoints;
- Blade layouts do not contain testing-environment bypass logic.

No database migration is required for this fix.
