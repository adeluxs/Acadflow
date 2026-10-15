# Course Material Upload + Research Assistant Delivery Fix — 2026-09-17

## Reported symptoms

1. A lecturer checking **Require enrollment to access** could receive `The requires enrollment field must be true or false.`; after a successful-looking submission an independent lecturer could be sent to the Access Denied page.
2. Research Assistant provider logs showed HTTP 200, parsed response, and `response_ready`, while the browser remained in a loading state and did not display the answer.

## Course material fix

- Native checkbox values are normalized before validation and the lecturer create/edit forms submit explicit `0`/`1` values.
- `CourseMaterialController` no longer equates lecturer ownership/access with matching `users.university_id`. Independent-first courses are authorized through the existing course access policy/context.
- The active semester is resolved with `IndependentAcademicContextService::requireActiveSemesterForCourse($course)`, preserving hidden independent academic context where required by the existing schema.
- `is_visible` is now validated and persisted consistently on create/update.
- `CourseMaterialUploadRegressionTest` covers an independent lecturer, browser-style `on`, unchecked `0`, and the post route without a 403.

## Research Assistant delivery fix

The provider itself was not the failing stage: the supplied log reached `AI response ready for caller`. `AiManager::recordAndReturn()` then performed analytics persistence synchronously before Laravel could finish the browser response. Observability work must not hold a valid AI answer.

- AI analytics is deferred until after the HTTP response for normal web requests; CLI/unit-test paths remain synchronous for deterministic testing.
- `AI_DEFER_ANALYTICS_AFTER_RESPONSE=true` controls this behavior.
- The contextual assistant request opts out of the global full-screen loader and keeps its existing local `Thinking…` state.
- A configurable browser timeout (`AI_CONTEXTUAL_UI_TIMEOUT_MS`, default 28000 ms) aborts a genuinely stuck request and shows a retryable error instead of spinning forever.
- `finally` always restores the submit button/loading state.

## Database / deployment

No database migration is required for this patch.

Recommended after replacing the source:

```bash
php artisan optimize:clear
php artisan view:clear
php artisan test --filter=CourseMaterialUploadRegressionTest
php artisan test --filter=AiLifecycleResilienceTest
php artisan test --filter=ResearchStudioProfessionalWorkflowTest
php artisan test
```
