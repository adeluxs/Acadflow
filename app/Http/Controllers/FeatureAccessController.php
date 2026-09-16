<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Models\FeatureUsageCharge;
use App\Services\Commerce\FeatureBillingService;
use App\Services\Commerce\PricingCatalogService;
use App\Services\Commerce\WalletService;
use App\Services\InstitutionAdoptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeatureAccessController extends Controller
{
    public function index(Request $request, WalletService $wallets, PricingCatalogService $pricing, InstitutionAdoptionService $institutionAdoption): View
    {
        $user = $request->user();
        $wallet = $wallets->account($user);
        $institutionEligible = (bool) ($user->university_id && $institutionAdoption->canReceiveInstitutionBenefits($user));
        $commercial = $institutionEligible
            ? CommercialAccount::query()->where('university_id', $user->university_id)->where('status', 'active')->first()
            : null;

        $featureRows = collect($pricing->featureDefinitions())->map(function (array $definition, string $feature) use ($pricing, $user, $commercial): array {
            $rule = $pricing->featureRule($feature);
            $pricingType = $rule ? $pricing->pricingType($rule) : null;
            $entitlement = FeatureEntitlement::query()
                ->where('user_id', $user->id)
                ->where('feature', $feature)
                ->where('status', 'active')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('id')
                ->first();

            return [
                ...$definition,
                'key' => $feature,
                'rule' => $rule,
                'pricing_type' => $pricingType,
                'entitlement' => $entitlement,
                'sponsored' => (bool) ($commercial?->sponsorsFeature($feature)),
            ];
        })->values();

        $aiRows = collect($pricing->aiDefinitions())->map(function (array $definition, string $feature) use ($pricing): array {
            return [...$definition, 'key' => $feature, 'pricing' => $pricing->aiPricing($feature)];
        })->values();

        $featureUsage = FeatureUsageCharge::query()->where('user_id', $user->id)->latest()->limit(30)->get();
        $aiUsage = AiUsageCharge::query()->where('user_id', $user->id)->latest()->limit(30)->get();

        return view('commerce.features', compact('wallet', 'commercial', 'institutionEligible', 'featureRows', 'aiRows', 'featureUsage', 'aiUsage'));
    }

    public function purchase(Request $request, FeatureBillingService $billing, PricingCatalogService $pricing): RedirectResponse
    {
        $features = array_keys($pricing->featureDefinitions());
        $data = $request->validate([
            'feature' => ['required', 'string', Rule::in($features)],
            'request_id' => ['nullable', 'string', 'max:160'],
        ]);

        $requestId = trim((string) ($data['request_id'] ?? '')) ?: (string) Str::uuid();
        $billing->purchaseFixedAccess($request->user(), (string) $data['feature'], $requestId);

        return back()->with('success', 'Paid feature access activated successfully.');
    }
}
