# Email verification and browser push

## Deploy the changes

Use PHP 8.2+ with OpenSSL, cURL and mbstring. Install the committed dependency lock, apply the new migrations without resetting data, rebuild frontend assets and restart queue workers:

```bash
composer install
php artisan migrate --force
npm ci
npm run build
php artisan queue:restart
```

The email-verification migration inserts a new default only when missing. It does not modify verification timestamps or overwrite an existing administrator choice. The push migration preserves existing subscriptions, widens provider endpoints beyond 255 characters, and indexes a SHA-256 endpoint hash instead of an oversized text URL. Its rollback preserves the wider representation.

## Email verification

Super Admin → System Settings → Security → **Email Verification Enabled** controls the requirement globally. It remains enabled by default. Institution administrators cannot override it, including through bulk-setting requests.

When enabled, unverified accounts are directed to confirmation before protected web/API access. Verification mail uses the application's configured Laravel mail transport. Configure real SMTP/provider credentials for production; a `log` mailer does not deliver external email.

When disabled, new accounts go to onboarding and verification mail is skipped. Existing unverified accounts can access features once their other account requirements are satisfied. Their addresses remain unverified in the database. Re-enabling the switch blocks those still-unverified accounts again. Signed links still require a valid signature, matching account ID and email hash before writing actual verification. Disabling this requirement does not grant verified institutional affiliation or bypass role/tenant, onboarding, inactive-account or 2FA checks.

## Set up browser push

1. Open Super Admin → Notification Management. If delivery keys are missing, use **Generate delivery keys** with a contact such as `mailto:support@yourdomain.com`. The private key is encrypted in database settings and is not displayed. Existing keys are preserved.
2. Alternatively, configure the complete `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` environment set. The subject must be a `mailto:` contact email or HTTPS contact URL. A partial environment configuration must be corrected before database-backed key setup can be used. Never rotate keys during ordinary deployment; current subscriptions are bound to the existing identity.
3. Enable **Push Notifications** in Feature & Module Management, ensure its **Notifications** dependency is enabled, and enable the push delivery channel in Notification Management. Existing administrator release/channel choices are intentionally preserved.
4. Serve the app over HTTPS. Open Notification Preferences in the target browser, choose **Enable this browser**, and approve the browser permission prompt. Explicit enrollment also opts the user's push preference in. Automatic subscription reconciliation preserves the existing preference.
5. Use **Send test notification**. A successful server response means the push provider accepted delivery. Confirm that it actually appears on the target device; browser/OS settings, connectivity, power modes and browser support control final presentation.

The application never prompts for permission merely because a page loaded. If permission is denied, users must change browser settings before enrollment. On supported iOS/iPadOS devices, use the Home Screen web app; see [WebKit's Web Push guidance](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/). Browser push has a standalone service worker and does not depend on enabling PWA/offline caching.

**Disable this browser** removes only its enrollment. User notification preferences control all devices. Signing out removes the current browser subscription before completing logout. Switching accounts on a shared browser invalidates the previous account's device subscription; re-enroll for the new account. A background request cannot re-enable a user who opted out.

## Delivery and recovery

`PushNotificationService` uses `minishlink/web-push` for encrypted `aes128gcm` messages and signed VAPID requests. The active worker displays received messages; notification clicks can only open the application's own origin. PWA and standalone workers share the same handlers.

Delivery checks the module, parent module, institution channel, user preference, active account and subscription expiry. HTTP 404/410 removes expired provider registrations; 429/server/connection failures preserve the subscription for retry. Notification Management's existing failed-push retry action honors the same runtime controls. Existing queued event listeners deliver notifications asynchronously, so queue workers must be running for announcements and event-driven notifications. A per-device request has bounded timeouts and does not follow provider redirects.

Only configured HTTPS browser push-service hosts are accepted. Subscriptions require valid P-256/auth keys. Endpoint ownership is scoped to the account; moving the same browser subscription to a new account requires possession of the existing encryption keys. Long endpoints are stored intact and uniquely identified by their hash. Logs contain subscription IDs and HTTP/exception classes, not subscription URLs, notification contents or private keys.

If push is unavailable, check the admin module/channel state, delivery-key configuration, user preferences, permission in the browser and OS, HTTPS and queue workers. If delivery fails permanently, re-enroll that browser; do not generate a new server key pair to fix a single device. Unsubscribe remains available when the push module is disabled.

## Automated validation

```bash
php -d memory_limit=512M vendor/bin/phpunit
node --test tests/Browser/browser-push.test.mjs
npm run build
```

Backend tests use SQLite in memory and a simulated HTTP provider with the real Web Push library. They exercise signing/encryption, expiry, preference controls, subscription ownership, long endpoints, one-time encrypted key setup, verification on/off, registration mail and signed confirmation. Browser tests run headless Chromium/Chrome with simulated push APIs and provider responses, plus worker handler tests. They exercise permission consent, enrollment/disable, recovery after failure, shared-account cleanup and safe notification navigation.

The browser runner uses installed macOS Chrome when available; other environments can install Playwright Chromium or set `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH`. Real production SMTP delivery, push-provider delivery to an actual device and MySQL rollout still require deployment validation. Automated tests send no real notification or email to external recipients.

Protocol references: [Web Push PHP](https://github.com/web-push-libs/web-push-php), [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API), [PushManager](https://developer.mozilla.org/en-US/docs/Web/API/PushManager).

## Validation record — 2026-10-20

- Full PHP suite: **371 tests, 1,558 assertions, passed**; includes 27 new email-verification/push cases.
- Worker/headless browser suite: **7 checks passed** using installed Chrome with simulated browser push APIs.
- Production build, changed PHP syntax, source whitespace, runtime Blade regressions, Feature Management, MySQL identifier lengths, centralized security policy and safe-error preflights: passed.
- Existing unrelated documentation/env-example/seeder preflight failures remain: five AI feature keys missing from canonical guides, five pre-existing AI variables missing from `.env.example`, and demo/university seeder idempotency warnings.
- The migrations were exercised in SQLite memory databases. They have not been applied to the working application's database. Real SMTP and device/provider delivery were not exercised; no external recipient was messaged.
