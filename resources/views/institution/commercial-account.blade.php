@extends('layouts.app')

@section('title', 'Commercial Account')
@section('page-title', 'Commercial Account')
@section('page-subtitle', 'View your institution’s commercial access and request changes from the platform team')

@section('content')
<div class="mx-auto max-w-7xl px-4 pt-4"><p class="font-semibold text-indigo-700">{{ $organisationName }} · {{ $departmentId?'Department':'Institution' }} commercial account</p><p class="mt-2 text-sm text-slate-500">The account funds only eligible approved memberships. Affiliation preserves departmental balances and personal paid access.</p></div>
@php
    $metadata = (array) ($account?->metadata ?? []);
    $sponsoredFeatureKeys = (array) data_get($metadata, 'sponsored_features', []);
    $statusStyles = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'in_review' => 'bg-blue-50 text-blue-700 border-blue-200',
        'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'rejected' => 'bg-red-50 text-red-700 border-red-200',
    ];
@endphp

<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">Institution commercial access</p>
            <h1 class="mt-1 text-3xl font-black text-slate-900">{{ auth()->user()->university?->name ?? 'Your institution' }}</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">See what the institution is sponsoring, how much prepaid budget remains, and request changes without needing access to platform financial controls.</p>
        </div>
        <div class="flex items-center gap-2">
            @if($account)
                <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-black {{ $account->status === 'active' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-700' }}">
                    <span class="h-2 w-2 rounded-full {{ $account->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    {{ $account->status === 'active' ? 'Commercial account active' : ucfirst($account->status) }}
                </span>
            @else
                <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-700">Setup not completed</span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <p class="font-black">Please check the request:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(!$account)
        <section class="overflow-hidden rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-white shadow-sm">
            <div class="grid gap-6 p-6 lg:grid-cols-[1.4fr_.9fr] lg:p-8">
                <div>
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-black text-white">1</div>
                    <h2 class="mt-4 text-2xl font-black text-slate-900">Request commercial account setup</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">This organisation does not have a commercial account yet. Send a setup request to Platform Admin. No payment or financial commitment is created just by sending the request.</p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border bg-white p-4"><p class="text-xs font-black uppercase text-slate-400">Step 1</p><p class="mt-1 text-sm font-bold text-slate-800">Request setup</p></div>
                        <div class="rounded-2xl border bg-white p-4"><p class="text-xs font-black uppercase text-slate-400">Step 2</p><p class="mt-1 text-sm font-bold text-slate-800">Platform reviews terms</p></div>
                        <div class="rounded-2xl border bg-white p-4"><p class="text-xs font-black uppercase text-slate-400">Step 3</p><p class="mt-1 text-sm font-bold text-slate-800">Account is activated</p></div>
                    </div>
                </div>
                <form method="POST" action="{{ route('institution.commercial-account.requests.store') }}" class="rounded-2xl border bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="request_type" value="account_setup">
                    <label class="block text-sm font-bold text-slate-800">Anything the platform team should know?
                        <textarea name="message" rows="5" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Example: We want to sponsor AI access for lecturers and support approximately 2,000 students.">{{ old('message') }}</textarea>
                    </label>
                    <p class="mt-3 text-xs leading-5 text-slate-500">You can discuss funding, limits and sponsored features after the platform team opens the commercial account.</p>
                    <button class="mt-4 w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white transition hover:bg-indigo-700">Send setup request</button>
                </form>
            </div>
        </section>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Available prepaid budget</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ $account->currency }} {{ \App\Support\Money::fromMinor((int) $account->prepaid_balance_minor) }}</p>
                <p class="mt-1 text-xs text-slate-500">Used for institution-sponsored variable usage such as AI where enabled.</p>
            </div>
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Approved members</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($memberCount) }}</p>
                <p class="mt-1 text-xs text-slate-500">Students, lecturers and institution administrators currently linked.</p>
            </div>
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">AI requests this month</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ number_format($aiRequests) }}</p>
                <p class="mt-1 text-xs text-slate-500">Recorded against this commercial account.</p>
            </div>
            <div class="rounded-2xl border bg-white p-5 shadow-sm">
                <p class="text-xs font-black uppercase tracking-wide text-slate-400">Institution-funded AI this month</p>
                <p class="mt-2 text-2xl font-black text-slate-900">{{ $currency }} {{ \App\Support\Money::fromMinor($institutionAiSpendMinor) }}</p>
                <p class="mt-1 text-xs text-slate-500">Only AI charges funded from the institution’s commercial balance.</p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
            <section class="rounded-3xl border bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">What your institution receives</p>
                        <h2 class="mt-1 text-xl font-black text-slate-900">Commercial terms at a glance</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">{{ $account->currency }}</span>
                </div>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border p-4">
                        <p class="text-xs font-black uppercase text-slate-400">AI sponsorship</p>
                        <p class="mt-2 font-black {{ data_get($metadata,'sponsor_ai_usage',false) ? 'text-emerald-700' : 'text-slate-700' }}">{{ data_get($metadata,'sponsor_ai_usage',false) ? 'Institution pays for eligible AI usage' : 'Members use their own AI allowance/wallet' }}</p>
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="text-xs font-black uppercase text-slate-400">Department admin limit</p>
                        <p class="mt-2 font-black text-slate-900">{{ data_get($metadata,'max_administrators') ? number_format((int) data_get($metadata,'max_administrators')).' per department' : 'No special commercial limit set' }}</p>
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="text-xs font-black uppercase text-slate-400">Student semester fee</p>
                        <p class="mt-2 font-black text-slate-900">{{ data_get($metadata,'student_semester_fee_minor') !== null ? $account->currency.' '.\App\Support\Money::fromMinor((int) data_get($metadata,'student_semester_fee_minor')) : 'Not configured' }}</p>
                    </div>
                    <div class="rounded-2xl border p-4">
                        <p class="text-xs font-black uppercase text-slate-400">Invoice grace period</p>
                        <p class="mt-2 font-black text-slate-900">{{ (int) data_get($metadata,'invoice_grace_days',7) }} days</p>
                    </div>
                </div>

                <div class="mt-6">
                    <h3 class="text-sm font-black text-slate-900">Sponsored paid features</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Members receive these entitlement-controlled features through the institution instead of purchasing individual access.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse($sponsoredFeatureKeys as $featureKey)
                            <span class="rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700">{{ data_get($pricingFeatures, $featureKey.'.title', str($featureKey)->replace('_',' ')->title()) }}</span>
                        @empty
                            <span class="rounded-full border bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-500">No paid features are currently institution-sponsored.</span>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border bg-white p-6 shadow-sm">
                <p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Need a change?</p>
                <h2 class="mt-1 text-xl font-black text-slate-900">Send a commercial request</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">You request it; Platform Admin reviews and applies approved financial changes. Your current account stays unchanged until then.</p>

                <form method="POST" action="{{ route('institution.commercial-account.requests.store') }}" class="mt-5 space-y-4" id="commercial-request-form">
                    @csrf
                    <label class="block text-sm font-bold text-slate-800">What do you need?
                        <select name="request_type" id="commercial-request-type" class="mt-2 w-full rounded-xl border-slate-300 text-sm" required>
                            <option value="">Choose a request</option>
                            <option value="add_funds" @selected(old('request_type')==='add_funds')>Add prepaid budget</option>
                            <option value="change_terms" @selected(old('request_type')==='change_terms')>Change limits or billing terms</option>
                            <option value="sponsorship" @selected(old('request_type')==='sponsorship')>Change sponsored AI/features</option>
                        </select>
                    </label>

                    <div id="commercial-request-funding" class="hidden rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                        <label class="block text-sm font-bold text-slate-800">How much additional prepaid budget do you want? ({{ $currency }})
                            <input name="requested_amount" value="{{ old('requested_amount') }}" inputmode="decimal" class="mt-2 w-full rounded-xl border-slate-300 bg-white" placeholder="Example: 250000.00">
                        </label>
                        <p class="mt-2 text-xs text-slate-500">Submitting this request does not credit the balance automatically. Platform Admin must verify the commercial arrangement/funding first.</p>
                    </div>

                    <div id="commercial-request-terms" class="hidden space-y-3 rounded-2xl border bg-slate-50 p-4">
                        <label class="block text-xs font-bold text-slate-700">Requested maximum institution administrators
                            <input type="number" min="1" max="10000" name="max_administrators" value="{{ old('max_administrators') }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white" placeholder="Leave blank if unchanged">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Requested student semester fee ({{ $currency }})
                            <input name="student_semester_fee" value="{{ old('student_semester_fee') }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white" placeholder="Leave blank if unchanged">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Requested invoice grace period
                            <input type="number" min="0" max="90" name="invoice_grace_days" value="{{ old('invoice_grace_days') }}" class="mt-1 w-full rounded-xl border-slate-300 bg-white" placeholder="Days; leave blank if unchanged">
                        </label>
                    </div>

                    <div id="commercial-request-sponsorship" class="hidden space-y-4 rounded-2xl border bg-slate-50 p-4">
                        <label class="flex items-start gap-3 rounded-xl border bg-white p-3">
                            <input type="checkbox" name="sponsor_ai_usage" value="1" class="mt-1 rounded border-slate-300 text-indigo-600" @checked(old('sponsor_ai_usage'))>
                            <span><span class="block text-sm font-bold text-slate-800">Request institution-sponsored AI</span><span class="mt-1 block text-xs text-slate-500">Eligible AI usage will draw from the institution’s approved prepaid commercial budget.</span></span>
                        </label>
                        <div>
                            <p class="text-sm font-bold text-slate-800">Paid features you want the institution to sponsor</p>
                            <div class="mt-2 space-y-2">
                                @foreach($pricingFeatures as $featureKey => $feature)
                                    <label class="flex items-start gap-3 rounded-xl border bg-white p-3">
                                        <input type="checkbox" name="sponsored_features[]" value="{{ $featureKey }}" class="mt-1 rounded border-slate-300 text-indigo-600" @checked(in_array($featureKey, old('sponsored_features', $sponsoredFeatureKeys), true))>
                                        <span><span class="block text-sm font-bold text-slate-800">{{ $feature['title'] }}</span><span class="mt-1 block text-xs leading-5 text-slate-500">{{ $feature['description'] }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <label class="block text-sm font-bold text-slate-800">Message to Platform Admin
                        <textarea name="message" rows="4" class="mt-2 w-full rounded-xl border-slate-300 text-sm" placeholder="Explain the reason, expected number of users, timing, or any commercial context.">{{ old('message') }}</textarea>
                    </label>
                    <button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white transition hover:bg-indigo-700">Submit request for review</button>
                </form>
            </section>
        </div>
    @endif

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Transparent follow-up</p><h2 class="mt-1 text-xl font-black text-slate-900">Commercial request history</h2><p class="mt-1 text-sm text-slate-500">Track what you requested and whether Platform Admin has reviewed it.</p></div>
        </div>
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-xs uppercase tracking-wide text-slate-400"><th class="p-3">Request</th><th class="p-3">Details</th><th class="p-3">Status</th><th class="p-3">Submitted</th><th class="p-3">Review</th></tr></thead>
                <tbody>
                    @forelse($requests as $commercialRequest)
                        <tr class="border-b align-top last:border-0">
                            <td class="p-3"><p class="font-black text-slate-900">{{ $commercialRequest->label() }}</p><p class="mt-1 font-mono text-[11px] text-slate-400">{{ $commercialRequest->uuid }}</p></td>
                            <td class="p-3 text-slate-600">
                                @if($commercialRequest->requested_amount_minor)<p class="font-bold text-slate-900">{{ $commercialRequest->currency }} {{ \App\Support\Money::fromMinor((int)$commercialRequest->requested_amount_minor) }}</p>@endif
                                <p class="max-w-md">{{ $commercialRequest->message ?: 'No additional message.' }}</p>
                            </td>
                            <td class="p-3"><span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-black {{ $statusStyles[$commercialRequest->status] ?? 'border-slate-200 bg-slate-50 text-slate-600' }}">{{ str($commercialRequest->status)->replace('_',' ')->title() }}</span></td>
                            <td class="p-3 text-slate-500">{{ $commercialRequest->created_at?->format('d M Y, H:i') }}</td>
                            <td class="p-3 text-slate-500">@if($commercialRequest->reviewed_at)<p>{{ $commercialRequest->reviewed_at->format('d M Y, H:i') }}</p><p class="mt-1 text-xs">{{ $commercialRequest->review_note ?: 'Reviewed by Platform Admin.' }}</p>@else<span class="text-slate-400">Awaiting review</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-5 text-slate-500">No commercial requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const type = document.getElementById('commercial-request-type');
        if (!type) return;
        const groups = {
            add_funds: document.getElementById('commercial-request-funding'),
            change_terms: document.getElementById('commercial-request-terms'),
            sponsorship: document.getElementById('commercial-request-sponsorship'),
        };
        const refresh = () => Object.entries(groups).forEach(([key, element]) => element?.classList.toggle('hidden', type.value !== key));
        type.addEventListener('change', refresh);
        refresh();
    });
</script>
@endpush
@endsection
