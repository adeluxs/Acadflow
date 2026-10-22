# AcadSyntra notification operations

Updated 22 October 2026 (Africa/Lagos). This guide describes the implemented system and the external services still required for real delivery.

## What changed and why

The existing notification, push subscription, service worker, settings, academic listeners and queue architecture are retained. Previously, academic email could block the request, social events only created in-app notices, fresh users could not reach browser subscription routes, and there was no central reminder/activity/delivery analytics pipeline. Local mail was configured as `log`; that transport cannot deliver to an inbox. VAPID keys existed, but the application database had no subscribed devices.

`NotificationService` now creates one deduplicated notification and per-channel outbox rows transactionally, then dispatches `DeliverNotification` after commit. `SocialNotificationService` delegates to it. Academic observers enqueue model IDs and revisions instead of serializing private academic text. Existing assignment, course, invitation and organisational event producers remain in use; grading, research feedback/status, AI completion, membership requests and commercial access changes join the central pipeline.

The bell and paginated notification centre link to owned activities, group notices by category and show priority. Clearing a notice hides it from the centre without deleting delivery history. Notification Settings supports academic, organisation, research, onboarding and optional engagement preferences, email/push/in-app controls, timezone, quiet hours, devices and consented estimated email-open tracking. Legacy profile opt-outs remain effective before a settings record exists. Password-reset and verification mail is queued separately from optional engagement preferences.

The searchable `/faqs` and `/help` pages contain step-by-step guides and real named-route links for independent registration, departmental/institutional adoption, affiliation, verification, access arrangements, research and notification setup. Institutional directory identity does not grant membership or imply platform adoption.

## Deployment

Use PHP 8.2 or newer. This workstation uses `/Applications/MAMP/bin/php/php8.5.2/bin/php`; its default shell PHP is too old. Back up the application database before migration. The additive notification migration preserves existing records, preferences and historical delivery rows; its `down()` deliberately refuses to discard notification history.

```sh
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:clear
php artisan queue:restart
php artisan notifications:diagnose
```

The local migration has already been applied with a private SQLite backup. Do not run `migrate:fresh` on the application database. Frontend assets were built for this change because browser JavaScript changed. When deploying source without its built assets, run `npm ci` and `npm run build` in the release build step.

Run supervised workers for both queues; adjust the connection to the application's configured connection:

```sh
php artisan queue:work database --queue=notifications --sleep=1 --tries=5 --timeout=300 --max-jobs=1000 --memory=192
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=300 --max-jobs=1000 --memory=192
```

`notifications` handles deliveries, campaigns, event fan-out and analytics snapshots. `default` still handles authentication mail and other existing jobs. Production should use a shared cache/lock store and supervised workers; Redis is supported by setting `QUEUE_CONNECTION` and `NOTIFICATION_QUEUE_CONNECTION` to `redis` and configuring the existing Redis connection. Keep the connection's `retry_after` greater than the worker's longest timeout. Delivery jobs themselves have a 60-second timeout; campaign jobs may need the longer worker timeout. Never switch to `sync` to make reminders appear to work.

Install the existing Laravel scheduler in cron, with the deployment's PHP binary and absolute project path:

```cron
* * * * * cd /path/to/acadflow-web && php artisan schedule:run >> /path/to/scheduler.log 2>&1
```

Hourly queued campaigns check onboarding, inactivity and academic deadlines; maintenance runs every five minutes; the default analytics snapshot warms every minute; opted-in operations summaries run at 08:00 in the application timezone. Schedules use overlap protection, campaign jobs use uniqueness, and notification/delivery keys provide a second deduplication boundary. Do not run a general local worker merely to test delivery: it could also consume unrelated queued academic or AI work.

## Browser push

Enable the existing notification and push modules and browser-push channel in Super Admin notification management. Keep the existing VAPID key pair; rotating it invalidates subscriptions created with the old public key. `notifications:diagnose --json` reports only configuration booleans/counts, never private keys or subscription authentication secrets.

Use a real HTTPS application URL, correct service-worker scope and outbound access to browser push services. Loopback HTTP is suitable only for local browser development. The shared worker push handler remains independent of the open page. Users see a soft invitation after a short delay, and browser permission is requested only after **Enable Notifications**. **Not Now** snoozes the invitation for seven days. Denied browser permission is respected; settings explain recovery. Fresh authenticated users can subscribe before finishing onboarding. A missing subscription with granted permission can be reconnected. Devices are owned by the account, renewal saves replacement keys and logout immediately submits its ordinary POST while push cleanup proceeds best effort.

