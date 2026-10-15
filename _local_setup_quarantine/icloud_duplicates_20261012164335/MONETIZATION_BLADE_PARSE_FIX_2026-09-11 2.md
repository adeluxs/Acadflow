# AcadFlow Monetization Blade Parse Fix — 2026-09-11

## Reported failure
Three `MonetizationPageRegressionTest` cases returned HTTP 500 because Laravel compiled `resources/views/admin/monetization/index.blade.php` to invalid PHP with `unexpected token "endif"`.

## Root cause
The metric-card loop had been rewritten as a multiline Blade directive containing an inline array:

```blade
@foreach([
    ...
] as [$label, $value])
```

Although logically balanced, this form triggered a Blade compilation/parsing regression in the application environment. The subsequent `@endif` for the super-admin section was therefore emitted into malformed generated PHP and PHP reported it as the unexpected token.

## Fix
- Restored the previously working parser-safe single-line metric-card `@foreach` expression.
- Kept the `ViewErrorBag` fallback required by middleware-free feature tests.
- Preserved the existing super-admin `@if/@endif` structure.
- Added a focused static preflight that rejects the problematic multiline inline-array form and checks `@if/@endif` balance.
- Updated the older critical-fixes preflight so it validates behavior-compatible source rather than requiring the superseded formatting.

## Validation
- 21/21 `scripts/check-*.php` preflight scripts pass.
- 801 PHP/Blade source files pass `php -l`.
- Full PHPUnit execution is not available in this container because the distributed source package does not contain `vendor/`.
