# AcadFlow PHPUnit Regression Fix — Second Pass (2026-09-10)

## Scope

This pass fixes the five failures reported after the previous patch. Application behavior was corrected to match the existing regression contracts; the assertions were not weakened.

## Root causes and fixes

1. **`AiAssistantEndpointTest` returned HTTP 500**
   - Root cause: the controller had been changed to call `AcademicAssistantService::ask()` with only two arguments when no course was selected.
   - The existing endpoint regression test requires the observable five-argument service contract: user, message, nullable course ID, logical request ID, and lifecycle start time.
   - Fixed `AiController::askAssistant()` and `AcademicAssistantService::ask()` to preserve that contract while keeping the newer centralized `AiManager` request-ID propagation.

2. **`AiLifecycleResilienceTest` lost settlement-reconciliation state**
   - Root cause: a billing settlement exception preserved the provider answer but did not expose the required `billing_settlement_pending` response metadata.
   - Fixed the settlement failure path to preserve the valid answer and merge `billing_settlement_pending=true` plus `billing_status=reconciliation_required` into response metadata.

3. **Three `MonetizationPageRegressionTest` cases returned HTTP 500**
   - Root cause: the Blade view referenced a temporary `$metricCards` variable that was not reliably defined on the render path used by the regression tests.
   - Removed the temporary variable and render the six metric cards directly from the controller-provided `$metrics` and `$displayCurrency` values.
   - Retained the safe `ViewErrorBag` fallback for middleware-free test rendering.

4. **Project preflight had encoded the previous workaround**
   - Updated `scripts/check-pasted-test-regressions.php` so it verifies the real five-argument endpoint contract and billing settlement metadata.
   - Updated `scripts/check-four-critical-fixes.php` so it verifies that monetization metric cards no longer depend on `$metricCards`.

## Validation completed in this environment

- 20/20 `scripts/check-*.php` preflight scripts pass.
- 797 PHP/Blade files lint successfully with PHP 8.4.23.
- Exact `AiAssistantEndpointTest.php` and `AiLifecycleResilienceTest.php` regression definitions were restored into the package and used to verify the required contracts statically.
- Full Laravel PHPUnit execution is not possible in this container because the supplied project does not include `vendor/` and Composer is not installed here.

## Files materially changed

- `app/Http/Controllers/AiController.php`
- `app/Services/Ai/AcademicAssistantService.php`
- `app/Ai/AiManager.php`
- `resources/views/admin/monetization/index.blade.php`
- `scripts/check-pasted-test-regressions.php`
- `scripts/check-four-critical-fixes.php`
- `tests/Feature/AiAssistantEndpointTest.php` (restored existing regression test)
- `tests/Feature/AiLifecycleResilienceTest.php` (restored existing regression test)
