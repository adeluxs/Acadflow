# AcadFlow — Four Critical Fixes Implementation Report

**Release date:** 2026-09-10  
**Base source:** `AcadFlow-progressive-institution-adoption-monetization-test-fixed-2026-09-03.zip`

## Scope completed

This release addresses the four reported areas: institution onboarding search, AI provider-success/request-timeout reliability, Admin Monetization `$setupCurrency`, and Blade `@endforeach` reliability. It also adds regression coverage and source-level preflight validation.

## 1. Institution onboarding search

### Root cause

The search route and backend service already existed, but `resources/views/institution/onboarding-request.blade.php` used Alpine directives (`x-data`, `x-model`, `@click`, etc.). AcadFlow's frontend bundle does not load Alpine, so the browser never initialized the search behavior. The backend search also relied on database collation behavior for `LIKE`, which was less predictable across database engines.

### Fix

- Replaced the Alpine-only UI with dependency-free JavaScript.
- Added 350 ms debounce.
- Added `AbortController` cancellation and sequence protection for stale/racing responses.
- Trimmed user terms before searching.
- Preserved minimum query validation.
- Added loading, results, no-results, network-error and Retry states.
- Search results now continue correctly to either **Claim this institution** or **Request access instead** when the institution is already active.
- `InstitutionAdoptionService::searchInstitutions()` now performs portable case-insensitive partial matching across institution name, short name, code, state and regulator.
- Existing “request new institution” behavior is preserved.

### Main files

- `resources/views/institution/onboarding-request.blade.php`
- `app/Http/Controllers/InstitutionOnboardingController.php`
- `app/Services/InstitutionAdoptionService.php`
- `tests/Feature/InstitutionOnboardingSearchRegressionTest.php`

## 2. AI request succeeds upstream but user may not receive response

### Root cause

The logged `CurlFactory.php` location was not treated as defective vendor code. The application could configure a provider request timeout as high as the common PHP `max_execution_time` (30 seconds), while context building, retry/failover, response parsing, schema validation, billing settlement, cache writes, request counters, analytics, grounding and response serialization all still needed time in the same HTTP request.

This meant PHP could hit its execution ceiling while Guzzle/cURL happened to be running, even though another provider request had previously returned HTTP 200 successfully.

A second observability problem made diagnosis harder: `ExternalProvider` generated a new UUID rather than inheriting AcadFlow's HTTP request correlation ID. Therefore provider logs and controller/safe-error logs could show different request IDs for one logical lifecycle.

### Fix

- Added `AI_LIFECYCLE_BUDGET_SECONDS` (default 24 seconds).
- Added `AI_POST_PROVIDER_RESERVE_SECONDS` (default 4 seconds).
- Changed default runtime provider timeout to 18 seconds.
- Provider attempts are dynamically capped by **remaining lifecycle budget minus post-provider reserve**.
- Retries/fallbacks re-check remaining budget before waiting or starting another provider attempt.
- Health checks are separately bounded to a short maximum.
- Provider transport now inherits `_ai_request_id`; the same logical request ID travels through the assistant, AiManager and provider adapter.
- Request middleware records request start time for elapsed/budget calculations.
- Added lifecycle logging with elapsed milliseconds around assistant context construction, manager dispatch, provider HTTP response, parsing and response readiness. Prompts/secrets are not logged.
- Gemini HTTP-200 parsing now distinguishes:
  - `AI_EMPTY_RESPONSE`
  - `AI_RESPONSE_BLOCKED`
  - `AI_RESPONSE_PARSE_FAILED`
- Added friendly mappings for these errors and `AI_REQUEST_TIMEOUT`.
- Cache read/write failure, usage-counter failure and analytics failure are non-critical and cannot discard a valid answer.
- If provider output is valid but billing settlement fails afterward, AcadFlow preserves the answer, safely attempts reservation release and marks the response for billing reconciliation rather than turning it into `AI_REQUEST_FAILED`.
- `_ai_deadline_at` is excluded from AI cache fingerprints so the lifecycle deadline does not destroy cache hits.

### What happens after Gemini HTTP 200 now

1. Provider HTTP response is logged with the shared request ID.
2. JSON is decoded.
3. Gemini content is classified as usable / empty / blocked / malformed.
4. Valid structured content becomes an `AiResponse`.
5. Central response schema is validated where configured.
6. A successful result is established.
7. Billing settlement is attempted safely.
8. Cache/usage/analytics bookkeeping is attempted without being allowed to destroy that success.
9. Grounding/citation checks and response formatting run in the assistant layer.
10. The response is returned with the same correlation ID.

