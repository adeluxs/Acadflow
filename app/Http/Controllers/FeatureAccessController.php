<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiUsageCharge;
use App\Models\FeatureEntitlement;
use App\Models\FeatureUsageCharge;
use App\Services\Commerce\FeatureBillingService;
use App\Services\Commerce\PricingCatalogService;
use App\Services\Commerce\WalletService;
use App\Services\Commerce\AcademicSponsorshipService;
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
        $institutionEligible = $institutionAdoption->canReceiveInstitutionBenefits($user);
        $sponsors = app(AcademicSponsorshipService::class)->accounts($user);
        $commercial = $sponsors->first();
        $definitions = $pricing->featureDefinitions();
        $aiDefinitions = $pricing->aiDefinitions();
        $rules = $pricing->activeRules(array_merge(array_map(fn ($key) => 'feature.'.$key, array_keys($definitions)), array_map(fn ($key) => 'ai.'.$key, array_keys($aiDefinitions))));
        $entitlementQuery = FeatureEntitlement::query()->where('user_id', $user->id)->whereIn('feature', array_keys($definitions))
            ->where('access_type', '!=', 'institution_sponsored')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $entitlements = FeatureEntitlement::query()->whereIn('id', (clone $entitlementQuery)->selectRaw('MAX(id)')->groupBy('feature'))->get()->keyBy('feature');

        $featureRows = collect($definitions)->map(function (array $definition, string $feature) use ($pricing, $rules, $entitlements, $sponsors): array {
            $rule = $rules->get('feature.'.$feature);
            $pricingType = $rule ? $pricing->pricingType($rule) : null;
            $entitlement = $entitlements->get($feature);

            return [
                ...$definition,
                'key' => $feature,
                'rule' => $rule,
                'pricing_type' => $pricingType,
                'entitlement' => $entitlement,
                'sponsored' => $sponsors->contains(fn ($account) => $account->sponsorsFeature($feature)),
            ];
        })->values();

        $aiRows = collect($aiDefinitions)->map(function (array $definition, string $feature) use ($pricing, $rules): array {
            return [...$definition, 'key' => $feature, 'pricing' => $pricing->aiPricingFromRule($rules->get('ai.'.$feature))];
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
