<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Models\FeatureUsageCharge;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\InstitutionAdoptionService;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FeatureBillingService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly EntitlementService $entitlements,
        private readonly InstitutionAdoptionService $institutionAdoption,
        private readonly PricingCatalogService $pricing,
    ) {}

    /**
     * Begin a protected runtime operation. Fixed-access rules require an existing
     * entitlement/sponsorship; pay-per-use rules reserve funds before the action.
     *
     * @return array<string,mixed>
     */
    public function begin(User $user, string $feature, string $requestId, int $quantity = 1): array
    {
        $quantity = max(1, $quantity);
        if ($user->isAdmin()) {
            return ['source' => 'admin', 'charge_minor' => 0, 'request_id' => $requestId, 'feature' => $feature, 'pricing_type' => 'admin'];
        }
        $rule = $this->pricing->featureRule($feature);
        if (! $rule) {
            return ['source' => 'free', 'charge_minor' => 0, 'request_id' => $requestId, 'feature' => $feature, 'pricing_type' => 'free'];
        }

        $type = $this->pricing->pricingType($rule);
        if ($type === 'fixed_access') {
            if (! $this->entitlements->has($user, $feature)) {
                throw ValidationException::withMessages([
                    'feature' => 'This feature requires activation before you can use it. Open Paid Feature Access to activate it.',
                ]);
            }

            return ['source' => 'entitlement', 'charge_minor' => 0, 'request_id' => $requestId, 'feature' => $feature, 'pricing_type' => $type, 'pricing_rule_id' => $rule->id];
        }

        if ($type !== 'pay_per_use') {
            throw ValidationException::withMessages(['feature' => 'This feature has an unsupported pricing configuration.']);
        }

        return $this->reservePayPerUse($user, $feature, $requestId, $quantity, $rule);
    }

    public function complete(User $user, array $reservation, array $metadata = []): void
    {
        $chargeId = (int) ($reservation['usage_charge_id'] ?? 0);
        if ($chargeId <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($user, $chargeId, $metadata): void {
                $usage = FeatureUsageCharge::query()->whereKey($chargeId)->lockForUpdate()->first();
                if (! $usage || $usage->status === 'completed') {
                    return;
                }
                if ($usage->status !== 'reserved') {
                    return;
                }

                $amount = (int) $usage->total_amount_minor;
                if ($amount > 0 && in_array($usage->funding_source, ['user_wallet', 'institution'], true)) {
                    $attempt = max(1, (int) data_get($usage->metadata, 'reservation_attempt', 1));
                    $this->ledger->post('feature-settle:'.$usage->uuid.':'.$attempt, 'feature_usage_settlement', $usage->currency, [
                        ['account_code' => 'feature_revenue_clearing', 'direction' => 'debit', 'amount_minor' => $amount],
                        ['account_code' => 'platform_feature_revenue', 'direction' => 'credit', 'amount_minor' => $amount],
                    ], $user, ['feature_usage_charge_id' => $usage->id, 'feature' => $usage->feature]);
                }

                $usage->forceFill([
                    'status' => 'completed',
                    'settled_at' => now(),
                    'metadata' => array_merge((array) $usage->metadata, $metadata),
                ])->save();
            }, 3);
        } catch (\Throwable $exception) {
            $this->markReconciliationRequired($chargeId, 'settlement_failed', $metadata + ['error_type' => $exception::class]);
            Log::error('Paid feature settlement failed after successful operation; reserved funds retained for reconciliation.', [
                'feature_usage_charge_id' => $chargeId,
                'user_id' => $user->id,
                'error_type' => $exception::class,
            ]);
        }
    }

    public function release(User $user, array $reservation, array $metadata = []): void
    {
        $chargeId = (int) ($reservation['usage_charge_id'] ?? 0);
        if ($chargeId <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($user, $chargeId, $metadata): void {
                $usage = FeatureUsageCharge::query()->whereKey($chargeId)->lockForUpdate()->first();
                if (! $usage || $usage->status !== 'reserved') {
                    return;
                }

                $amount = (int) $usage->total_amount_minor;
                if ($amount > 0 && $usage->funding_source === 'user_wallet') {
                    $this->wallets->creditSpending(
                        $user,
                        $amount,
                        'feature_usage_release',
                        $usage,
                        null,
                        'Paid feature reservation released.',
                        ['feature' => $usage->feature, 'feature_request_id' => $usage->request_id]
                    );
                } elseif ($amount > 0 && $usage->funding_source === 'institution' && $usage->commercial_account_id) {
                    $account = CommercialAccount::query()->whereKey($usage->commercial_account_id)->lockForUpdate()->firstOrFail();
                    $account->increment('prepaid_balance_minor', $amount);
                    $attempt = max(1, (int) data_get($usage->metadata, 'reservation_attempt', 1));
                    $this->ledger->post('feature-release:'.$usage->uuid.':'.$attempt, 'feature_usage_release', $usage->currency, [
                        ['account_code' => 'feature_revenue_clearing', 'direction' => 'debit', 'amount_minor' => $amount],
                        ['account_code' => 'institution_prepaid_liability:'.$account->id, 'direction' => 'credit', 'amount_minor' => $amount],
                    ], $user, ['feature_usage_charge_id' => $usage->id, 'feature' => $usage->feature]);
                }

                $usage->forceFill([
                    'status' => 'released',
                    'released_at' => now(),
                    'metadata' => array_merge((array) $usage->metadata, $metadata),
                ])->save();
            }, 3);
        } catch (\Throwable $exception) {
            $this->markReconciliationRequired($chargeId, 'release_failed', $metadata + ['error_type' => $exception::class]);
            Log::error('Paid feature reservation release failed; reserved funds retained for reconciliation.', [
                'feature_usage_charge_id' => $chargeId,
                'user_id' => $user->id,
                'error_type' => $exception::class,
            ]);
        }
    }

    public function markReconciliationRequired(int $chargeId, string $reason, array $metadata = []): void
    {
        if ($chargeId <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($chargeId, $reason, $metadata): void {
                $usage = FeatureUsageCharge::query()->whereKey($chargeId)->lockForUpdate()->first();
                if (! $usage || $usage->status !== 'reserved') {
                    return;
                }

                $usage->forceFill([
                    'status' => 'reconciliation_required',
                    'metadata' => array_merge((array) $usage->metadata, $metadata, [
                        'reconciliation_reason' => $reason,
                        'reconciliation_required_at' => now()->toIso8601String(),
                    ]),
                ])->save();
            }, 3);
        } catch (\Throwable $exception) {
            Log::critical('Unable to persist paid feature reconciliation marker.', [
                'feature_usage_charge_id' => $chargeId,
                'reason' => $reason,
                'error_type' => $exception::class,
            ]);
        }
    }

    public function purchaseFixedAccess(User $user, string $feature, string $requestId, string $billingPeriod = 'monthly'): FeatureEntitlement
    {
        if (data_get(config('monetization.pricing_features.'.$feature, []), 'requires_ai_monetization', false)
            && ! (bool) SettingService::getGlobal('ai_monetization_enabled', false)) {
            throw ValidationException::withMessages(['feature' => 'This AI package is unavailable while extra AI charging is disabled.']);
        }

        $rule = $this->pricing->featureRule($feature);
        if (! $rule || $this->pricing->pricingType($rule) !== 'fixed_access') {
            throw ValidationException::withMessages(['feature' => 'This feature is not configured for fixed access.']);
        }

        $offer = app(PackageAccessService::class)->isPackage($feature)
            ? app(PackageAccessService::class)->offer($rule, $feature, $billingPeriod)
            : [
                'amount_minor' => max(0, (int) $rule->unit_amount_minor),
                'duration_days' => max(0, (int) data_get($rule->metadata, 'duration_days', 0)),
                'included_ai_requests' => 0,
            ];

        return DB::transaction(function () use ($user, $feature, $requestId, $rule, $billingPeriod, $offer): FeatureEntitlement {
            // One user can only make one fixed-access purchase decision at a time.
            // Re-check entitlement after acquiring the lock so double-clicks/tabs
            // cannot purchase the same active access period twice.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = FeatureEntitlement::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->where('access_type', '!=', 'institution_sponsored')
                ->where('access_type', '!=', 'trial')
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $existingCharge = FeatureUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($existingCharge?->feature_entitlement_id) {
                return FeatureEntitlement::query()->findOrFail($existingCharge->feature_entitlement_id);
            }

            if ($this->institutionSponsors($user, $feature)) {
                $days = (int) $offer['duration_days'];

                return $this->entitlements->grant(
                    $user,
                    $feature,
                    'institution_sponsored',
                    null,
                    $days > 0 ? now()->addDays($days) : null,
                    ['pricing_rule_id' => $rule->id, 'funding_source' => 'institution_sponsorship']
                );
            }

            $wallet = $this->wallets->account($user);
            $currency = strtoupper((string) $rule->currency);
            $this->assertCurrency($currency, $wallet->currency, 'wallet');
            $amount = (int) $offer['amount_minor'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['feature' => 'This feature price is not valid.']);
            }

            $usage = FeatureUsageCharge::query()->create([
                'request_id' => $requestId,
                'user_id' => $user->id,
                'university_id' => $user->university_id,
                'pricing_rule_id' => $rule->id,
                'feature' => $feature,
                'pricing_type' => 'fixed_access',
                'quantity' => 1,
                'unit_amount_minor' => $amount,
                'total_amount_minor' => $amount,
                'currency' => $currency,
                'funding_source' => 'user_wallet',
                'status' => 'reserved',
                'metadata' => ['pricing_rule_version' => $rule->version, 'billing_period' => $billingPeriod],
            ]);

            $this->wallets->debitSpending(
                $user,
                $amount,
                'feature_purchase_reserve',
                $usage,
                null,
                'Paid feature access purchased.',
                ['feature' => $feature, 'feature_request_id' => $requestId]
            );

            $days = (int) $offer['duration_days'];
            $includedAi = (int) $offer['included_ai_requests'];
            FeatureEntitlement::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->where('access_type', 'trial')
                ->where('status', 'active')
                ->update(['status' => 'converted']);
            $entitlement = $this->entitlements->grant(
                $user,
                $feature,
                'purchased',
                $includedAi,
                $days > 0 ? now()->addDays($days) : null,
                [
                    'pricing_rule_id' => $rule->id,
                    'usage_charge_id' => $usage->id,
                    'billing_period' => $billingPeriod,
                    'included_ai_requests_total' => $includedAi,
                ]
            );

            $this->ledger->post('feature-purchase-settle:'.$usage->uuid, 'feature_purchase_settlement', $currency, [
                ['account_code' => 'feature_revenue_clearing', 'direction' => 'debit', 'amount_minor' => $amount],
                ['account_code' => 'platform_feature_revenue', 'direction' => 'credit', 'amount_minor' => $amount],
            ], $user, ['feature_usage_charge_id' => $usage->id, 'feature_entitlement_id' => $entitlement->id]);

            $usage->forceFill([
                'feature_entitlement_id' => $entitlement->id,
                'status' => 'completed',
                'settled_at' => now(),
            ])->save();

            return $entitlement;
        }, 3);
    }

    /** @return array<string,mixed> */
    private function reservePayPerUse(User $user, string $feature, string $requestId, int $quantity, PricingRule $rule): array
    {
        return DB::transaction(function () use ($user, $feature, $requestId, $quantity, $rule): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = FeatureUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($existing?->status === 'completed') {
                throw ValidationException::withMessages([
                    'feature' => 'This paid operation has already completed. Start a new operation instead of replaying the same request.',
                ]);
            }
            if ($existing?->status === 'reserved') {
                throw ValidationException::withMessages([
                    'feature' => 'This paid operation is already in progress. Please wait for it to finish before trying again.',
                ]);
            }
            if ($existing?->status === 'reconciliation_required') {
                throw ValidationException::withMessages(['feature' => 'Billing reconciliation is pending for this request. Please start a new operation.']);
            }

            $unit = max(0, (int) ($rule->unit_amount_minor ?? 0));
            $total = $unit * $quantity;
            if ($unit <= 0 || $total <= 0) {
                throw ValidationException::withMessages(['feature' => 'This feature price is not valid.']);
            }

            $currency = strtoupper((string) $rule->currency);
            $fundingSource = 'user_wallet';
            $commercialAccountId = null;

            $commercial = app(AcademicSponsorshipService::class)->account($user, $feature, true);

            if ($commercial) {
                $this->assertCurrency($currency, $commercial->currency, 'institution commercial account');
                if ((int) $commercial->prepaid_balance_minor < $total) {
                    throw ValidationException::withMessages(['feature' => 'Your institution prepaid balance is too low for this paid feature.']);
                }
                $fundingSource = 'institution';
                $commercialAccountId = $commercial->id;
            } else {
                $wallet = $this->wallets->account($user);
                $this->assertCurrency($currency, $wallet->currency, 'wallet');
                if ($this->wallets->totalSpendableMinor($user) < $total) {
                    throw ValidationException::withMessages(['feature' => 'Your spending balance is too low for this paid feature. Add funds and try again.']);
                }
            }

            $attempt = max(1, (int) data_get($existing?->metadata, 'reservation_attempt', 0) + 1);
            $metadata = array_merge((array) ($existing?->metadata ?? []), [
                'pricing_rule_version' => $rule->version,
                'reservation_attempt' => $attempt,
            ]);
            if ($existing) {
                $usage = $existing;
                $usage->forceFill([
                    'user_id' => $user->id, 'university_id' => $user->university_id, 'pricing_rule_id' => $rule->id,
                    'commercial_account_id' => $commercialAccountId, 'feature' => $feature, 'pricing_type' => 'pay_per_use',
                    'quantity' => $quantity, 'unit_amount_minor' => $unit, 'total_amount_minor' => $total, 'currency' => $currency,
                    'funding_source' => $fundingSource, 'status' => 'reserved', 'released_at' => null, 'metadata' => $metadata,
                ])->save();
            } else {
                $usage = FeatureUsageCharge::query()->create([
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'university_id' => $user->university_id,
                    'pricing_rule_id' => $rule->id,
                    'commercial_account_id' => $commercialAccountId,
                    'feature' => $feature,
                    'pricing_type' => 'pay_per_use',
                    'quantity' => $quantity,
                    'unit_amount_minor' => $unit,
                    'total_amount_minor' => $total,
                    'currency' => $currency,
                    'funding_source' => $fundingSource,
                    'status' => 'reserved',
                    'metadata' => $metadata,
                ]);
            }

            if ($fundingSource === 'institution') {
                $commercial->decrement('prepaid_balance_minor', $total);
                $this->ledger->post('feature-reserve:'.$usage->uuid.':'.$attempt, 'feature_usage_reserve', $currency, [
                    ['account_code' => 'institution_prepaid_liability:'.$commercial->id, 'direction' => 'debit', 'amount_minor' => $total],
                    ['account_code' => 'feature_revenue_clearing', 'direction' => 'credit', 'amount_minor' => $total],
                ], $user, ['feature_usage_charge_id' => $usage->id, 'feature' => $feature]);
            } else {
                $this->wallets->debitSpending(
                    $user,
                    $total,
                    'feature_usage_reserve',
                    $usage,
                    null,
                    'Paid feature funds reserved.',
                    ['feature' => $feature, 'feature_request_id' => $requestId, 'reservation_attempt' => $attempt]
                );
            }

            return $this->reservationFromUsage($usage);
        }, 3);
    }

    private function institutionSponsors(User $user, string $feature): bool
    {
        return app(AcademicSponsorshipService::class)->account($user, $feature) !== null;
    }

    /** @return array<string,mixed> */
    private function reservationFromUsage(FeatureUsageCharge $usage): array
    {
        return [
            'usage_charge_id' => $usage->id,
            'request_id' => $usage->request_id,
            'feature' => $usage->feature,
            'pricing_type' => $usage->pricing_type,
            'source' => $usage->funding_source,
            'charge_minor' => (int) $usage->total_amount_minor,
            'currency' => $usage->currency,
            'commercial_account_id' => $usage->commercial_account_id,
            'status' => $usage->status,
        ];
    }

    private function assertCurrency(string $expected, ?string $actual, string $source): void
    {
        $actual = strtoupper((string) $actual);
        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'feature' => "This price is in {$expected}, but your {$source} is in {$actual}. Ask an administrator to align the currencies before charging.",
            ]);
        }
    }
}
