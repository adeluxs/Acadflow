<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AiUsageCharge;
use App\Models\FeatureEntitlement;
use App\Models\FeatureUsageCharge;
use App\Services\Commerce\AcademicSponsorshipService;
use App\Services\Commerce\FeatureBillingService;
use App\Services\Commerce\PackageAccessService;
use App\Services\Commerce\PricingCatalogService;
use App\Services\Commerce\WalletService;
use App\Services\InstitutionAdoptionService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeatureAccessController extends Controller
{
    public function index(Request $request, WalletService $wallets, PricingCatalogService $pricing, PackageAccessService $packages, InstitutionAdoptionService $institutionAdoption): View
    {
        $user = $request->user();
        $wallet = $wallets->account($user);
        $institutionEligible = $institutionAdoption->canReceiveInstitutionBenefits($user);
        $sponsors = app(AcademicSponsorshipService::class)->accounts($user);
        $commercial = $sponsors->first();
        $definitions = $pricing->featureDefinitions();
        $aiDefinitions = $pricing->aiDefinitions();
        $aiMonetizationEnabled = (bool) SettingService::getGlobal('ai_monetization_enabled', false);
        $rules = $pricing->activeRules(array_merge(array_map(fn ($key) => 'feature.'.$key, array_keys($definitions)), array_map(fn ($key) => 'ai.'.$key, array_keys($aiDefinitions))));
        $entitlementSummary = FeatureEntitlement::query()->from('feature_entitlements as entitlement_history')
            ->where('entitlement_history.user_id', $user->id)
            ->whereIn('entitlement_history.feature', array_keys($definitions))
            ->where('entitlement_history.access_type', '!=', 'institution_sponsored')
            ->selectRaw('entitlement_history.feature, MAX(entitlement_history.id) AS latest_id, MAX(CASE WHEN entitlement_history.access_type = ? THEN 1 ELSE 0 END) AS trial_used', ['trial'])
            ->groupBy('entitlement_history.feature');
        $entitlements = FeatureEntitlement::query()
            ->joinSub($entitlementSummary, 'entitlement_summary', fn ($join) => $join->on('feature_entitlements.id', '=', 'entitlement_summary.latest_id'))
            ->select('feature_entitlements.*')->addSelect('entitlement_summary.trial_used')->get()->keyBy('feature');

        $featureRows = collect($definitions)->filter(function (array $definition) use ($user): bool {
            $roles = (array) ($definition['roles'] ?? []);

            return $roles === [] || in_array($user->role, $roles, true);
        })->map(function (array $definition, string $feature) use ($user, $pricing, $packages, $rules, $entitlements, $sponsors, $aiDefinitions, $aiMonetizationEnabled): array {
            $rule = $rules->get('feature.'.$feature);
            $pricingType = $rule ? $pricing->pricingType($rule) : null;
            $latestEntitlement = $entitlements->get($feature);
            $sponsored = $sponsors->contains(fn ($account) => $account->sponsorsFeature($feature));
            $packageStatus = data_get($definition, 'package', false)
                ? $packages->statusFrom($user, $feature, $rule, $latestEntitlement, $sponsored)
                : null;
            $entitlement = $latestEntitlement
                && $latestEntitlement->status === 'active'
                && (! $latestEntitlement->starts_at || ! $latestEntitlement->starts_at->isFuture())
                && (! $latestEntitlement->expires_at || $latestEntitlement->expires_at->isFuture())
                    ? $latestEntitlement
                    : null;
            if ($packageStatus && $packageStatus['entitlement']) {
                $entitlement = $packageStatus['entitlement'];
            }

            return [
                ...$definition,
                'key' => $feature,
                'rule' => $rule,
                'pricing_type' => $pricingType,
                'entitlement' => $entitlement,
                'sponsored' => $sponsored,
                'package_status' => $packageStatus,
                'offers' => $rule && data_get($definition, 'package', false) ? $packages->offers($rule, $feature) : [],
                'trial_days' => $rule ? max(0, (int) data_get($rule->metadata, 'trial_days', data_get($definition, 'default_trial_days', 0))) : 0,
                'trial_used' => (bool) ($latestEntitlement?->trial_used ?? false),
                'available_for_purchase' => ! data_get($definition, 'requires_ai_monetization', false) || $aiMonetizationEnabled,
                'ai_tools' => collect((array) ($definition['ai_features'] ?? []))->map(fn (string $aiFeature): array => [
                    'key' => $aiFeature,
                    'title' => data_get($aiDefinitions, $aiFeature.'.title', str($aiFeature)->replace('_', ' ')->title()->toString()),
                ])->values()->all(),
            ];
        })->values();

        $offeredPackages = $featureRows->where('package', true)->filter(fn (array $row): bool => (bool) $row['rule'] && $row['available_for_purchase']);
        $aiRows = collect($aiDefinitions)->map(function (array $definition, string $feature) use ($pricing, $rules, $offeredPackages): array {
            $includedIn = $offeredPackages
                ->filter(fn (array $package): bool => in_array($feature, array_column($package['ai_tools'], 'key'), true))
                ->map(fn (array $package): string => (string) ($package['short_title'] ?? $package['title']))
                ->values()->all();

            return [...$definition, 'key' => $feature, 'pricing' => $pricing->aiPricingFromRule($rules->get('ai.'.$feature)), 'included_in_packages' => $includedIn];
        })->values();

        $featureUsage = FeatureUsageCharge::query()->where('user_id', $user->id)->latest()->limit(30)->get();
        $aiUsage = AiUsageCharge::query()->where('user_id', $user->id)->latest()->limit(30)->get();
        $aiMonthSpendMinor = (int) AiUsageCharge::query()->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereIn('status', ['reserved', 'completed', 'reconciliation_required'])
            ->sum('user_charge_minor');

        return view('commerce.features', compact('wallet', 'commercial', 'institutionEligible', 'featureRows', 'aiRows', 'featureUsage', 'aiUsage', 'aiMonthSpendMinor'));
    }

    public function purchase(Request $request, FeatureBillingService $billing, PricingCatalogService $pricing): RedirectResponse
    {
        $features = array_keys($pricing->featureDefinitions());
        $data = $request->validate([
            'feature' => ['required', 'string', Rule::in($features)],
            'billing_period' => ['nullable', Rule::in(['monthly', 'semester'])],
            'request_id' => ['nullable', 'string', 'max:160'],
        ]);

        $this->assertRole($request, (string) $data['feature']);

        $requestId = trim((string) ($data['request_id'] ?? '')) ?: (string) Str::uuid();
        $billing->purchaseFixedAccess($request->user(), (string) $data['feature'], $requestId, (string) ($data['billing_period'] ?? 'monthly'));

        return back()->with('success', 'Paid feature access activated successfully.');
    }

    public function startTrial(Request $request, PackageAccessService $packages, PricingCatalogService $pricing): RedirectResponse
    {
        $data = $request->validate([
            'feature' => ['required', 'string', Rule::in(array_keys($pricing->featureDefinitions()))],
        ]);
        $this->assertRole($request, (string) $data['feature']);
        $packages->startTrial($request->user(), (string) $data['feature']);

        return back()->with('success', 'Your package trial is now active.');
    }

    public function updateAiSpendingLimit(Request $request, WalletService $wallets): RedirectResponse
    {
        $data = $request->validate([
            'ai_monthly_spending_limit' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],
        ]);
        $value = trim((string) ($data['ai_monthly_spending_limit'] ?? ''));
        $wallets->account($request->user())->forceFill([
            'ai_monthly_spending_limit_minor' => $value === '' || $value === '0' ? null : Money::toMinor($value),
        ])->save();

        return back()->with('success', 'Your monthly AI spending limit was updated.');
    }

    private function assertRole(Request $request, string $feature): void
    {
        $definition = (array) config('monetization.pricing_features.'.$feature, []);
        $roles = (array) ($definition['roles'] ?? []);
        if ($roles !== [] && ! in_array($request->user()->role, $roles, true)) {
            throw ValidationException::withMessages(['feature' => 'This paid feature is not available for your account role.']);
        }
    }
}
