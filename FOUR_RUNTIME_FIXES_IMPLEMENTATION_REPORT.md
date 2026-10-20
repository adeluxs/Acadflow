# AcadSyntra Four Runtime Fixes — Implementation Report

## Scope

This release fixes the four reported production issues against the latest progressive-institution-adoption source:

1. Institution onboarding search not executing in the browser.
2. AI requests exhausting a 30-second PHP request even though provider calls can succeed.
3. Undefined `$setupCurrency` on Admin → Monetization.
4. A stale/invalid compiled Blade view reporting an unexpected `@endforeach`.

## 1. Institution onboarding search

### Root cause
The onboarding page used Alpine directives (`x-data`, `x-model`, `@click`, etc.), but Alpine is not installed or initialized by AcadSyntra. The backend search route and adoption service existed, yet the browser-side search state never ran.

During the fix audit a second issue was caught: the new search module initially had its own auto-initializer while `app.js` also initialized it after lazy import. That would have produced duplicate listeners and requests. The module now has exactly one initializer owned by `app.js`.

### Fix
- Replaced the Alpine-only UI with a vanilla/Vite module: `resources/js/institution-onboarding.js`.
- Lazy-loads only when `[data-institution-search]` exists.
- 350 ms debounce.
- `AbortController` cancels superseded requests.
- Sequence guard prevents stale result races.
- Loading, results, no-results, network error and retry states.
- Active institutions redirect to institution-access request instead of a second claim.
- Existing unclaimed institutions populate `request_type=claim_existing` + `university_id`.
- New-institution request workflow remains available.
- Backend search trims input and uses normalized, partial, case-insensitive matching across name, short name, code, state and regulator.

### Primary files
- `resources/views/institution/onboarding-request.blade.php`
- `resources/js/institution-onboarding.js`
- `resources/js/app.js`
- `app/Services/InstitutionAdoptionService.php`
- `app/Http/Controllers/InstitutionOnboardingController.php`

## 2. AI HTTP 200 / 30-second timeout

### What the supplied logs actually show
The successful Gemini log and the later 30-second fatal have different request IDs. They are therefore two separate logical requests in the supplied evidence. The old correlation behavior nevertheless made it harder to trace one logical request through controller → router → provider → parser → persistence → response.

### Root cause
Guzzle/CurlFactory is not defective. PHP happened to be executing inside Guzzle when the request-wide 30-second ceiling was exhausted.

The previous runtime could allow a provider timeout close to the entire PHP execution ceiling. A slow attempt, retry/failover, context preparation and post-provider work could therefore consume more than the available request budget. A provider could also return a valid result only for a later non-critical write (cache, analytics, usage accounting or settlement) to risk turning the whole operation into a failure.

### Fix — predictable request budget
- Added `AI_INTERACTIVE_REQUEST_BUDGET=24`.
- Added `AI_RESPONSE_RESERVE_SECONDS=4`.
- Runtime budget is automatically clamped below PHP's execution ceiling when PHP defines one.
- Every synchronous AI request receives `_ai_started_at` and `_ai_deadline_at`.
- Provider attempts calculate their timeout from the remaining deadline and reserve response-processing time.
- A new attempt is not started when only the response reserve remains.
- Retry/fallback loops therefore cannot blindly consume multiple full provider timeouts inside one 30-second PHP request.

### Fix — correlation and lifecycle timing
One logical `request_id` now flows through:

`AiController → AcademicAssistantService → AiManager → provider adapter → parser → response`

Each provider attempt additionally has its own `provider_attempt_id`.

Lifecycle logging now captures elapsed milliseconds for stages such as request receipt, context/prompt enrichment, provider dispatch, response resolution and completion. Prompts, secrets and API keys are not logged.

### Fix — preserve successful answers
After a valid provider result is established:
- cache-write failure is logged and ignored;
- usage counter failure is logged and ignored;
- analytics failure is logged and ignored;
- billing settlement failure marks `metadata.billing_settlement_pending=true` for later reconciliation instead of discarding the valid answer;
- billing release failure after provider failure does not replace the original provider error.

### Fix — Gemini parsing
Gemini HTTP 200 responses now distinguish:
- `AI_EMPTY_RESPONSE`
- `AI_RESPONSE_BLOCKED`
- `AI_RESPONSE_PARSE_FAILED`

