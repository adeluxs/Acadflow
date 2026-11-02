# Payment gateways

AcadSyntra currently implements **Paystack**. Stripe and Flutterwave are placeholders in older comments, not usable adapters. Gateway credentials and mode are managed by Super Admin in Payment Gateways.

## Architecture and boundaries

| Area | Entry points | Settlement owner |
|---|---|---|
| Wallet funding | Wallet add-funds form; wallet callback | `CommerceService::initiateWalletFunding` / `verifyWalletFunding`, then `WalletService` and `LedgerService` |
| Marketplace | Publication/learning-path purchase; order callback | `CommerceService::initialize` / `verify` / `complete`; order items, entitlements and revenue allocations |
| Institution invoices | API payment initialize/verify; authenticated browser return; manual bank-transfer form | API `BillingController`, `PaymentService`; manual references require administrator verification |
| Historical subscriptions | Compatibility callback and webhook dispatcher | `SubscriptionController`; preserves non-recurring entitlements and never creates new recurring plans |
| Refunds | Order request, administrator approval, provider event, reconciliation | `CommerceService`; processing amounts reserve refund capacity; provider completion gates local reversal |
| Withdrawals | Verified payout accounts, withdrawal request and administrator decision | Existing `CommerceService` / `WalletService` workflow; this work does not introduce automatic bank transfers |

The rest of the application uses the shared tenant settings, feature access and identity layers. AI and feature usage consume the spending balance through their existing billing services. Creator earnings and spending balances remain separate ledger buckets.

`PaymentGatewayManager` constructs registered adapters and offers only active, configured gateways for checkout. Deactivation preserves earlier verification. New payment records snapshot test/live mode. Keep both keys until outstanding payments in the older environment have settled; rotating/removing a key can prevent old webhook validation. Historical records without an environment snapshot use the gateway's current mode.

## Configure Paystack

1. Create the Paystack gateway. It starts inactive.
2. Choose Test Mode and enter the test secret key. Public keys are optional for the server-created hosted checkout. Select only currencies enabled for your merchant account.
3. Save and run Test Connection. This uses a read-only provider call and requires both HTTP success and a positive provider response.
4. Copy the webhook URL shown on the configuration page into the Paystack dashboard. The preferred path is `/commerce/webhook/paystack`; `/webhook/payment/paystack` reaches the same dispatcher for older integrations. Both require a signed raw JSON body and accept charge and refund events.
5. Exercise wallet funding, a marketplace purchase, an invoice, a pending payment and a refund in test mode. Then save the live secret key and select Live Mode for production payments.

Paystack's listed currencies are NGN, USD, GHS, ZAR, KES and XOF; availability depends on the merchant account and country. XOF amounts must be whole units even though Paystack expects amounts multiplied by 100. The application stores money as integer minor units. See [Paystack API currency rules](https://paystack.com/docs/api/).

Saved credentials are encrypted by the model and never returned in serialized gateway data. Leave password fields blank to retain saved values. No separate Paystack webhook secret is needed: signatures use the test/live secret key. The edit form has a single mode selector so credentials, checkout and connection testing agree.

## Confirmation and recovery

Browser redirects are hints to check a payment, not proof of payment. The provider's successful reference, exact integer amount and currency must all match the local record. Signature checks use the original request bytes, preserving whitespace, Unicode and slash formatting. Both webhook URLs handle wallet, marketplace, invoice and legacy payments; unrelated event types are acknowledged without settlement. Inconclusive settlement returns 503 so Paystack can redeliver. The application does not acknowledge deferred work as completed without a durable inbox.

Payment references are persisted before provider POSTs. A timeout or malformed response leaves the attempt available for verification instead of deleting its reference or automatically issuing another POST. Funding idempotency stores only local preparation within its retryable database transaction. Checkout double-clicks reuse an existing URL; an attempt without a conclusive URL must be checked before another initialization. Idempotency keys cannot be replayed by another account.

Users can check pending funding on Wallet and pending orders on Orders. Authorized API clients can check invoice references through `/api/v1/payments/verify`. Restoring provider connectivity or a valid signed webhook can complete a pending attempt without a second payment.

Refund POSTs also run once. A returned refund ID with `pending` or `processing` status leaves the refund processing and keeps its amount reserved. Refund events read the stored API refund ID, verify amount/currency and wait for `processed` before reversing local allocations. Duplicate delivery cannot repeat a completed reversal. Transport failures, server errors, missing refund IDs and failed refunds require reconciliation rather than a blind POST retry. Ambiguous events remain available for administrator reconciliation. If an operator crashes before recording the refund response, let the processing attempt become stale and review it again to mark it for reconciliation.

For reconciliation, an authorized administrator checks the provider dashboard and records the final outcome, provider refund reference and an evidence note on the order. Confirming completion finalizes local accounting; confirming that no refund occurred permits another reviewed attempt. Never choose “not refunded” while the provider still reports pending/processing.

Provider behavior references: [webhooks](https://paystack.com/docs/payments/webhooks/), [verification](https://paystack.com/docs/payments/verify-payments/), [refunds](https://paystack.com/docs/payments/refunds/), [refund API](https://paystack.com/docs/api/refund/).

## Deployment and checks

Run normal non-destructive migrations with the configured PHP 8.2+ executable:

```bash
php artisan migrate --force
```

The migration `2026_10_20_000001_harden_payment_gateway_storage` widens encrypted credentials from JSON to text, normalizes legacy JSON credential representations, and widens the transaction-type enum so MySQL accepts wallet funding. Its rollback intentionally retains the wider types to preserve stored records. Back up the database through the normal operational process before deployment. Do not reset the database or regenerate the application encryption key.

Local automated checks use SQLite memory databases and fake provider HTTP responses; they do not charge accounts or validate live credentials:

```bash
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/PaymentGatewayFlowTest.php
php scripts/check-documentation.php
php scripts/check-monetization-runtime-billing.php
```

Real provider credentials, public HTTPS webhook reachability and MySQL concurrency need staging verification before production rollout. No live payment or live refund is required by the automated tests.

## Validation record — 2026-10-20

- Full PHPUnit suite: **344 tests, 1,453 assertions, passed** (PHP 8.5.2, SQLite memory database); includes 30 payment-flow cases.
- Production frontend build: passed.
- Changed PHP syntax checks and source whitespace checks: passed.
- Runtime Blade regressions, Feature Management, MySQL identifier lengths, monetization rebuild/runtime billing, centralized security policy, safe errors, grounded companion and specialized assistant preflights: passed.
- Existing unrelated preflight failures remain in AI feature documentation (five missing keys), `.env.example` (five missing AI variables), demo/university seeder idempotency, central AI entry-point detection and AI Assistant routing detection. The affected implementations were not changed by this payment work.
- Live merchant calls, public webhook delivery and MySQL migration/concurrency were not exercised. The migration has not been applied to the working application's database.
