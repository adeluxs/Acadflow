# AcadFlow — Password Policy & Rate-Limit UX Remediation

**Date:** 2026-08-20  
**Scope:** Password-strength guidance, existing Security Settings consistency, existing auth/security rate-limit UX, 429 responses, and minor login feedback only.

## 1. Audit result

The codebase already had a centralized `security` settings group and `SettingService`, so this change strengthens that architecture instead of introducing another security configuration system.

Existing password controls discovered:

- `password_min_length`
- `password_require_uppercase`
- `password_require_numbers`
- `password_require_special`
- `max_login_attempts`
- `lockout_duration_minutes`

Existing named Laravel rate limiters discovered:

- `login`
- `register`
- `password-reset`
- `verification`
- `two-factor`

There is no separate resendable OTP subsystem in this Laravel codebase. The existing two-factor challenge uses TOTP/recovery codes, while email verification uses the verification notification flow. No fake OTP settings or middleware were added.

## 2. Password policy consistency

`SettingService::getPasswordPolicy()` now returns the effective UI/API policy and `SettingService::getPasswordRules()` builds backend validation from the same policy. Registration, API password changes, and password reset use these central methods.

A previous reset-password rule used a separate hardcoded Laravel `Password` rule with mixed case, numbers and uncompromised-password checking. That caused the reset path to disagree with Admin/registration. It was removed so password validation has one runtime source of truth.

The current policy does not include a standalone lowercase requirement, so the UI does not claim one exists. The special-character rule uses the same set as backend validation: `@$!%*#?&`.

## 3. Live password guidance

`resources/views/auth/partials/password-policy.blade.php` is reused by registration and password reset. `resources/js/password-policy.js` updates:

- minimum length;
- uppercase when enabled;
- number when enabled;
- special character when enabled;
- confirmation match.

It is dynamically imported only where the password-policy component exists.

## 4. Admin-controlled existing security throttles

The existing named rate limiters now read from central Security Settings:

| Setting | Default | Existing limiter |
|---|---:|---|
| `login_requests_per_minute` | 10 | `login` |
| `registration_requests_per_hour` | 5 | `register` |
| `password_reset_requests_per_minute` | 5 | `password-reset` |
| `verification_requests_per_minute` | 6 | `verification` |
| `two_factor_attempts_per_minute` | 5 | `two-factor` |

`max_login_attempts` and `lockout_duration_minutes` remain the failed-credential lockout controls and are intentionally not merged with the general login-request throttle.

The new default rows are installed with `insertOrIgnore` and seeded with `firstOrCreate`, preserving existing administrator values.

## 5. 429 response behavior

`bootstrap/app.php` centrally handles Laravel `ThrottleRequestsException` and derives the actual retry duration from the response's `Retry-After` header.

JSON/API response:

```json
{
  "message": "Too many requests. Please try again in 45 seconds.",
  "code": "TOO_MANY_REQUESTS",
  "retry_after": 45
}
```

Web requests return to the relevant form with a friendly error and safe previous input. Passwords, password confirmations, current passwords, TOTP codes and tokens are never flashed back. A small JS helper displays the remaining wait as a live countdown.

## 6. Login refinement

The working login flow was not redesigned. The existing failed-login limiter now additionally provides:

- remaining attempts before lockout after a bad credential attempt;
- actual remaining lockout wait time;
- HTTP `Retry-After` on lockout;
- structured API 429 data.

## 7. API/mobile consistency

`GET /api/v1/settings/public` now exposes `password_policy`. Authenticated account bootstrap/profile payloads expose the applicable tenant-scoped policy where useful. This repository does not contain a separate mobile client; external/mobile clients should consume this contract rather than hardcoding independent password rules.

## 8. Regression coverage

Added:

- `tests/Feature/SecurityPolicyExperienceTest.php`
- `scripts/check-security-policy.php`
- Composer script `security-policy-check`

The static check protects against reintroducing hardcoded registration/reset password rules or hardcoded values in the existing auth limiter registrations.

## 9. Files added

- `app/Support/Security/RetryAfter.php`
- `database/migrations/2026_08_20_150000_add_security_rate_limit_settings.php`
- `resources/views/auth/partials/password-policy.blade.php`
- `resources/js/password-policy.js`
- `resources/js/rate-limit-feedback.js`
- `tests/Feature/SecurityPolicyExperienceTest.php`
- `scripts/check-security-policy.php`
- this document

## 10. Main files modified

- `app/Services/SettingService.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/SettingsController.php`
- `app/Http/Controllers/WebAuthController.php`
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `bootstrap/app.php`
- `database/seeders/SettingsSeeder.php`
- `resources/views/settings/index.blade.php`
- `resources/views/settings/partials/field.blade.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/reset-password.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/js/app.js`
- `composer.json`
- canonical Developer/User Guides and `CHANGELOG.md`