### Main files

- `config/ai.php`
- `.env.example`
- `app/Http/Middleware/RequestCorrelationId.php`
- `app/Ai/AiManager.php`
- `app/Ai/AiCache.php`
- `app/Ai/Providers/ExternalProvider.php`
- `app/Ai/Providers/GeminiProvider.php`
- `app/Services/Ai/AiRuntimeConfigService.php`
- `app/Services/Ai/AcademicAssistantService.php`
- `app/Support/Errors/UserFacingError.php`
- `tests/Feature/AiResponseLifecycleRegressionTest.php`
- `tests/Unit/AiPostProviderResilienceTest.php`

## 3. Admin Monetization `$setupCurrency`

### Root cause

The Blade view expected a setup-specific currency variable, while currency resolution was partially performed inside the view through a differently named value. That made controller/view data fragile and produced `Undefined variable $setupCurrency` in some page states.

### Fix

`MonetizationController::index()` now resolves `$setupCurrency` centrally using this order:

1. existing Commercial Account currency;
2. selected institution's configured currency;
3. platform display currency;
4. safe application fallback `NGN` only when the resolved value is blank.

The controller explicitly passes `$setupCurrency` to the view. The view no longer depends on `$setupInstitutionCurrency` or creates the authoritative currency itself.

### Main files

- `app/Http/Controllers/Admin/MonetizationController.php`
- `resources/views/admin/monetization/index.blade.php`
- `tests/Feature/MonetizationPageRegressionTest.php`

## 4. Unexpected `@endforeach` compiled Blade error

The reported filename under `storage/framework/views` is a generated compiled-view cache artifact and is intentionally not edited or shipped as authoritative source. The source-only release does not contain that compiled artifact, so its hash cannot be reliably reverse-mapped to a removed/stale historical template.

The current source tree was audited instead. No `@foreach/@endforeach`, `@forelse/@endforelse`, `@for/@endfor`, `@while/@endwhile` or `@switch/@endswitch` mismatch remains across the current Blade templates. The Monetization view involved in the adjacent Admin failure was also hardened by replacing its compact destructuring metric loop with an ordinary `$metricCards` loop.

Deployment must clear old compiled views:

```bash
php artisan view:clear
php artisan optimize:clear
```

This removes stale compiled PHP without modifying anything under `storage/framework/views` manually.

### Main files

- `resources/views/admin/monetization/index.blade.php`
- `scripts/check-runtime-regressions.php`
- `scripts/check-four-critical-fixes.php`

## Regression tests added

- `tests/Feature/InstitutionOnboardingSearchRegressionTest.php`
- `tests/Feature/AiResponseLifecycleRegressionTest.php`
- `tests/Unit/AiPostProviderResilienceTest.php`
- `tests/Feature/MonetizationPageRegressionTest.php`

The AI regression coverage includes valid Gemini HTTP 200 output, empty HTTP 200 output, blocked output, malformed structured output, bounded provider timeout, shared request ID and valid-answer resilience when post-provider billing/analytics operations fail.

## Validation executed in this environment

- All repository `scripts/check-*.php` preflights: **19 passed**.
- Dedicated `scripts/check-four-critical-fixes.php`: **PASS**.
- Blade directive balance: **162 templates checked** by the dedicated preflight.
- PHP/Blade-PHP syntax: **796 files passed `php -l`**.
- Static web/API route → controller audit: **507 routed controller actions, 0 missing targets**.
- `.env.example` preflight: **309 environment keys represented, no duplicate keys, no blank runtime-critical values**.

## PHPUnit/runtime limitation

This source-only release does not contain `vendor/`, and Composer is not available in the execution environment used to build this archive. Therefore Laravel/PHPUnit itself was **not executed here**. The new tests are included and must be run where dependencies are installed:

```bash
composer install
php artisan optimize:clear
php artisan view:clear
php artisan test
```

## Recommended production AI settings

The release defaults are designed for a typical 30-second PHP execution ceiling:

```env
AI_REQUEST_TIMEOUT=18
AI_LIFECYCLE_BUDGET_SECONDS=24
AI_POST_PROVIDER_RESERVE_SECONDS=4
```

The lifecycle budget is also constrained against PHP's configured execution limit at runtime. Do not solve provider latency by setting several-minute synchronous timeouts; use queues/background workflows for operations that legitimately require longer processing.
