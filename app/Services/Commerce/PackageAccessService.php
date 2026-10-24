<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\FeatureEntitlement;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageAccessService
{
    public function __construct(
        private readonly PricingCatalogService $pricing,
        private readonly EntitlementService $entitlements,
    ) {}

    /** @return array<string,mixed> */
    public function definition(string $feature): array
    {
        return (array) config('monetization.pricing_features.'.$feature, []);
    }

    public function isPackage(string $feature): bool
    {
        return (bool) data_get($this->definition($feature), 'package', false);
    }

    /** @return array{state:string,entitlement:?FeatureEntitlement,rule:?PricingRule,grace_ends_at:?Carbon} */
    public function status(User $user, string $feature): array
    {
        $rule = $this->pricing->featureRule($feature);
        $entitlement = FeatureEntitlement::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->where('access_type', '!=', 'institution_sponsored')
            ->latest('id')
            ->first();

        return $this->statusFrom(
            $user,
            $feature,
            $rule,
            $entitlement,
            (bool) app(AcademicSponsorshipService::class)->account($user, $feature),
        );
    }

    /** @return array{state:string,entitlement:?FeatureEntitlement,rule:?PricingRule,grace_ends_at:?Carbon} */
    public function statusFrom(User $user, string $feature, ?PricingRule $rule, ?FeatureEntitlement $entitlement, bool $sponsored): array
    {
        if (! $rule || $user->isAdmin()) {
            return ['state' => $rule ? 'admin' : 'free', 'entitlement' => null, 'rule' => $rule, 'grace_ends_at' => null];
        }

        if ($sponsored) {
            return ['state' => 'sponsored', 'entitlement' => null, 'rule' => $rule, 'grace_ends_at' => null];
        }

        if ($entitlement && $entitlement->status === 'active'
            && (! $entitlement->starts_at || ! $entitlement->starts_at->isFuture())
            && (! $entitlement->expires_at || $entitlement->expires_at->isFuture())) {
            return [
                'state' => $entitlement->access_type === 'trial' ? 'trial' : 'active',
                'entitlement' => $entitlement,
                'rule' => $rule,
                'grace_ends_at' => null,
            ];
        }

        $graceDays = max(0, (int) data_get($rule->metadata, 'grace_days', data_get($this->definition($feature), 'default_grace_days', 0)));
        $graceEndsAt = $entitlement?->expires_at?->copy()->addDays($graceDays);
        if ($graceDays > 0 && $graceEndsAt?->isFuture()) {
            return ['state' => 'grace', 'entitlement' => $entitlement, 'rule' => $rule, 'grace_ends_at' => $graceEndsAt];
        }

        return ['state' => 'locked', 'entitlement' => $entitlement, 'rule' => $rule, 'grace_ends_at' => null];
    }

    /** @return array<string,array{label:string,amount_minor:int,duration_days:int,included_ai_requests:int}> */
    public function offers(PricingRule $rule, string $feature): array
    {
        $definition = $this->definition($feature);
        $monthly = [
            'label' => 'Monthly',
            'amount_minor' => max(0, (int) $rule->unit_amount_minor),
            'duration_days' => max(1, (int) data_get($rule->metadata, 'duration_days', data_get($definition, 'default_duration_days', 30))),
            'included_ai_requests' => max(0, (int) data_get($rule->metadata, 'included_ai_requests', 0)),
        ];
        $offers = ['monthly' => $monthly];
        $semesterAmount = max(0, (int) data_get($rule->metadata, 'semester_amount_minor', 0));
        if ($semesterAmount > 0) {
            $offers['semester'] = [
                'label' => 'Semester',
                'amount_minor' => $semesterAmount,
                'duration_days' => max(1, (int) data_get($rule->metadata, 'semester_duration_days', data_get($definition, 'default_semester_duration_days', 120))),
                'included_ai_requests' => max(0, (int) data_get($rule->metadata, 'semester_included_ai_requests', 0)),
            ];
        }

        return $offers;
    }

    /** @return array{label:string,amount_minor:int,duration_days:int,included_ai_requests:int} */
    public function offer(PricingRule $rule, string $feature, string $billingPeriod): array
    {
        $offers = $this->offers($rule, $feature);
        if (! isset($offers[$billingPeriod])) {
            throw ValidationException::withMessages(['billing_period' => 'Choose an available access period.']);
        }

        return $offers[$billingPeriod];
    }

    public function packageForAi(User $user, string $aiFeature): ?string
    {
        foreach ((array) config('monetization.pricing_features', []) as $feature => $definition) {
            if (! data_get($definition, 'package', false)) {
                continue;
            }
            if (! in_array($user->role, (array) ($definition['roles'] ?? []), true)) {
                continue;
            }
            if (in_array($aiFeature, (array) ($definition['ai_features'] ?? []), true)) {
                return (string) $feature;
            }
        }

        return null;
    }

    public function consumeIncludedAi(User $user, string $aiFeature): ?FeatureEntitlement
    {
        $package = $this->packageForAi($user, $aiFeature);
        if (! $package) {
            return null;
        }

        $entitlement = FeatureEntitlement::query()
            ->where('user_id', $user->id)
            ->where('feature', $package)
            ->where('status', 'active')
            ->where('remaining_units', '>', 0)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $entitlement) {
            return null;
        }
        $entitlement->decrement('remaining_units');

        return $entitlement->refresh();
    }

    public function restoreIncludedAi(int $entitlementId): void
    {
        if ($entitlementId <= 0) {
            return;
        }
        FeatureEntitlement::query()->whereKey($entitlementId)->lockForUpdate()->first()?->increment('remaining_units');
    }

    public function startTrial(User $user, string $feature): FeatureEntitlement
    {
        $rule = $this->pricing->featureRule($feature);
        $definition = $this->definition($feature);
        if (data_get($definition, 'requires_ai_monetization', false)
            && ! (bool) SettingService::getGlobal('ai_monetization_enabled', false)) {
            throw ValidationException::withMessages(['feature' => 'This AI package is unavailable while extra AI charging is disabled.']);
        }
        $days = max(0, (int) data_get($rule?->metadata, 'trial_days', data_get($definition, 'default_trial_days', 0)));
        if (! $rule || ! $this->isPackage($feature) || $days <= 0) {
            throw ValidationException::withMessages(['feature' => 'A trial is not currently available for this package.']);
        }

        return DB::transaction(function () use ($user, $feature, $rule, $days): FeatureEntitlement {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (FeatureEntitlement::query()->where('user_id', $user->id)->where('feature', $feature)->exists()) {
                throw ValidationException::withMessages(['feature' => 'This package trial has already been used.']);
            }
            if ($this->entitlements->has($user, $feature)) {
                throw ValidationException::withMessages(['feature' => 'You already have access to this package.']);
            }

            $included = max(0, (int) data_get($rule->metadata, 'trial_included_ai_requests', 0));

            return $this->entitlements->grant($user, $feature, 'trial', $included, now()->addDays($days), [
                'pricing_rule_id' => $rule->id,
                'billing_period' => 'trial',
                'included_ai_requests_total' => $included,
            ]);
        }, 3);
    }
}
