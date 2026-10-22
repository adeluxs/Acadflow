<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Models\User;

/** Independent feature entitlement engine; payment method is intentionally irrelevant. */
class EntitlementService
{
    public function __construct(
        private readonly \App\Services\InstitutionAdoptionService $institutionAdoption,
        private readonly PricingCatalogService $pricing,
    ) {}

    public function has(User $user, string $feature): bool
    {
        if ($user->isAdmin()) return true;

        $rule = $this->pricing->featureRule($feature);
        if (! $rule) return true;

        // Pay-per-use features do not require a pre-purchased entitlement. The
        // action boundary performs reserve/settle/refund through FeatureBillingService.
        if ($this->pricing->pricingType($rule) === 'pay_per_use') return true;

        $granted = FeatureEntitlement::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->where('access_type', '!=', 'institution_sponsored')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();
        if ($granted) return true;

        if (app(AcademicSponsorshipService::class)->account($user, $feature)) return true;

        return false;
    }

    public function grant(User $user, string $feature, string $accessType = 'granted', ?int $units = null, ?\DateTimeInterface $expiresAt = null, array $metadata = []): FeatureEntitlement
    {
        return FeatureEntitlement::create([
            'user_id' => $user->id,
            'feature' => $feature,
            'access_type' => $accessType,
            'remaining_units' => $units,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => $expiresAt,
            'metadata' => $metadata,
        ]);
    }
}
