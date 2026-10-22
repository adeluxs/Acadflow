<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\PricingRule;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\JoinClause;

class PricingCatalogService
{
    /** Latest currently effective global rules, fetched once for catalogue views. */
    public function activeRules(array $keys): Collection
    {
        if ($keys === []) {
            return collect();
        }
        $eligible = PricingRule::query()->whereIn('pricing_rules.key', $keys)->where('pricing_rules.scope_type', 'global')->where('pricing_rules.scope_id', 0)
            ->where('pricing_rules.enabled', true)
            ->where(fn ($q) => $q->whereNull('pricing_rules.starts_at')->orWhere('pricing_rules.starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('pricing_rules.ends_at')->orWhere('pricing_rules.ends_at', '>', now()));
        $latest = (clone $eligible)->select('pricing_rules.key')->selectRaw('MAX(version) AS latest_version')->groupBy('pricing_rules.key');

        return $eligible->select('pricing_rules.*')->joinSub($latest, 'latest_pricing', fn (JoinClause $join) => $join
            ->on('pricing_rules.key', '=', 'latest_pricing.key')->on('pricing_rules.version', '=', 'latest_pricing.latest_version'))
            ->get()->keyBy('key');
    }

    public function activeRule(string $key): ?PricingRule
    {
        return PricingRule::query()
            ->where('key', $key)
            ->where('scope_type', 'global')
            ->where('scope_id', 0)
            ->where('enabled', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('version')
            ->first();
    }

    public function featureRule(string $feature): ?PricingRule
    {
        return $this->activeRule('feature.'.$feature);
    }

    public function aiRule(string $feature): ?PricingRule
    {
        return $this->activeRule('ai.'.$feature);
    }

    public function pricingType(PricingRule $rule): string
    {
        $type = (string) data_get($rule->metadata, 'pricing_type', '');
        return $type === 'fixed_feature_access' ? 'fixed_access' : ($type ?: 'fixed_access');
    }

    /** @return array{enabled:bool,amount_minor:int,free_daily_requests:int,currency:string,rule:?PricingRule,feature_specific:bool} */
    public function aiPricing(string $feature): array
    {
        return $this->aiPricingFromRule($this->aiRule($feature));
    }

    /** Same pricing calculation for a rule supplied by a bulk catalogue lookup. */
    public function aiPricingFromRule(?PricingRule $rule): array
    {
        $enabled = (bool) SettingService::getGlobal('ai_monetization_enabled', false);

        if ($rule) {
            return [
                'enabled' => $enabled,
                'amount_minor' => max(0, (int) ($rule->unit_amount_minor ?? 0)),
                'free_daily_requests' => max(0, (int) data_get($rule->metadata, 'free_daily_requests', 0)),
                'currency' => strtoupper((string) ($rule->currency ?: SettingService::getGlobal('currency', 'NGN'))),
                'rule' => $rule,
                'feature_specific' => true,
            ];
        }

        return [
            'enabled' => $enabled,
            'amount_minor' => max(0, Money::toMinor((string) SettingService::getGlobal('ai_request_charge_amount', '25'))),
            'free_daily_requests' => max(0, (int) SettingService::getGlobal('ai_free_daily_requests', 3)),
            'currency' => strtoupper((string) SettingService::getGlobal('currency', 'NGN')),
            'rule' => null,
            'feature_specific' => false,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public function featureDefinitions(): array
    {
        return (array) config('monetization.pricing_features', []);
    }

    /** @return array<string,array<string,mixed>> */
    public function aiDefinitions(): array
    {
        $definitions = (array) config('monetization.ai_features', []);
        foreach ((array) config('ai.features', []) as $feature) {
            if (! isset($definitions[$feature])) {
                $definitions[$feature] = [
                    'title' => str((string) $feature)->replace('_', ' ')->title()->toString(),
                    'category' => 'AI Assistants',
                ];
            }
        }
        return $definitions;
    }
}
