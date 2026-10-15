# Monetization View-Data Fix — 2026-09-11

## Failure reproduced from user test output

All three `MonetizationPageRegressionTest` cases returned HTTP 500 because the Blade template referenced `$suggestedTargetMinor` while that value was created only inside a Blade-local `@php` block.

## Root cause

The monetization page mixed controller-provided state with Blade-local computed state. That made rendering fragile under the middleware-free regression tests and allowed a required form variable to be absent at render time.

## Fix

- Moved monetization setup calculations into `MonetizationController::index()`:
  - `pricingFeatureGroups`
  - `pricingRuleTitles`
  - `setupChanges`
  - `setupMetadata`
  - `requestedAddMinor`
  - `suggestedTargetMinor`
  - `selectedSponsoredFeatures`
- Passed every value explicitly to the view.
- Removed all `@php` directives from `resources/views/admin/monetization/index.blade.php`.
- Preserved old-input behavior for sponsored feature checkboxes.
- Preserved an existing commercial account's currency as authoritative when rendering and saving the commercial account.
- Added `scripts/check-monetization-view-data-contract.php` so controller/view contract regressions fail preflight.
- Strengthened existing monetization/critical-fix preflight checks to reject Blade-local `@php` state on this page.

## Validation

- 22/22 `scripts/check-*.php` preflight checks passed.
- 639/639 PHP files linted successfully.
- Runtime regression preflight checked 162 Blade templates successfully.
- Monetization view-data contract check passed.

The project archive intentionally does not contain `vendor/`, so the complete Laravel PHPUnit suite cannot be executed in this environment. Run `php artisan optimize:clear`, `php artisan view:clear`, and `php artisan test` in the normal project environment.
