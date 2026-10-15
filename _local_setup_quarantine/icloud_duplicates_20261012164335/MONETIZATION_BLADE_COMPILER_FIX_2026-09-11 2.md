# AcadFlow Monetization Blade Compiler Fix — 2026-09-11

## Failure reproduced from supplied test output

All three remaining `MonetizationPageRegressionTest` cases failed with HTTP 500 because Laravel compiled `resources/views/admin/monetization/index.blade.php` into PHP containing an unexpected `endif`. The failure occurred before the page could render.

## Root cause

The previously compiling Monetization template had been modified with a single-line Blade PHP directive:

```blade
@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag())
```

That fallback was added to avoid an undefined `$errors` variable when the regression test disables middleware. It introduced the Blade compiler regression. Comparing the failing template to the earlier compiling template showed this fallback was the structural change responsible for the parse failure.

## Final fix

The inline PHP fallback was removed completely. Validation rendering now uses short-circuit guards instead:

```blade
@if(isset($errors) && $errors->any())
...
@endif
```

The `feature` and `amount` field messages use the same guarded pattern rather than Blade's `@error` directive. This means the view no longer requires `ShareErrorsFromSession` to have run, while normal validation messages still render when `$errors` exists.

## Regression protection

Updated:

- `scripts/check-monetization-blade-regression.php`
- `scripts/check-pasted-test-regressions.php`

The checks now reject the broken inline `@php($errors = ...)` form, require all three guarded error-access points, reject the unsafe `@error` usage in this middleware-free view path, and continue checking the metric loop and directive balance.

## Validation performed

- 21/21 repository `scripts/check-*.php` preflight scripts passed.
- 963 PHP and Blade source files passed `php -l`; 0 syntax failures.
- The repaired Monetization template differs from the earlier compiling template only at the three validation-error access points.
- Full Laravel PHPUnit execution is not available in this source-only environment because the archive does not include `vendor/`.
