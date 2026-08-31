# AcadFlow Monetization Rebuild — Implementation & Deployment Guide

**Release date:** 2026-08-28  
**Scope:** Nigeria-first monetization rebuild, subscription retirement, wallet/ledger, user earnings, B2B commercial accounts, AI usage billing and OpenRouter integration.

## 1. What changed

AcadFlow no longer relies on recurring subscription plans as the normal runtime access model. Historical subscription, invoice, payment and transaction records are retained for audit and migration, but core academic/API access no longer depends on an active subscription.

The new commercial architecture introduces:

- Minor-unit money handling through `App\Support\Money`; new monetization calculations do not use floating-point arithmetic.
- Double-entry financial journals and postings (`ledger_journals`, `ledger_postings`).
- Idempotency protection for wallet funding, commerce purchases, refunds, withdrawals and AI usage billing.
- Separate wallet concepts for spending balance, pending creator earnings, available creator earnings, lifetime earnings and recovery debt.
- Marketplace revenue allocation, settlement holds, withdrawal handling and refund reversal accounting.
- Recovery-debt accounting when a creator refund exceeds currently available earnings; future creator earnings repay the receivable before becoming withdrawable.
- Versioned pricing rules and independent feature entitlements.
- Institution/B2B commercial accounts with prepaid balances and commercial metadata.
- Central Admin **Monetization** area for pricing, wallet, commissions, earnings, withdrawals, payout verification, refund reconciliation, AI billing and institutional commercial controls.
- OpenRouter as a first-class AI provider inside the existing AcadFlow AI provider registry/router/fallback system.
- Central AI usage charging at the AI manager boundary, with free allowance, reservation, settlement/release, provider-cost capture and margin reporting.
- Dry-run-first legacy monetization migration command.

## 2. Important retained historical components

The rebuild intentionally does **not** destructively remove historical financial data. Legacy subscription models/tables may still exist so old invoices, payments and entitlements can be interpreted and migrated safely. They are no longer the normal runtime feature gate.

The old subscription renewal command is retained only as a non-charging compatibility tombstone, so a stale cron entry cannot renew or bill users.

## 3. New/important code locations

### Financial foundation

- `app/Support/Money.php`
- `app/Services/Commerce/LedgerService.php`
- `app/Services/Commerce/IdempotencyService.php`
- `app/Services/Commerce/WalletService.php`
- `app/Services/Commerce/CommerceService.php`
- `app/Services/Commerce/EntitlementService.php`
- `app/Services/Commerce/AiUsageBillingService.php`
- `app/Models/LedgerJournal.php`
- `app/Models/LedgerPosting.php`
- `app/Models/MonetizationIdempotencyKey.php`
- `app/Models/PricingRule.php`
- `app/Models/FeatureEntitlement.php`
- `app/Models/CommercialAccount.php`
- `app/Models/WalletFundingRequest.php`
- `app/Models/AiUsageCharge.php`

### Database/migration

- `database/migrations/2026_08_28_170000_create_monetization_foundation.php`
- `app/Console/Commands/MigrateLegacyMonetization.php`
- `app/Console/Commands/ReleaseCreatorEarnings.php`

### Admin/user UX

- `app/Http/Controllers/Admin/MonetizationController.php`
- `resources/views/admin/monetization/index.blade.php`
- `resources/views/commerce/wallet.blade.php`
- `resources/views/commerce/orders.blade.php`

### AI/OpenRouter

- `app/Ai/Providers/OpenRouterProvider.php`
- `app/Ai/AiProviderRegistry.php`
- `app/Ai/AiManager.php`
- `app/Services/Ai/AiRuntimeConfigService.php`
- `config/ai.php`
- `resources/views/ai/settings.blade.php`

### Regression tests/preflight

- `tests/Unit/MoneyTest.php`
- `tests/Feature/MonetizationFoundationTest.php`
- `tests/Feature/OpenRouterProviderTest.php`
- `tests/Architecture/SubscriptionRetirementArchitectureTest.php`
- `scripts/check-monetization-rebuild.php`

## 4. Refund safety model

External refunds use a resumable state model. A provider refund is not blindly repeated after an uncertain network outcome. Ambiguous results are locked for reconciliation, and Admin can explicitly reconcile them after checking the payment provider. Confirmed provider refunds are checkpointed before local ledger finalization.

This protects against a failure mode where a gateway successfully refunds money but the application loses the response and accidentally sends a second refund on retry.

## 5. Deployment procedure

### Before deployment

1. Back up the production database.
2. Back up `.env`, uploaded files and any gateway/webhook configuration.
3. Deploy the new application files.
4. Keep existing subscription/payment history intact.

