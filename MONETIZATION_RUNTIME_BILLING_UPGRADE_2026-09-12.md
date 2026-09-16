# AcadFlow Runtime Monetization & Billing Upgrade — 2026-09-12

## Scope completed

This release closes the gap between Admin pricing configuration and real runtime charging.

### Runtime settings
- `Runtime pricing & wallet controls` are saved globally and consumed through `SettingService::getGlobal()`.
- AI monetization enablement, global free allowance, AI request price and provider-cost reporting FX now use the same global source of truth as the Admin form.
- Wallet funding minimum, withdrawal minimum/fee, creator hold days, marketplace commission and institution revenue share use the same global controls.

### Three pricing modes
1. **Fixed access** — one successful purchase creates an entitlement for the configured number of days (or no expiry when configured as 0).
2. **Pay per use** — money is reserved before a protected operation, settled after success, and released after failure.
3. **AI usage** — global AI price/free allowance with optional per-assistant overrides.

### Runtime-connected non-AI features
- Group Submissions — pay per use.
- Rubric-Based Grading — pay per use.
- Attendance Tracking — pay per use.
- Document & PDF Generation — pay per use.
- Knowledge Hub Premium Access — fixed access.

### AI pricing targets
All 16 AI features currently registered in `config/ai.php` are exposed as pricing targets, including study, lecturer, writing, citation, research, assignment, submission validation, plagiarism, SIWES, project, material, discussion and Knowledge Hub AI features.

### Billing lifecycle
- User wallet and verified institution commercial-account sponsorship are supported.
- Currency mismatches are rejected instead of silently treating minor units as interchangeable.
- AI and pay-per-use billing use durable request IDs and billing rows.
- Successful cached provider AI answers pass through billing; their new provider cost is recorded as zero.
- Failed operations release reserved funds.
- If post-success settlement or release bookkeeping fails, the charge is marked `reconciliation_required`; successful delivered work is not incorrectly refunded and failed work is not silently charged as completed.
- Duplicate/in-flight logical request IDs are protected against free replay/double execution.
- Per-user row locking prevents concurrent requests from consuming the same AI free-allowance slot.
- Fixed-access purchase decisions are serialized so double-clicks/tabs cannot purchase the same active access period twice.

### User experience
- Added **Paid Feature Access** page showing wallet balance, eligible institution sponsorship, non-AI feature pricing, AI assistant pricing/free allowances and recent usage charges.
- Fixed-access features can be activated from the page.
- Wallet and user navigation link to Paid Feature Access.

### Admin experience
- Paid Feature Access pricing uses a runtime-connected target dropdown instead of raw rule keys.
- Pricing type is derived server-side from the feature target.
- Fixed access exposes duration, AI exposes free successful requests/day, and pay-per-use exposes the unit price.
- Publishing a price creates an immutable pricing-rule version for auditability.
- Monetization metrics include completed generic feature revenue and completed AI revenue/margin.

## Data model

Added migration:

`database/migrations/2026_09_12_000001_create_feature_usage_charges_table.php`

The table records request ID, user/institution, pricing rule, commercial account, entitlement, feature, pricing type, quantity, unit/total amount, currency, funding source, lifecycle status and reconciliation metadata.

## Verification performed in the artifact environment

- 23/23 repository `scripts/check-*.php` preflight checks passed.
- 810/810 PHP source files passed `php -l` syntax validation.
- 163 Blade templates are present; the existing Blade/runtime regression preflights passed.
- Admin Monetization Blade contains no inline `@php` block, preserving the prior compiler-regression fix.
- AI pricing registry covers 16/16 AI feature keys from `config/ai.php`.

The source package does not include `vendor/`, and Composer is not installed in the artifact environment, so the full Laravel PHPUnit suite cannot be executed here. A dedicated `MonetizationRuntimeBillingTest` was added for execution in the normal project environment.

## Deployment / validation

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan view:clear
php artisan test
```

For local development, omit `--force` if preferred.
