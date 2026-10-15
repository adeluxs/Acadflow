# AcadFlow Safe Error UX Remediation — 2026-08-20

## Scope

This change set is limited to preventing raw framework/PHP/Guzzle/API/JavaScript diagnostics from reaching normal users, improving contextual failure UX, and adding safe retry behavior without redesigning unrelated features.

## Root cause findings

1. **Raw exception reflection existed above the transport layer.** Several controllers/services returned or flashed `$exception->getMessage()` directly, which could turn a Guzzle/cURL exception into visible file paths, exception classes, or stack-like diagnostics.
2. **There was no single final user-facing exception boundary.** Laravel could still render an unexpected throwable differently depending on route type/debug state, while individual controllers used inconsistent failure formats.
3. **Frontend request handling was inconsistent.** AI, QR refresh, gateway testing, secure download, and file upload paths had separate catch/display behavior; some inserted `error.message`/`data.message` directly and Knowledge Hub still used browser `alert()`.
4. **The Paystack connection test had a definite implementation defect.** `PaymentGatewayController` called `PaystackGateway::getSecretKey()` while that method was private, so a real connectivity test could fail before an HTTP request was made. The test also returned success for a non-successful Paystack HTTP response.
5. **Paystack calls had no explicit connection/request timeout.** This increased the chance that network/provider failures would feel like a hung interface.
6. **The exact subtype of the reported CurlFactory incident cannot be proven from the supplied source alone.** No production log containing the incident was included. The existing AI transport already classifies TLS, DNS, timeout, refused connection, rate-limit, authentication, model, and provider 5xx failures; those diagnostics remain in secure provider logs.

## Centralized architecture used/improved

- Added `App\Support\Errors\UserFacingError` as the canonical server-side normalization/sanitization layer.
- Added request correlation IDs through `RequestCorrelationId` and `X-Request-Id`.
- Strengthened `bootstrap/app.php` exception rendering for JSON/API clients and production web requests.
- Added predictable API error fields: `status`, `success`, `code`, `message`, `retryable`, and `request_id`.
- Added safe handling for validation, authentication, authorization, common HTTP 4xx, 429, 5xx, cURL/Guzzle/transport, query, AI, and generic failures.
- Added `resources/views/partials/feedback.blade.php` and reused it in app/auth layouts instead of keeping competing flash blocks.
- Added `resources/js/error-feedback.js` for frontend normalization and non-disruptive toast feedback.
- Added a standalone safe full-page state only for read-only requests where the whole page cannot load.

## User experience changes

- Users no longer receive raw exception classes, PHP/vendor paths, SQL traces, Guzzle/CurlFactory details, or stack traces from the audited paths.
- AI failures now preserve the user's text, show a contextual message, show a request ID when available, and expose **Try Again** only when the backend marks the failure retryable.
- Contextual AI components stay on-page and retry only the failed request.
- Attendance QR refresh failures keep the page usable and provide an inline safe retry.
- Payment gateway connection testing uses safe inline status and a retry only for the read-only connection test.
- Secure Knowledge Hub downloads use application feedback instead of browser-native `alert()`.
- File upload failures preserve the selected file and sanitize the error, but do not blindly retry the upload POST because a lost response can make the server-side outcome ambiguous.
- Failed unknown POST/form actions preserve non-sensitive input while explicitly excluding passwords, OTPs/tokens/secrets, and card fields.

## AI audit

The existing central AI router/provider fallback architecture was retained. `ExternalProvider` already differentiated provider HTTP status, TLS, DNS, connection refused, timeout, generic network failures, configuration/authentication/model failures, and rate limits. Failure payloads from `AcademicAssistantService`, `ContextualAssistantService`, `GroundedCompanionService`, and `AiController` were normalized so provider diagnostics are not reflected to normal users. Existing configured fallback behavior remains authoritative.

## Login/authentication audit

