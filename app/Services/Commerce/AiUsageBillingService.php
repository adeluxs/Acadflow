<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Ai\Contracts\AiResponse;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\User;
use App\Services\InstitutionAdoptionService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiUsageBillingService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly LedgerService $ledger,
        private readonly InstitutionAdoptionService $institutionAdoption,
        private readonly PricingCatalogService $pricing,
    ) {}

    /** @return array<string,mixed> */
    public function reserve(?User $user, string $feature, string $requestId): array
    {
        if (! $user) return ['source' => 'platform', 'charge_minor' => 0, 'request_id' => $requestId];

        $pricing = $this->pricing->aiPricing($feature);
        if (! $pricing['enabled']) {
            return ['source' => 'platform', 'charge_minor' => 0, 'request_id' => $requestId, 'currency' => $pricing['currency']];
        }

        return DB::transaction(function () use ($user, $feature, $requestId, $pricing): array {
            // Serialize billing decisions for one user so concurrent requests
            // cannot consume the same free-allowance slot or race a request ID.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $usage = AiUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($usage?->status === 'completed') {
                return $this->reservationFromUsage($usage) + ['already_completed' => true];
            }
            if ($usage?->status === 'reserved') {
                return $this->reservationFromUsage($usage) + ['already_reserved' => true];
            }
            if ($usage?->status === 'reconciliation_required') {
                throw ValidationException::withMessages(['ai' => 'Billing reconciliation is still pending for this AI request. Please try a new request.']);
            }

            $usedQuery = AiUsageCharge::query()
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                // Reserved requests consume an allowance slot while they are in
                // flight so concurrent calls cannot all claim the same free slot.
                // Reconciliation-required requests delivered a valid answer and
                // therefore also count. Released/failed reservations do not.
                ->whereIn('status', ['reserved', 'completed', 'reconciliation_required']);
            if ($pricing['feature_specific']) $usedQuery->where('feature', $feature);
            $used = $usedQuery->count();
            $free = (int) $pricing['free_daily_requests'];
            $charge = $used < $free ? 0 : (int) $pricing['amount_minor'];
            $currency = strtoupper((string) $pricing['currency']);
            $source = $charge > 0 ? 'user_wallet' : ($used < $free ? 'free_allowance' : 'platform');
            $commercialAccountId = null;

            if ($charge > 0) {
                $commercial = app(AcademicSponsorshipService::class)->account($user, null, true, true);

                if ($commercial && (bool) data_get($commercial->metadata, 'sponsor_ai_usage', false)) {
                    $this->assertCurrency($currency, $commercial->currency, 'institution commercial account');
                    if ((int) $commercial->prepaid_balance_minor < $charge) {
                        throw ValidationException::withMessages(['ai' => 'Your institution AI allowance is currently exhausted.']);
                    }
                    $source = 'institution';
                    $commercialAccountId = $commercial->id;
                } else {
                    $wallet = $this->wallets->account($user);
                    $this->assertCurrency($currency, $wallet->currency, 'wallet');
                    if ($this->wallets->totalSpendableMinor($user) < $charge) {
                        throw ValidationException::withMessages(['ai' => 'Your balance is too low for this AI request. Add funds and try again.']);
                    }
                }
            }

            $attempt = max(1, (int) data_get($usage?->metadata, 'reservation_attempt', 0) + 1);
            $metadata = array_merge((array) ($usage?->metadata ?? []), [
                'funding_source' => $source,
                'commercial_account_id' => $commercialAccountId,
                'pricing_rule_id' => $pricing['rule']?->id,
                'pricing_rule_version' => $pricing['rule']?->version,
                'free_daily_requests' => $free,
                'feature_specific_allowance' => (bool) $pricing['feature_specific'],
                'reservation_attempt' => $attempt,
            ]);

            if (! $usage) {
                $usage = AiUsageCharge::query()->create([
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'university_id' => $user->university_id,
                    'feature' => $feature,
                    'provider' => null,
                    'model' => null,
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'provider_cost_micro_usd' => 0,
                    'user_charge_minor' => $charge,
                    'platform_margin_minor' => 0,
                    'currency' => $currency,
                    'status' => 'reserved',
                    'metadata' => $metadata,
                ]);
            } else {
                $usage->forceFill([
                    'user_id' => $user->id,
                    'university_id' => $user->university_id,
                    'feature' => $feature,
                    'user_charge_minor' => $charge,
                    'currency' => $currency,
                    'status' => 'reserved',
                    'metadata' => $metadata,
                ])->save();
            }

            if ($charge > 0 && $source === 'institution') {
                $commercial = CommercialAccount::query()->whereKey($commercialAccountId)->lockForUpdate()->firstOrFail();
                $commercial->decrement('prepaid_balance_minor', $charge);
                $this->ledger->post('ai-reserve:'.$usage->uuid.':'.$attempt, 'ai_usage_reserve', $currency, [
                    ['account_code' => 'institution_prepaid_liability:'.$commercial->id, 'direction' => 'debit', 'amount_minor' => $charge],
                    ['account_code' => 'ai_revenue_clearing', 'direction' => 'credit', 'amount_minor' => $charge],
                ], $user, ['ai_usage_charge_id' => $usage->id, 'ai_request_id' => $requestId, 'feature' => $feature]);
            } elseif ($charge > 0 && $source === 'user_wallet') {
                $this->wallets->debitSpending(
                    $user,
                    $charge,
                    'ai_usage_reserve',
                    $usage,
                    null,
                    'AI request funds reserved.',
                    ['ai_request_id' => $requestId, 'feature' => $feature, 'reservation_attempt' => $attempt]
                );
            }

            return $this->reservationFromUsage($usage);
        }, 3);
    }

    public function release(?User $user, array $reservation): void
    {
        if (! $user) return;
        $requestId = (string) ($reservation['request_id'] ?? '');
        if ($requestId === '') return;

        DB::transaction(function () use ($user, $requestId): void {
            $usage = AiUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if (! $usage || $usage->status !== 'reserved') return;

            $charge = (int) $usage->user_charge_minor;
            $source = (string) data_get($usage->metadata, 'funding_source', 'platform');
            $attempt = max(1, (int) data_get($usage->metadata, 'reservation_attempt', 1));

            if ($charge > 0 && $source === 'user_wallet') {
                $this->wallets->creditSpending(
                    $user,
                    $charge,
                    'ai_usage_release',
                    $usage,
                    null,
                    'AI request reservation released.',
                    ['ai_request_id' => $requestId, 'feature' => $usage->feature, 'reservation_attempt' => $attempt]
                );
            } elseif ($charge > 0 && $source === 'institution') {
                $accountId = (int) data_get($usage->metadata, 'commercial_account_id', 0);
                if ($accountId > 0) {
                    $account = CommercialAccount::query()->whereKey($accountId)->lockForUpdate()->firstOrFail();
                    $account->increment('prepaid_balance_minor', $charge);
                    $this->ledger->post('ai-release:'.$usage->uuid.':'.$attempt, 'ai_usage_release', $usage->currency, [
                        ['account_code' => 'ai_revenue_clearing', 'direction' => 'debit', 'amount_minor' => $charge],
                        ['account_code' => 'institution_prepaid_liability:'.$account->id, 'direction' => 'credit', 'amount_minor' => $charge],
                    ], $user, ['ai_usage_charge_id' => $usage->id, 'ai_request_id' => $requestId]);
                }
            }

            $usage->forceFill([
                'status' => 'released',
                'metadata' => array_merge((array) $usage->metadata, ['released_at' => now()->toIso8601String()]),
            ])->save();
        }, 3);
    }

    public function settle(?User $user, string $feature, AiResponse $response, array $reservation): void
    {
        if (! $response->success) {
            $this->release($user, $reservation);
            return;
        }

        $requestId = (string) ($reservation['request_id'] ?? $response->requestId);
        $providerCostMicroUsd = max(0, (int) ($response->metadata['provider_cost_micro_usd'] ?? 0));
        $fxMinor = Money::toMinor((string) SettingService::getGlobal('ai_local_currency_per_usd_reporting', '1500'));
        $providerCostLocalMinor = intdiv(($providerCostMicroUsd * $fxMinor) + 500_000, 1_000_000);

        DB::transaction(function () use ($user, $feature, $response, $reservation, $requestId, $providerCostMicroUsd, $providerCostLocalMinor): void {
            $usage = AiUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if ($usage?->status === 'completed') return;

            $charge = (int) ($usage?->user_charge_minor ?? $reservation['charge_minor'] ?? 0);
            $currency = strtoupper((string) ($usage?->currency ?? $reservation['currency'] ?? SettingService::getGlobal('currency', 'NGN')));
            $source = (string) data_get($usage?->metadata, 'funding_source', $reservation['source'] ?? 'platform');
            $attempt = max(1, (int) data_get($usage?->metadata, 'reservation_attempt', 1));
            $metadata = array_merge((array) ($usage?->metadata ?? []), [
                'funding_source' => $source,
                'provider_cost_local_minor' => $providerCostLocalMinor,
                'cached' => (bool) ($response->cached ?? false),
            ]);

            if (! $usage) {
                $usage = AiUsageCharge::query()->create([
                    'request_id' => $requestId,
                    'user_id' => $user?->id,
                    'university_id' => $user?->university_id,
                    'feature' => $feature,
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'input_tokens' => max(0, (int) ($response->inputTokens ?? 0)),
                    'output_tokens' => max(0, (int) ($response->outputTokens ?? 0)),
                    'provider_cost_micro_usd' => $providerCostMicroUsd,
                    'user_charge_minor' => $charge,
                    'platform_margin_minor' => $charge - $providerCostLocalMinor,
                    'currency' => $currency,
                    'status' => 'completed',
                    'metadata' => $metadata,
                ]);
            } else {
                $usage->forceFill([
                    'feature' => $feature,
                    'provider' => $response->provider,
                    'model' => $response->model,
                    'input_tokens' => max(0, (int) ($response->inputTokens ?? 0)),
                    'output_tokens' => max(0, (int) ($response->outputTokens ?? 0)),
                    'provider_cost_micro_usd' => $providerCostMicroUsd,
                    'platform_margin_minor' => $charge - $providerCostLocalMinor,
                    'status' => 'completed',
                    'metadata' => $metadata,
                ])->save();
            }

            if ($charge > 0 && in_array($source, ['user_wallet', 'institution'], true)) {
                $this->ledger->post('ai-settle:'.$usage->uuid.':'.$attempt, 'ai_usage_settlement', $currency, [
                    ['account_code' => 'ai_revenue_clearing', 'direction' => 'debit', 'amount_minor' => $charge],
                    ['account_code' => 'platform_ai_revenue', 'direction' => 'credit', 'amount_minor' => $charge],
                ], $user, ['ai_usage_charge_id' => $usage->id, 'provider' => $response->provider, 'model' => $response->model, 'feature' => $feature]);
            }
        }, 3);
    }

    public function markReconciliationRequired(string $requestId, string $reason = 'settlement_failed'): void
    {
        if (trim($requestId) === '') return;

        DB::transaction(function () use ($requestId, $reason): void {
            $usage = AiUsageCharge::query()->where('request_id', $requestId)->lockForUpdate()->first();
            if (! $usage || ! in_array($usage->status, ['reserved', 'released'], true)) return;

            $previousStatus = (string) $usage->status;
            $usage->forceFill([
                'status' => 'reconciliation_required',
                'metadata' => array_merge((array) $usage->metadata, [
                    'reconciliation_reason' => $reason,
                    'reconciliation_required_at' => now()->toIso8601String(),
                    'pre_reconciliation_status' => $previousStatus,
                    'reservation_released' => $previousStatus === 'released',
                ]),
            ])->save();
        }, 3);
    }

    /** @return array<string,mixed> */
    private function reservationFromUsage(AiUsageCharge $usage): array
    {
        return [
            'usage_charge_id' => $usage->id,
            'request_id' => $usage->request_id,
            'source' => (string) data_get($usage->metadata, 'funding_source', 'platform'),
            'commercial_account_id' => (int) data_get($usage->metadata, 'commercial_account_id', 0) ?: null,
            'charge_minor' => (int) $usage->user_charge_minor,
            'currency' => $usage->currency,
            'status' => $usage->status,
        ];
    }

    private function assertCurrency(string $expected, ?string $actual, string $source): void
    {
        $actual = strtoupper((string) $actual);
        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'ai' => "AI pricing is in {$expected}, but your {$source} is in {$actual}. Ask an administrator to align the currencies before charging.",
            ]);
        }
    }
}