Connection/timeout/rate-limit/provider errors remain separately classified by the shared provider transport.

### Cache correctness
Volatile request lifecycle fields (`_ai_request_id`, `_ai_started_at`, `_ai_deadline_at`) are excluded from the deterministic AI cache key so correlation/timing does not destroy cache hits.

### Primary files
- `config/ai.php`
- `.env.example`
- `app/Ai/AiManager.php`
- `app/Ai/AiCache.php`
- `app/Ai/Contracts/AiRequest.php`
- `app/Ai/Providers/ExternalProvider.php`
- `app/Ai/Providers/GeminiProvider.php`
- `app/Services/Ai/AiRuntimeConfigService.php`
- `app/Services/Ai/AcademicAssistantService.php`
- `app/Http/Controllers/AiController.php`
- `app/Support/Errors/UserFacingError.php`

### AI frontend behavior
The existing assistant UX already preserves the failed question, disables duplicate submission while active and provides a Retry action. The backend error classification now allows that UX to display a truthful safe failure without exposing Guzzle/PHP paths.

## 3. Admin Monetization `$setupCurrency`

### Root cause
The view depended on a Blade-local `$setupCurrency` derived from `$setupInstitutionCurrency`. That made the variable's existence dependent on the exact rendered Blade source/cache path and caused the reported undefined-variable failure.

### Fix
`MonetizationController@index` now resolves the authoritative setup currency and passes `$setupCurrency` directly to the view. Currency continues to use AcadSyntra's central `SettingService` and commercial-account currency, with the existing platform currency fallback when no institution-specific setting exists.

### Primary files
- `app/Http/Controllers/Admin/MonetizationController.php`
- `resources/views/admin/monetization/index.blade.php`

## 4. Unexpected `@endforeach`

The reported exception points to a compiled file under `storage/framework/views`. Compiled Blade output is generated cache and is never edited.

A complete scan of the latest source found no unmatched `@foreach/@endforeach` (or related Blade directive) source error. `scripts/check-runtime-regressions.php` passes all **162 current Blade templates**. This means the reported compiled file is consistent with stale compiled output from an earlier source state rather than a remaining malformed Blade template in this release.

The release does not package compiled Blade cache. Deployment should run:

```bash
php artisan view:clear
php artisan optimize:clear
```

## Regression tests added/expanded

### Institution search
- full institution name;
- partial name;
- different casing;
- leading/trailing spaces;
- no-result response;
- invalid short query;
- selecting an existing institution creates a claim for the same university record.

### AI
- Gemini valid HTTP 200 reaches a successful normalized provider response;
- logical request ID remains correlated through provider headers/response;
- empty HTTP 200 response classification;
- blocked response classification;
- malformed generated JSON classification;
- exhausted safe request budget starts no provider request;
- valid provider answer survives non-critical billing-settlement, cache and analytics failures;
- controller endpoint returns a valid AI service result to the frontend API response.

### Monetization
- Admin Monetization renders with default currency and empty rules;
- selected institution currency is resolved by the controller;
- existing verified-institution sponsorship tests remain intact.

## Validation executed in this environment

- All repository `scripts/check-*.php` preflights pass, including the new four-runtime-fixes check.
- `scripts/check-runtime-regressions.php`: PASS, 162 Blade templates checked.
- JavaScript syntax check (`node --check`) passes for the onboarding module and app bootstrap.
- PHP syntax validation passes for changed PHP/tests and the repository-wide PHP source/test set.
- `.env.example` contains the new runtime budget keys and passes env validation.

`vendor/` is not included in the source archive, so Laravel/PHPUnit cannot be executed in this environment. The regression tests are included for execution after `composer install` on the target machine.

## Recommended deployment commands

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan view:clear
php artisan optimize:clear
npm ci
npm run build
```

For development/test verification:

```bash
composer install
php artisan optimize:clear
php artisan test
```

## Outcome

The four reported issues are addressed at their application root causes. No vendor code or compiled Blade cache was modified, and the fix remains centralized so all AcadSyntra AI features benefit from the safer lifecycle and provider transport behavior.
