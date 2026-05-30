<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', SubscriptionPlan::class);

        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price_per_month')->get();

        return view('admin.subscription-plans.index', compact('plans'));
    }

    public function create()
    {
        $this->authorize('create', SubscriptionPlan::class);

        return view('admin.subscription-plans.create', [
            'billingCycles' => $this->getBillingCycles(),
            'planTypes' => $this->getPlanTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', SubscriptionPlan::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:subscription_plans,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'required|in:b2c,b2b,free',
            'price_per_month' => 'required|numeric|min:0',
            'price_per_semester' => 'nullable|numeric|min:0',
            'price_per_year' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,semester,yearly',
            'available_billing_cycles' => 'nullable|array',
            'trial_days' => 'nullable|integer|min:0',
            'has_trial' => 'boolean',
            'max_courses' => 'nullable|integer|min:1',
            'max_students_per_course' => 'nullable|integer|min:1',
            'max_file_upload_size_mb' => 'nullable|integer|min:1',
            'max_storage_gb' => 'nullable|integer|min:1',
            'allow_group_submissions' => 'boolean',
            'allow_rubrics' => 'boolean',
            'allow_attendance_tracking' => 'boolean',
            'allow_document_generation' => 'boolean',
            'allow_api_access' => 'boolean',
            'allow_white_label' => 'boolean',
            'max_administrators' => 'nullable|integer|min:1',
            'is_recommended' => 'boolean',
            'min_seats' => 'nullable|integer|min:1',
            'max_seats' => 'nullable|integer|min:1',
            'priority_support' => 'boolean',
            'dedicated_account_manager' => 'boolean',
            'sso_enabled' => 'boolean',
            'api_access' => 'boolean',
            'custom_branding' => 'boolean',
            'can_upgrade' => 'boolean',
            'can_downgrade' => 'boolean',
            'prorated_upgrades' => 'boolean',
            'cancellation_minimum_days' => 'nullable|integer|min:0',
            'refundable' => 'boolean',
            'refund_period_days' => 'nullable|integer|min:0',
            'custom_pricing' => 'boolean',
            'pricing_note' => 'nullable|string',
            'features' => 'nullable|json',
            'limits' => 'nullable|json',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $features = $request->input('features', []);
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }

        $limits = $request->input('limits', []);
        if (is_string($limits)) {
            $limits = json_decode($limits, true) ?: [];
        }

        $plan = SubscriptionPlan::create([
            'uuid' => Str::uuid(),
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'plan_type' => $validated['plan_type'],
            'price_per_month' => $validated['price_per_month'],
            'price_per_semester' => $validated['price_per_semester'] ?? null,
            'price_per_year' => $validated['price_per_year'] ?? null,
            'billing_cycle' => $validated['billing_cycle'],
            'available_billing_cycles' => $validated['available_billing_cycles'] ?? [],
            'trial_days' => $validated['trial_days'] ?? 0,
            'has_trial' => $validated['has_trial'] ?? false,
            'features' => $features,
            'limits' => $limits,
            'max_courses' => $validated['max_courses'] ?? null,
            'max_students_per_course' => $validated['max_students_per_course'] ?? null,
            'max_file_upload_size_mb' => $validated['max_file_upload_size_mb'] ?? 50,
            'max_storage_gb' => $validated['max_storage_gb'] ?? null,
            'allow_group_submissions' => $validated['allow_group_submissions'] ?? true,
            'allow_rubrics' => $validated['allow_rubrics'] ?? true,
            'allow_attendance_tracking' => $validated['allow_attendance_tracking'] ?? true,
            'allow_document_generation' => $validated['allow_document_generation'] ?? false,
            'allow_api_access' => $validated['allow_api_access'] ?? false,
            'allow_white_label' => $validated['allow_white_label'] ?? false,
            'max_administrators' => $validated['max_administrators'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_recommended' => $validated['is_recommended'] ?? false,
            'min_seats' => $validated['min_seats'] ?? null,
            'max_seats' => $validated['max_seats'] ?? null,
            'priority_support' => $validated['priority_support'] ?? false,
            'dedicated_account_manager' => $validated['dedicated_account_manager'] ?? false,
            'sso_enabled' => $validated['sso_enabled'] ?? false,
            'api_access' => $validated['api_access'] ?? false,
            'custom_branding' => $validated['custom_branding'] ?? false,
            'can_upgrade' => $validated['can_upgrade'] ?? true,
            'can_downgrade' => $validated['can_downgrade'] ?? true,
            'prorated_upgrades' => $validated['prorated_upgrades'] ?? true,
            'cancellation_minimum_days' => $validated['cancellation_minimum_days'] ?? 0,
            'refundable' => $validated['refundable'] ?? false,
            'refund_period_days' => $validated['refund_period_days'] ?? 0,
            'custom_pricing' => $validated['custom_pricing'] ?? false,
            'pricing_note' => $validated['pricing_note'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect()->route('admin.subscription-plans')
            ->with('success', 'Subscription plan created successfully.');
    }

    public function edit(SubscriptionPlan $subscriptionPlan)
    {
        $this->authorize('update', $subscriptionPlan);

        return view('admin.subscription-plans.edit', [
            'subscriptionPlan' => $subscriptionPlan,
            'billingCycles' => $this->getBillingCycles(),
            'planTypes' => $this->getPlanTypes(),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->authorize('update', $subscriptionPlan);

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'plan_type' => 'required|in:b2c,b2b,free',
            'price_per_month' => 'required|numeric|min:0',
            'price_per_semester' => 'nullable|numeric|min:0',
            'price_per_year' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,semester,yearly',
            'available_billing_cycles' => 'nullable|array',
            'trial_days' => 'nullable|integer|min:0',
            'has_trial' => 'boolean',
            'max_courses' => 'nullable|integer|min:1',
            'max_students_per_course' => 'nullable|integer|min:1',
            'max_file_upload_size_mb' => 'nullable|integer|min:1',
            'max_storage_gb' => 'nullable|integer|min:1',
            'allow_group_submissions' => 'boolean',
            'allow_rubrics' => 'boolean',
            'allow_attendance_tracking' => 'boolean',
            'allow_document_generation' => 'boolean',
            'allow_api_access' => 'boolean',
            'allow_white_label' => 'boolean',
            'max_administrators' => 'nullable|integer|min:1',
            'is_recommended' => 'boolean',
            'min_seats' => 'nullable|integer|min:1',
            'max_seats' => 'nullable|integer|min:1',
            'priority_support' => 'boolean',
            'dedicated_account_manager' => 'boolean',
            'sso_enabled' => 'boolean',
            'api_access' => 'boolean',
            'custom_branding' => 'boolean',
            'can_upgrade' => 'boolean',
            'can_downgrade' => 'boolean',
            'prorated_upgrades' => 'boolean',
            'cancellation_minimum_days' => 'nullable|integer|min:0',
            'refundable' => 'boolean',
            'refund_period_days' => 'nullable|integer|min:0',
            'custom_pricing' => 'boolean',
            'pricing_note' => 'nullable|string',
            'features' => 'nullable|json',
            'limits' => 'nullable|json',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $features = $request->input('features', []);
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }

        $limits = $request->input('limits', []);
        if (is_string($limits)) {
            $limits = json_decode($limits, true) ?: [];
        }

        $subscriptionPlan->update([
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'plan_type' => $validated['plan_type'],
            'price_per_month' => $validated['price_per_month'],
            'price_per_semester' => $validated['price_per_semester'] ?? null,
            'price_per_year' => $validated['price_per_year'] ?? null,
            'billing_cycle' => $validated['billing_cycle'],
            'available_billing_cycles' => $validated['available_billing_cycles'] ?? [],
            'trial_days' => $validated['trial_days'] ?? 0,
            'has_trial' => $validated['has_trial'] ?? false,
            'features' => $features,
            'limits' => $limits,
            'max_courses' => $validated['max_courses'] ?? null,
            'max_students_per_course' => $validated['max_students_per_course'] ?? null,
            'max_file_upload_size_mb' => $validated['max_file_upload_size_mb'] ?? 50,
            'max_storage_gb' => $validated['max_storage_gb'] ?? null,
            'allow_group_submissions' => $validated['allow_group_submissions'] ?? true,
            'allow_rubrics' => $validated['allow_rubrics'] ?? true,
            'allow_attendance_tracking' => $validated['allow_attendance_tracking'] ?? true,
            'allow_document_generation' => $validated['allow_document_generation'] ?? false,
            'allow_api_access' => $validated['allow_api_access'] ?? false,
            'allow_white_label' => $validated['allow_white_label'] ?? false,
            'max_administrators' => $validated['max_administrators'] ?? null,
            'is_active' => $validated['is_active'] ?? $subscriptionPlan->is_active,
            'is_recommended' => $validated['is_recommended'] ?? false,
            'min_seats' => $validated['min_seats'] ?? null,
            'max_seats' => $validated['max_seats'] ?? null,
            'priority_support' => $validated['priority_support'] ?? false,
            'dedicated_account_manager' => $validated['dedicated_account_manager'] ?? false,
            'sso_enabled' => $validated['sso_enabled'] ?? false,
            'api_access' => $validated['api_access'] ?? false,
            'custom_branding' => $validated['custom_branding'] ?? false,
            'can_upgrade' => $validated['can_upgrade'] ?? true,
            'can_downgrade' => $validated['can_downgrade'] ?? true,
            'prorated_upgrades' => $validated['prorated_upgrades'] ?? true,
            'cancellation_minimum_days' => $validated['cancellation_minimum_days'] ?? 0,
            'refundable' => $validated['refundable'] ?? false,
            'refund_period_days' => $validated['refund_period_days'] ?? 0,
            'custom_pricing' => $validated['custom_pricing'] ?? false,
            'pricing_note' => $validated['pricing_note'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $subscriptionPlan->sort_order,
        ]);

        return redirect()->route('admin.subscription-plans')
            ->with('success', 'Subscription plan updated successfully.');
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $this->authorize('delete', $subscriptionPlan);

        // Check if plan is in use
        $activeSubscriptions = UserSubscription::where('plan_id', $subscriptionPlan->id)
            ->where('status', 'active')
            ->exists();

        if ($activeSubscriptions) {
            return back()->with('error', 'Cannot delete plan with active subscriptions.');
        }

        $subscriptionPlan->delete();

        return redirect()->route('admin.subscription-plans')
            ->with('success', 'Subscription plan deleted successfully.');
    }

    private function getBillingCycles(): array
    {
        return [
            'monthly' => 'Monthly',
            'semester' => 'Semester (4 months)',
            'yearly' => 'Yearly',
        ];
    }

    private function getPlanTypes(): array
    {
        return [
            'b2c' => 'B2C (Business to Consumer)',
            'b2b' => 'B2B (Business to Business)',
            'free' => 'Free Plan',
        ];
    }
}