### Install dependencies on the deployment server

This release ZIP intentionally does not contain `vendor/` or `node_modules/`.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

For a development/staging environment, omit `--no-dev` so PHPUnit and development tooling are installed.

### Apply schema

```bash
php artisan optimize:clear
php artisan migrate --force
```

### Review legacy monetization migration first

The migration command is dry-run by default:

```bash
php artisan acadflow:monetization-migrate
```

Review the counts, reconcile them against a database backup, then apply:

```bash
php artisan acadflow:monetization-migrate --apply
```

The command does not delete subscription, invoice, payment or transaction history.

### Scheduler/queues

Ensure the normal Laravel scheduler continues to run. Mature creator earnings are released by the scheduled monetization command. Ensure the application's queue workers remain active for existing queued workloads.

Useful manual command:

```bash
php artisan acadflow:release-creator-earnings
```

## 6. OpenRouter configuration

OpenRouter is integrated into the existing AI provider architecture. Configure it through AcadFlow AI Settings and/or the application's normal environment/bootstrap configuration.

`.env.example` includes:

```env
OPENROUTER_API_KEY=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_MODEL=openai/gpt-4o-mini
OPENROUTER_TEMPERATURE=0.2
OPENROUTER_SITE_URL=${APP_URL}
OPENROUTER_APP_NAME=${APP_NAME}
OPENROUTER_INPUT_COST_PER_MILLION=0
OPENROUTER_OUTPUT_COST_PER_MILLION=0
```

Do not expose the API key to frontend JavaScript or mobile clients.

## 7. Admin checks after deployment

After deployment, verify:

- **Admin → Monetization** loads and shows the commercial settings.
- Wallet minimum funding and withdrawal rules match the intended market policy.
- Marketplace/institution commission rules are configured deliberately rather than left to assumptions.
- Settlement hold periods match operational/refund risk.
- Payout account verification, withdrawal and refund queues are visible to authorized administrators only.
- Institution commercial accounts have the correct currency and prepaid balance.
- AI monetization is enabled only when desired and free allowance/charge values are reviewed.
- OpenRouter provider/model/fallback selection is correct in AI Settings.
- Payment gateway webhook signatures and exact amount/currency verification are working in staging before production cutover.

## 8. Validation completed in the release environment

The final source tree was validated with the standalone checks available without Composer dependencies:

- **764 PHP files:** syntax validation passed.
- **478 statically declared route → controller method targets:** all resolved.
- **155 Blade templates:** repository runtime-regression preflight passed.
- Repository AI routing/provider/transport checks: passed.
- Documentation and `.env.example` coverage checks: passed.
- Feature management check: passed.
- Grounded AI Companion check: passed.
- Seeder idempotency check: passed.
- Monetization rebuild check: passed.
- MySQL identifier-length check: passed.
- Safe-error experience check: passed.
- Security policy/rate-limit check: passed.
- Specialized AI assistants check: passed.
- New monetization-domain floating-point static scan: passed.

## 9. Validation that must be run on staging/production-like infrastructure

The supplied source package has no `vendor/` directory, Composer is not installed in the packaging environment, and `node_modules/` is not bundled. Therefore Laravel boot tests, PHPUnit and the Vite production build could not be executed inside the packaging environment.

After dependencies are installed in staging, run at minimum:

```bash
php artisan test
npm run build
php artisan route:list
php artisan migrate:status
```

Then exercise these end-to-end flows with sandbox/test gateway credentials:

1. Fund wallet → verify callback → spend wallet balance.
2. Buy a paid Knowledge Hub item → allocate creator/platform/institution revenue.
3. Release creator earning → request withdrawal → process payout.
4. Refund before withdrawal.
5. Refund after withdrawal → create recovery debt → confirm future earnings repay debt.
6. Retry duplicated wallet/purchase/refund requests and confirm idempotency.
7. Simulate ambiguous gateway-refund response and reconcile it without sending a second provider refund.
8. Run AI request with free allowance.
9. Run AI request charged to wallet.
10. Run institution-sponsored AI request.
11. Select OpenRouter as primary/fallback and verify provider/model/usage analytics.
12. Confirm inactive historical subscription rows do not block normal academic/API access.

## 10. Rollback principle

The migration is additive and historical data is retained. If the release must be rolled back, take a database snapshot before deployment and restore the application/database together. Do not manually delete journals, wallet funding records, AI charge records or entitlement rows from a partially active production cutover without reconciliation.

---

**Implementation status:** Code implementation and standalone release validation completed. Full Laravel/PHPUnit/Vite runtime validation requires installing the project's declared Composer/NPM dependencies in staging or production-like infrastructure.