- Wrong credentials continue to be distinguished from system failures.
- Rate-limit responses include the real retry-after value.
- API login failures now use predictable codes and retryability metadata.
- Database/network/unexpected login failures fall through the safe central boundary and do not masquerade as incorrect credentials.

## Payment/gateway safety

- Made the Paystack active secret-key accessor callable by the authorized gateway test service, fixing the private-method failure.
- Fixed false-positive gateway test success when Paystack returns a failed HTTP status.
- Added bounded connection/request timeouts to Paystack HTTP calls.
- Removed direct reflection of Paystack provider messages into normal payment UX.
- Billing verification now distinguishes temporary verification failure, definitive failure, and still-processing state rather than automatically marking every unconfirmed payment failed.
- No automatic retry was added to payment creation or refund POSTs.

## Files changed

### New
- `app/Http/Middleware/RequestCorrelationId.php`
- `app/Support/Errors/UserFacingError.php`
- `resources/js/error-feedback.js`
- `resources/views/errors/request-failed.blade.php`
- `resources/views/partials/feedback.blade.php`
- `scripts/check-safe-error-experience.php`
- `tests/Feature/SafeErrorExperienceTest.php`
- `tests/Unit/UserFacingErrorTest.php`
- `docs/SAFE_ERROR_UX_2026_08_20.md`

### Modified
- `.env.example`
- `bootstrap/app.php`
- `composer.json`
- `app/Http/Controllers/Admin/PaymentGatewayController.php`
- `app/Http/Controllers/AiController.php`
- `app/Http/Controllers/Api/AttendanceController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/BillingController.php`
- `app/Http/Controllers/Api/SyncController.php`
- `app/Http/Controllers/AttendanceController.php`
- `app/Http/Controllers/StudentImportController.php`
- `app/Http/Controllers/SubmissionTaskController.php`
- `app/Services/AcademicIntegrity/PlagiarismService.php`
- `app/Services/Ai/AcademicAssistantService.php`
- `app/Services/Ai/ContextualAssistantService.php`
- `app/Services/Ai/GroundedCompanionService.php`
- `app/Services/Media/ClamAvMalwareScanner.php`
- `app/Services/PaymentGateway/PaystackGateway.php`
- `app/Services/SmsService.php`
- `resources/js/app.js`
- `resources/js/components/FileUpload.vue`
- `resources/views/admin/payment-gateways/edit.blade.php`
- `resources/views/ai/_contextual-assistant.blade.php`
- `resources/views/ai/assistant.blade.php`
- `resources/views/attendance/session.blade.php`
- `resources/views/knowledge/show.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/students/import.blade.php`
- `resources/views/subscription/checkout.blade.php`

## Verification performed in this workspace

Passed:

- PHP syntax check across 597 PHP files.
- `php scripts/check-safe-error-experience.php`
- `php scripts/check-security-policy.php`
- `php scripts/check-ai-provider-transport.php`
- `php scripts/check-grok-performance.php`
- `node --check resources/js/error-feedback.js`
- `node --check resources/js/app.js`
- Syntax checks of the modified inline AI, contextual-AI, attendance-QR, and gateway-test scripts after replacing Blade-only values with test placeholders.
- Static scan confirms no browser-native `alert()` remains in `resources/`.
- Static scan confirms the audited controller response/flash patterns do not directly emit `getMessage()`.

Added automated PHPUnit coverage for raw technical exception sanitization, AI retry policy, structured 400/401/403/404/408/409/422/429/500/502/503 behavior, validation sanitization, and form-input preservation.

Not executed in this uploaded source artifact:

- PHPUnit/framework suite, because `vendor/` and Composer executable/dependencies were not available in the supplied workspace.
- Vite production build, because `node_modules/` was not supplied and `vite` is therefore not installed. The modified standalone/core JavaScript and substituted inline scripts were syntax-checked directly.

## Production requirement

Production must use `APP_ENV=production` and `APP_DEBUG=false`. The new central safety boundary additionally prevents raw production/API exception output even if debug configuration is accidentally incorrect, but debug mode should still never be enabled in production.
