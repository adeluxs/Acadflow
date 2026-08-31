@extends('layouts.app')
@section('title','Monetization')
@section('content')
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600">Commercial control center</p><h1 class="text-3xl font-black text-slate-900">Monetization</h1><p class="mt-1 text-sm text-slate-500">Pay-as-you-use, wallet, marketplace earnings, AI billing and institutional commercial access in one place.</p></div></div>
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([['Paid orders',$metrics['paid_orders']],['Gross volume',$displayCurrency.' '.number_format($metrics['gross_minor']/100,2)],['Platform revenue',$displayCurrency.' '.number_format($metrics['platform_revenue_minor']/100,2)],['Pending withdrawals',$metrics['pending_withdrawals']],['AI revenue',$displayCurrency.' '.number_format($metrics['ai_revenue_minor']/100,2)],['AI margin',$displayCurrency.' '.number_format($metrics['ai_margin_minor']/100,2)]] as [$label,$value])
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">{{ $label }}</p><p class="mt-2 text-xl font-black text-slate-900">{{ $value }}</p></div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.monetization.update') }}" class="rounded-3xl border bg-white p-6 shadow-sm">@csrf @method('PUT')
        <h2 class="text-xl font-black">Runtime pricing & wallet controls</h2><p class="mt-1 text-sm text-slate-500">These values are read directly by the domain services; they are not decorative settings.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm font-semibold">Minimum wallet funding ({{ $displayCurrency }})<input name="wallet_minimum_funding_amount" value="{{ old('wallet_minimum_funding_amount',$settings['wallet_minimum_funding_amount']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Minimum withdrawal ({{ $displayCurrency }})<input name="minimum_withdrawal_amount" value="{{ old('minimum_withdrawal_amount',$settings['minimum_withdrawal_amount']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Withdrawal fee (%)<input name="withdrawal_fee_percentage" value="{{ old('withdrawal_fee_percentage',$settings['withdrawal_fee_percentage']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Creator hold (days)<input type="number" name="creator_earnings_hold_days" value="{{ old('creator_earnings_hold_days',$settings['creator_earnings_hold_days']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Platform marketplace commission (%)<input name="knowledge_platform_commission_percentage" value="{{ old('knowledge_platform_commission_percentage',$settings['knowledge_platform_commission_percentage']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Institution revenue share (%)<input name="knowledge_institution_revenue_percentage" value="{{ old('knowledge_institution_revenue_percentage',$settings['knowledge_institution_revenue_percentage']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Free AI requests/day<input type="number" name="ai_free_daily_requests" value="{{ old('ai_free_daily_requests',$settings['ai_free_daily_requests']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">AI charge/request ({{ $displayCurrency }})<input name="ai_request_charge_amount" value="{{ old('ai_request_charge_amount',$settings['ai_request_charge_amount']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">{{ $displayCurrency }}/USD reporting rate<input name="ai_local_currency_per_usd_reporting" value="{{ old('ai_local_currency_per_usd_reporting',$settings['ai_local_currency_per_usd_reporting']) }}" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="flex items-center gap-3 rounded-xl border p-3 text-sm font-semibold"><input type="checkbox" name="ai_monetization_enabled" value="1" @checked(old('ai_monetization_enabled',$settings['ai_monetization_enabled']))>Charge for provider AI after free allowance</label>
        </div><button class="mt-5 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Save monetization settings</button>
    </form>

    @if(auth()->user()->isSuperAdmin())
    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.monetization.pricing-rules.store') }}" class="rounded-3xl border bg-white p-6 shadow-sm">@csrf<h2 class="text-lg font-black">Pricing rule</h2><div class="mt-4 grid gap-3"><input name="key" placeholder="feature.export_advanced" class="rounded-xl border-slate-300" required><input name="name" placeholder="Advanced export" class="rounded-xl border-slate-300" required><div class="grid grid-cols-2 gap-3"><input name="amount" placeholder="Amount in selected currency" class="rounded-xl border-slate-300"><input name="percentage" placeholder="Percentage" class="rounded-xl border-slate-300"></div><input name="currency" value="{{ $displayCurrency }}" class="rounded-xl border-slate-300"><label class="flex gap-2"><input type="checkbox" name="enabled" value="1" checked>Enabled</label><button class="rounded-xl bg-slate-900 px-4 py-2 text-white">Save pricing rule</button></div></form>
        <form method="POST" action="{{ route('admin.monetization.commercial-accounts.store') }}" class="rounded-3xl border bg-white p-6 shadow-sm">@csrf<h2 class="text-lg font-black">Institution commercial account</h2><div class="mt-4 grid gap-3"><select name="university_id" class="rounded-xl border-slate-300" required>@foreach($universities as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select><input name="name" placeholder="Commercial account name" class="rounded-xl border-slate-300" required><input name="prepaid_balance" value="0.00" class="rounded-xl border-slate-300" required><input type="number" name="max_administrators" placeholder="Maximum administrators" class="rounded-xl border-slate-300"><input name="student_semester_fee" placeholder="Optional student semester fee (institution currency)" class="rounded-xl border-slate-300"><input type="number" min="0" max="90" name="invoice_grace_days" value="7" placeholder="Invoice grace days" class="rounded-xl border-slate-300"><label class="flex gap-2"><input type="checkbox" name="sponsor_ai_usage" value="1">Sponsor members' AI usage</label><button class="rounded-xl bg-slate-900 px-4 py-2 text-white">Save commercial account</button></div></form>
    </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Pending withdrawals</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $pendingWithdrawals->count() }}</span></div>
            <div class="mt-4 space-y-4">@forelse($pendingWithdrawals as $withdrawal)<div class="rounded-2xl border p-4 text-sm"><p class="font-black">{{ $withdrawal->wallet?->user?->full_name }} · {{ $withdrawal->wallet?->currency }} {{ \App\Support\Money::fromMinor((int)$withdrawal->amount_minor) }}</p><p class="mt-1 text-xs text-slate-500">{{ $withdrawal->payoutAccount?->bank_name }} · {{ $withdrawal->payoutAccount?->masked_account_number }} · fee {{ \App\Support\Money::fromMinor((int)$withdrawal->fee_minor) }}</p><form method="POST" action="{{ route('commerce.withdrawals.process',$withdrawal) }}" class="mt-3 grid gap-2">@csrf<input name="provider_reference" class="rounded-lg border-slate-300 text-xs" placeholder="Provider/bank reference for approval"><input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Decision note"><div class="flex gap-2"><button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve paid</button><button name="decision" value="reject" class="rounded-lg border px-3 py-2 text-xs font-bold">Reject & release hold</button></div></form></div>@empty<p class="text-sm text-slate-500">No withdrawal requests awaiting review.</p>@endforelse</div>
        </section>
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Refund operations</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $pendingRefunds->count() }}</span></div>
            <div class="mt-4 space-y-4">
                @forelse($pendingRefunds as $refund)
                    <div class="rounded-2xl border p-4 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black">{{ $refund->order?->order_number }} · {{ $refund->order?->currency }} {{ \App\Support\Money::fromMinor((int)$refund->amount_minor) }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $refund->requester?->full_name }}</p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[11px] font-bold {{ $refund->reconciliation_required ? 'bg-red-50 text-red-700' : ($refund->status === 'processing' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700') }}">
                                {{ $refund->reconciliation_required ? 'Reconciliation required' : ucfirst($refund->status) }}
                            </span>
                        </div>
                        <p class="mt-2 text-slate-600">{{ $refund->reason }}</p>

                        @if($refund->reconciliation_required)
                            <div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800">
                                The provider outcome was uncertain. Verify this refund in the payment-provider dashboard before choosing an outcome. AcadFlow will not send a second provider refund automatically.
                            </div>
                            <form method="POST" action="{{ route('commerce.refunds.reconcile',$refund) }}" class="mt-3 grid gap-2">@csrf
                                <input name="provider_reference" class="rounded-lg border-slate-300 text-xs" placeholder="Provider refund reference (required if confirmed)">
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Required reconciliation evidence/note" required>
                                <div class="flex flex-wrap gap-2">
                                    <button name="outcome" value="confirmed" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Confirmed at provider</button>
                                    <button name="outcome" value="not_refunded" class="rounded-lg border px-3 py-2 text-xs font-bold">Provider shows no refund</button>
                                </div>
                            </form>
                        @elseif($refund->status === 'processing')
                            <form method="POST" action="{{ route('commerce.refunds.process',$refund) }}" class="mt-3 grid gap-2">@csrf
                                <input type="hidden" name="decision" value="approve">
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Optional finalization note">
                                <button class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white">Resume safe finalization</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('commerce.refunds.process',$refund) }}" class="mt-3 grid gap-2">@csrf
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Decision note">
                                <div class="flex gap-2"><button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve</button><button name="decision" value="reject" class="rounded-lg border px-3 py-2 text-xs font-bold">Reject</button></div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No refund requests awaiting action.</p>
                @endforelse
            </div>
        </section>
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Payout verification</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $pendingPayoutAccounts->count() }}</span></div>
            <div class="mt-4 space-y-4">@forelse($pendingPayoutAccounts as $account)<div class="rounded-2xl border p-4 text-sm"><p class="font-black">{{ $account->user?->full_name }}</p><p class="mt-1 text-slate-600">{{ $account->account_name }} · {{ $account->bank_name }} · {{ $account->masked_account_number }}</p><form method="POST" action="{{ route('commerce.payout-accounts.verify',$account) }}" class="mt-3 flex gap-2">@csrf<button name="verified" value="1" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Mark verified</button><button name="verified" value="0" class="rounded-lg border px-3 py-2 text-xs font-bold">Keep unverified</button></form></div>@empty<p class="text-sm text-slate-500">No payout accounts awaiting verification.</p>@endforelse</div>
        </section>
    </div>

    <section class="rounded-3xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">Current pricing rules</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-slate-500"><th class="p-2">Key</th><th class="p-2">Amount</th><th class="p-2">Percent</th><th class="p-2">Status</th></tr></thead><tbody>@forelse($pricingRules as $r)<tr class="border-b"><td class="p-2 font-mono text-xs">{{ $r->key }}</td><td class="p-2">{{ $r->unit_amount_minor===null?'—':$r->currency.' '.number_format($r->unit_amount_minor/100,2) }}</td><td class="p-2">{{ $r->percentage_basis_points===null?'—':number_format($r->percentage_basis_points/100,2).'%' }}</td><td class="p-2">{{ $r->enabled?'Enabled':'Disabled' }}</td></tr>@empty<tr><td colspan="4" class="p-4 text-slate-500">No paid feature rules yet. Features remain free until a rule is enabled.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