Closed-page delivery depends on the browser, OS and device policies; it does not require an active application tab. On iOS/iPadOS 16.4 and later, Web Push applies to installed Home Screen web apps and permission requires direct user interaction. See [WebKit's implementation requirements](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/) and [MDN's permission and secure-context guidance](https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API/Using_the_Notifications_API).

HTTP 404/410 and expired subscriptions are removed safely and recorded as invalid/expired device events. A push provider's acceptance means it accepted the encrypted message; it does not prove the OS displayed it. The explicit **Test push** action reports its immediate provider result, separately from centrally queued academic delivery statistics.

## Email and provider feedback

Configure the existing mail provider or SMTP transport, verified sender/domain, credentials and TLS settings. `MAIL_MAILER=log` and `array` are test transports and are recorded as **simulated**, with no provider-acceptance timestamp. Use an actual external transport for production; a logging fallback in a custom failover transport cannot substantiate external acceptance. SMTP timeout defaults to 15 seconds through `MAIL_TIMEOUT`.

Publish the provider-issued SPF and DKIM records for the actual sending domain, then configure aligned DMARC and review authentication reports before tightening enforcement. Values depend on the selected provider; do not paste unrelated DNS examples into production. Provider guidance explains how SPF, DKIM and alignment establish DMARC authentication: [Amazon SES authentication](https://docs.aws.amazon.com/ses/latest/dg/send-email-authentication-dmarc.html), [Twilio email authentication](https://www.twilio.com/docs/email/concepts/security/email-authentication).

Academic email/push previews are generic and deep-link to authenticated owned content. Optional email includes a signed unsubscribe link; opening the confirmation page changes nothing, and POST disables only the relevant optional category. Authentication/security email remains separate. Open pixels are disabled by default; even when consented, email privacy proxies make opens estimates.

Provider delivery/bounce/complaint callbacks require an adapter for the chosen provider. There is no automatic integration with an unconfigured provider. The internal adapter contract is:

```text
POST /webhook/notifications/provider
Content-Type: application/json
X-AcadSyntra-Timestamp: <Unix seconds, within 300 seconds>
X-AcadSyntra-Signature: <hex HMAC-SHA256(secret, timestamp + "." + exact body bytes)>

{"delivery_id":"<notification_logs.uuid>","event_id":"<unique-provider-event-id>","event":"delivered"}
```

Set a strong `NOTIFICATION_PROVIDER_WEBHOOK_SECRET` and rebuild configuration cache. The adapter must verify its provider's original signature before forwarding and correlate the provider message ID or `X-AcadSyntra-Delivery-ID` header with the delivery UUID. Supported events are `delivered`, `bounced`, `complained`, `opened`, `clicked`. Only already accepted email rows can receive these confirmations. Repeated event IDs are idempotent. Bounces/complaints suppress subsequent optional email; provider opens still require user consent. No webhook secret or raw provider payload is shown in analytics.

## Reminder rules and safe delivery

Onboarding reminders start no earlier than 12 hours after account creation, with at least 12 hours between notices. Defaults permit six per onboarding cycle; after the third ignored reminder the next interval is four times the configured interval (48 hours by default). Personal setup links to the saved step, department drafts to the wizard, and institution adoption/admin setup to its existing route. Pending review is not an abandoned draft. Existing approved institution administrators are included regardless of the account type they registered with. Completion, changed stage, opt-out and quiet hours are rechecked before an external send.

Inactivity reminders require explicit engagement opt-in **and** useful unfinished owned work. Stages are 24 hours, 48 hours, seven days, 14 days and 28 days per activity cycle, not an endless daily sequence. Return, work completion or opt-out stops pending reminders. Foreground authenticated HTML navigation and form interactions count as meaningful activity; polling, prefetch, counters, autosave and background JSON requests do not. Activity timestamps write at most once per five minutes, with daily activity rows for reporting. Day boundaries follow application reporting timezone; quiet hours follow the user's selected timezone.

Daily external defaults are ten emails and twenty push deliveries per account, shared across devices. Quiet hours and rate limits defer delivery. Channels retry transient errors with backoff, up to five provider attempts. Accepted sends are never retried by the admin action. An interrupted in-flight send, expired processing lease or uncertain SMTP outcome is marked **unknown** for manual provider reconciliation, not automatically resent. This avoids claiming that distributed email/push can provide unconditional exactly-once delivery.

Settings and templates are available under **Super Admin → Notification Analytics**. Templates accept plain-text placeholders, not executable Blade/PHP. Audit logs record administrative changes. User preferences and scope checks still apply to the resulting notices; email-domain matching never grants academic privileges.

## Analytics, privacy and performance

`/admin/notifications/analytics` is restricted to Super Admins. Cards, interactive charts, accessible data tables and CSV exports use recorded database data, not fixtures or demonstration values. Filters cover dates (maximum 366 days), event/category, channel, role and institution/department scope.

- Delivery: generated notices, channel queues, retries/failures/unknown/simulated results, provider acceptance, confirmation when supplied, clicks, estimated consented opens and processing/queue-to-acceptance latency.
- Push: active users/devices, eligibility coverage, subscription growth, declined/disabled/expired states and browser/device breakdown.
- Onboarding: incomplete users, cohort completion, delivered reminders, clicks, completion after reminders, completion time, saved-step drop-off and account-type comparisons.
- Activity: meaningful DAU/WAU/MAU, inactivity thresholds, engagement sends, returns and channel-associated return rates.

Comparisons of reminded and unreminded users and channel attribution are labelled **observational**; they do not establish causation. Historical rows without acceptance evidence are not reclassified as successful new deliveries. Absence of tracking before this deployment means old activity/conversions cannot be reconstructed. Numeric totals can differ between notice counts and per-device delivery counts.

The dashboard caches aggregate reports for 60 seconds, with a background warm-up and visible snapshot timestamp. It does not cache authorisation or balances. SQL aggregations, indexed status/channel/date keys, bounded campaign chunks of 100 users, paginated histories and streamed exports avoid loading whole populations into request memory. Existing adoption recipient batching remains covered by query-budget tests. The main application bundle measured 6,118 gzip bytes of JavaScript and 19,703 gzip bytes of CSS after the build, within the existing 8 KiB/25 KiB budgets. No production throughput improvement is claimed without production measurements.

Failure-rate alerts use a rolling hour (defaults 20% with at least ten deliveries), deduplicate by hour and log safe counts. Daily operational summaries are off until enabled. Alert delivery still depends on working channels; monitor worker health, scheduler execution and application warnings independently. Maintenance trims at most 1,000 old rows per table per run, in deletion batches of 250, with a default 180-day retention and a 30-day minimum. In-app notice history is retained. Adjust retention and processing capacity for the deployment's volume and privacy obligations.

## Verification and outstanding external checks

Automated coverage includes deduplication, no request-time email, legacy opt-outs, timezone/quiet hours, reminder timing/limits/completion, useful-work inactivity, foreground activity, assignment deadlines, grading/membership/research events, provider failures and invalid subscriptions, signed interactions/unsubscribe, HMAC callbacks, analytics/filter/export permissions, worker uncertainty and preservation of existing data during migration. Real Web Push encryption/signing is exercised with controlled provider HTTP responses; external services are not contacted by these tests.

The final full suite passed **513 tests / 2,895 assertions** in 182.25 seconds, including institution-admin scanning and interrupted-worker duplicate prevention. PHP syntax passed for 139 changed/new files; documentation, MySQL identifier and bundle-budget checks passed. Sequential push/loader browser checks passed 18 tests with one conditional skip. The real-backend notification browser scenario passed, with no JavaScript errors, covering a fresh-user opt-in, settings persistence, owned links, recorded analytics, chart controls, CSV download, searchable help and 390px layouts. Its browser permission APIs are controlled fixtures, not physical push endpoints.

Reproduce PHP and lightweight browser checks:

```sh
php artisan test --compact
node --test --test-concurrency=1 tests/Browser/browser-push.test.mjs tests/Browser/loader-logout.test.mjs
npm run performance:bundles
php scripts/check-documentation.php
php scripts/check-mysql-identifiers.php
php artisan notifications:diagnose --json
```

`tests/Fixtures/notifications.php` refuses to operate outside `APP_ENV=testing` and a SQLite path beginning `/private/tmp/acadsyntra-notifications-`. Its `--reset` option rebuilds only that guarded fixture; its `--deliver` uses an array mailer and controlled push HTTP handler. The full browser scenario accepts `NOTIFICATION_BROWSER_URL` only on loopback, and fixture servers must use isolated `APP_CONFIG_CACHE` and `APP_ROUTES_CACHE` paths.

At handover the actual local application uses log mail, has no configured SMTP credentials and no subscribed real device. No random user's address was used. Actual inbox receipt, provider callbacks, closed-page push and installed-iOS delivery therefore remain external verification steps after connecting a real test inbox/device and enabling the deployment's workers and scheduler. Do not report simulated email or controlled push HTTP responses as live delivery.
