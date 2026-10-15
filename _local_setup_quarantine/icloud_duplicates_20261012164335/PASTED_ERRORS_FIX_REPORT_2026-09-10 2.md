# AcadFlow — Pasted PHPUnit Errors Fix Report

Date: 2026-09-10

This patch targets the failures supplied in `Pasted text (2).txt` against the uploaded AcadFlow source.

## Fixed failures

### 1. `AiRuleEngineTest::test_cache_avoids_reprocessing`

**Observed:** the second identical `AiManager::analyze()` call returned `cached=false`.

**Root cause:** `AiCache` correctly excluded the top-level `_ai_request_id` and `_ai_deadline_at` from its key, but `AiPromptService::safeContext()` still allowed `_ai_deadline_at` into the rendered `_prompt.user_prompt`. Because the deadline changes on every invocation, the enriched payload remained different and generated a new cache key.

**Fix:** `_ai_deadline_at` is now removed from prompt/model context before prompt rendering. Routing fingerprints and actual business context remain part of the effective cache fingerprint, while volatile lifecycle metadata does not.

### 2. `AiAssistantEndpointTest`

**Observed:** Mockery rejected `AcademicAssistantService::ask(user, question, null)` and the endpoint returned HTTP 500 instead of 200.

**Root cause:** the controller always supplied the optional third `courseId` argument, even when no course was selected. Existing callers/mocks written against the original two-argument contract therefore no longer matched.

**Fix:** `AiController::askAssistant()` now calls `ask($user, $message)` when `course_id` is absent and only supplies the third argument when a real course ID exists. The service still supports course-scoped requests.

### 3. `AiLifecycleResilienceTest`

**Observed:** `Unknown named parameter $requestId`.

**Root cause:** the centralized manager preserved request IDs through payload metadata, but the public `AiManager::analyze()` method no longer exposed the previously expected named `requestId` argument.

**Fix:** `AiManager::analyze()` now accepts an optional fifth `?string $requestId` argument. When supplied it is copied to `_ai_request_id` before the normalized `AiRequest` is created, ensuring the same logical ID survives the full lifecycle.

### 4. `MonetizationPageRegressionTest` (three page states)

**Observed:** `Undefined variable $errors` in `resources/views/admin/monetization/index.blade.php` when the test disabled middleware.

**Root cause:** Laravel normally shares `$errors` through `ShareErrorsFromSession`, but `withoutMiddleware()` intentionally removes that middleware. The view referenced `$errors` directly and also contains `@error` directives.

**Fix:** the view now initializes a safe empty `Illuminate\Support\ViewErrorBag` only when `$errors` was not already supplied. Normal web requests retain Laravel's real validation error bag; middleware-free tests and isolated renders no longer crash.

## Files changed

- `app/Ai/AiManager.php`
- `app/Services/Ai/AiPromptService.php`
- `app/Http/Controllers/AiController.php`
- `resources/views/admin/monetization/index.blade.php`
- `CHANGELOG.md`
- `scripts/check-pasted-test-regressions.php`

## Validation completed

- All 20 `scripts/check-*.php` preflight suites passed, including the new targeted pasted-error regression preflight.
- Modified PHP/Blade files pass `php -l`.
- Repository-wide PHP/Blade syntax validation passed.
- ZIP integrity is checked after packaging.

## PHPUnit environment note

The supplied archive does not include `vendor/`, Composer is not installed in this execution environment, and outbound DNS is unavailable. Therefore the Laravel PHPUnit suite cannot be booted here. On the development/staging machine with dependencies installed, verify with:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan test
```

The changes above directly address the exact runtime causes reported by the supplied failing tests rather than weakening their assertions.
