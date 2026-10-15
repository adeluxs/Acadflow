<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\CommercialAccountRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SettingService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InstitutionCommercialAccountController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()->isUniversityAdmin(), 403);
        $universityId = (int) $request->user()->university_id;
        abort_if($universityId <= 0, 403, 'Your administrator account is not linked to an institution.');

        $account = CommercialAccount::query()->where('university_id', $universityId)->first();
        $requests = CommercialAccountRequest::query()
            ->where('university_id', $universityId)
            ->with('reviewer:id,first_name,last_name')
            ->latest()
            ->limit(20)
            ->get();
        $pricingFeatures = collect((array) config('monetization.pricing_features', []));
        $currency = strtoupper((string) ($account?->currency ?: SettingService::get('currency', 'NGN', $universityId)));
        $monthStart = now()->startOfMonth();
        $institutionAiSpendMinor = (int) AiUsageCharge::query()
            ->where('university_id', $universityId)
            ->where('currency', $currency)
            ->where('metadata->funding_source', 'institution')
            ->where('created_at', '>=', $monthStart)
            ->sum('user_charge_minor');
        $aiRequests = (int) AiUsageCharge::query()
            ->where('university_id', $universityId)
            ->where('created_at', '>=', $monthStart)
            ->count();
        $memberCount = User::query()->where('university_id', $universityId)->count();

        return view('institution.commercial-account', compact(
            'account', 'requests', 'pricingFeatures', 'currency', 'institutionAiSpendMinor', 'aiRequests', 'memberCount'
        ));
    }

    public function storeRequest(Request $request, NotificationService $notifications): RedirectResponse
    {
        abort_unless($request->user()->isUniversityAdmin(), 403);
        $universityId = (int) $request->user()->university_id;
        abort_if($universityId <= 0, 403);

        $account = CommercialAccount::query()->where('university_id', $universityId)->first();
        $features = array_keys((array) config('monetization.pricing_features', []));
        $data = $request->validate([
            'request_type' => ['required', Rule::in(['account_setup', 'add_funds', 'change_terms', 'sponsorship'])],
            'requested_amount' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],
            'max_administrators' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'student_semester_fee' => ['nullable', 'regex:/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/'],
            'invoice_grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'sponsor_ai_usage' => ['nullable', 'boolean'],
            'sponsored_features' => ['nullable', 'array'],
            'sponsored_features.*' => ['string', Rule::in($features)],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'request_type.required' => 'Choose what you would like the platform team to help with.',
            'requested_amount.regex' => 'Enter the requested funding amount using numbers only, with up to 2 decimal places.',
        ]);

        if ($data['request_type'] === 'account_setup' && $account) {
            return back()->withErrors(['request_type' => 'Your institution already has a commercial account. Choose a funding, terms, or sponsorship request instead.'])->withInput();
        }
        if ($data['request_type'] !== 'account_setup' && ! $account) {
            return back()->withErrors(['request_type' => 'Request commercial account setup first. Once approved, you can request funding or term changes.'])->withInput();
        }
        if ($data['request_type'] === 'add_funds') {
            $amountMinor = isset($data['requested_amount']) ? Money::toMinor((string) $data['requested_amount']) : 0;
            if ($amountMinor <= 0) {
                return back()->withErrors(['requested_amount' => 'Enter how much prepaid budget you want added.'])->withInput();
            }
        } else {
            $amountMinor = null;
        }

        $duplicate = CommercialAccountRequest::query()
            ->where('university_id', $universityId)
            ->where('request_type', $data['request_type'])
            ->whereIn('status', ['pending', 'in_review'])
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['request_type' => 'A request of this type is already awaiting review. You can follow its status below.'])->withInput();
        }

        $currency = strtoupper((string) ($account?->currency ?: SettingService::get('currency', 'NGN', $universityId)));
        $requestedChanges = match ($data['request_type']) {
            'change_terms' => array_filter([
                'max_administrators' => $data['max_administrators'] ?? null,
                'student_semester_fee_minor' => isset($data['student_semester_fee']) && $data['student_semester_fee'] !== '' ? Money::toMinor((string) $data['student_semester_fee']) : null,
                'invoice_grace_days' => $data['invoice_grace_days'] ?? null,
            ], static fn ($value): bool => $value !== null),
            'sponsorship' => [
                'sponsor_ai_usage' => $request->boolean('sponsor_ai_usage'),
                'sponsored_features' => array_values($data['sponsored_features'] ?? []),
            ],
            default => [],
        };

        $commercialRequest = DB::transaction(function () use ($request, $account, $universityId, $data, $amountMinor, $currency, $requestedChanges): CommercialAccountRequest {
            return CommercialAccountRequest::query()->create([
                'university_id' => $universityId,
                'commercial_account_id' => $account?->id,
                'requested_by_user_id' => $request->user()->id,
                'request_type' => $data['request_type'],
                'requested_amount_minor' => $amountMinor,
                'currency' => $currency,
                'requested_changes' => $requestedChanges,
                'message' => trim((string) ($data['message'] ?? '')) ?: null,
                'status' => 'pending',
            ]);
        }, 3);

        User::query()->where('role', 'super_admin')->get()->each(function (User $admin) use ($notifications, $commercialRequest, $request): void {
            $notifications->send(
                $admin,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                'Institution commercial request',
                ($request->user()->university?->name ?? 'An institution').' submitted a '.$commercialRequest->label().'.',
                ['commercial_account_request_id' => $commercialRequest->id, 'route' => 'admin.monetization']
            );
        });

        return back()->with('success', 'Request submitted. Platform Admin can now review it, and you can track the status here.');
    }
}
