# AI Post-Provider Resilience Fix — 2026-09-12

## Regression
`AiPostProviderResilienceTest` expected `AiUsageBillingService::release()` exactly once when settlement fails after a valid provider answer. The durable reconciliation change marked the request for reconciliation but did not execute the release path.

## Fix
- `AiManager` now releases the reservation after a post-provider or cached-response settlement failure, then marks the delivered request as `reconciliation_required`.
- `AiUsageBillingService::markReconciliationRequired()` accepts both `reserved` and `released` records and stores `pre_reconciliation_status` plus `reservation_released`.
- Valid AI answers remain successful and retain `billing_status=reconciliation_required` / `billing_settlement_pending=true`.
- Duplicate logical request IDs remain blocked because the usage record is still reconciliatory after the release.

## Compatibility
The existing PHPUnit test was not modified. A preflight assertion was added so this release-call contract cannot be silently removed again.
