@extends('layouts.app')
@section('title','Wallet & Earnings')
@section('page-title','Wallet & Earnings')
@section('page-subtitle','Fund small purchases once, track creator earnings separately, and withdraw verified earnings securely')
@section('content')
@php
    $fmt = fn (int $minor) => \App\Support\Money::fromMinor($minor);
    $verifiedAccounts = $accounts->where('is_verified', true)->where('currency', $wallet->currency);
@endphp

@if((int) $wallet->recovery_debt_minor > 0)
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
        <p class="font-black">Earnings recovery in progress</p>
        <p class="mt-1 text-sm leading-6">{{ $wallet->currency }} {{ $fmt((int) $wallet->recovery_debt_minor) }} from a completed refund/reversal is still recoverable. New creator earnings will automatically reduce this amount before becoming withdrawable. Your spending balance is not affected.</p>
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Spending balance</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $wallet->currency }} {{ $fmt((int)$wallet->spending_balance_minor) }}</p><p class="mt-2 text-xs text-slate-500">For AI, marketplace and other pay-as-you-use services.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending earnings</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $wallet->currency }} {{ $fmt((int)$wallet->pending_earnings_minor) }}</p><p class="mt-2 text-xs text-slate-500">Creator revenue still inside the settlement hold.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Available earnings</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $wallet->currency }} {{ $fmt((int)$wallet->available_earnings_minor) }}</p><p class="mt-2 text-xs text-slate-500">Verified earnings available for withdrawal.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Lifetime earnings</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $wallet->currency }} {{ $fmt((int)$wallet->lifetime_earnings_minor) }}</p><p class="mt-2 text-xs text-slate-500">Gross creator earnings recorded by AcadFlow.</p></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('commerce.wallet.fund') }}" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div><h2 class="text-lg font-black">Add funds</h2><p class="mt-1 text-sm text-slate-500">Top up once, then use your spending balance for lower-value internal transactions.</p></div>
        <label class="text-sm font-semibold">Amount ({{ $wallet->currency }})<input required type="number" inputmode="decimal" min="{{ $walletRules['minimum_funding'] }}" step="0.01" name="amount" value="{{ old('amount') }}" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Minimum {{ $walletRules['minimum_funding'] }}"></label>
        <label class="text-sm font-semibold">Payment method<select required name="gateway" class="mt-1 w-full rounded-xl border-slate-300">@forelse($gateways as $gateway)<option value="{{ $gateway->code }}">{{ $gateway->name }}</option>@empty<option disabled>No payment gateway is currently available</option>@endforelse</select></label>
        <button @disabled($gateways->isEmpty()) class="rounded-xl bg-slate-950 px-4 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Continue securely</button>
        <p class="text-xs text-slate-500">Your balance is credited only after AcadFlow verifies the payment amount and currency directly with the gateway.</p>
    </form>

    <form method="POST" action="{{ route('commerce.payout-accounts.store') }}" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        @csrf
        <div><h2 class="text-lg font-black">Payout account</h2><p class="mt-1 text-sm text-slate-500">Withdrawal accounts must be verified before they can receive earnings.</p></div>
        <select name="provider" class="rounded-xl border-slate-300"><option value="bank">Bank</option><option value="paystack">Paystack transfer</option><option value="manual">Manual settlement</option></select>
        <input required name="account_name" class="rounded-xl border-slate-300" placeholder="Account name">
        <input required name="account_number" class="rounded-xl border-slate-300" placeholder="Account number">
        <div class="grid gap-3 sm:grid-cols-2"><input name="bank_name" class="rounded-xl border-slate-300" placeholder="Bank name"><input name="bank_code" class="rounded-xl border-slate-300" placeholder="Bank code"></div>
        <input required name="currency" value="{{ $wallet->currency }}" class="rounded-xl border-slate-300">
        <button class="rounded-xl border border-slate-300 px-4 py-2.5 font-bold">Save account</button>
    </form>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('commerce.withdrawals.store') }}" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div><h2 class="text-lg font-black">Withdraw earnings</h2><p class="mt-1 text-sm text-slate-500">Minimum {{ $wallet->currency }} {{ $walletRules['minimum_withdrawal'] }} · fee {{ $walletRules['withdrawal_fee'] }}%.</p></div>
        <select required name="payout_account_id" class="rounded-xl border-slate-300">@forelse($verifiedAccounts as $account)<option value="{{ $account->id }}">{{ $account->bank_name ?: str($account->provider)->headline() }} · {{ $account->masked_account_number }} · {{ $account->currency }}</option>@empty<option disabled>No verified payout account yet</option>@endforelse</select>
        <input required type="number" inputmode="decimal" min="{{ $walletRules['minimum_withdrawal'] }}" step="0.01" name="amount" class="rounded-xl border-slate-300" placeholder="Withdrawal amount">
        <button @disabled($verifiedAccounts->isEmpty()) class="rounded-xl bg-blue-600 px-4 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Request withdrawal</button>
        <p class="text-xs text-slate-500">The requested amount is reserved immediately so it cannot be withdrawn twice. The final amount sent equals your requested amount minus the configured withdrawal fee; all calculations are performed server-side in minor currency units.</p>
    </form>

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black">Payout accounts</h2>
        <div class="mt-3 space-y-3">@forelse($accounts as $account)<div class="flex items-center justify-between rounded-xl border p-3 text-sm"><div><p class="font-bold">{{ $account->bank_name ?: str($account->provider)->headline() }}</p><p class="text-slate-500">{{ $account->masked_account_number }} · {{ $account->currency }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $account->is_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $account->is_verified ? 'Verified' : 'Verification pending' }}</span></div>@empty<p class="text-sm text-slate-500">No payout account saved yet.</p>@endforelse</div>
    </div>
</div>

<div class="mt-6 rounded-2xl border bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black">Wallet activity</h2>
    <div class="mt-3 divide-y">@forelse($wallet->entries as $entry)<div class="flex flex-col justify-between gap-1 py-3 text-sm sm:flex-row sm:items-center"><div><p class="font-semibold text-slate-800">{{ $entry->description ?: str($entry->entry_type)->replace('_',' ')->headline() }}</p><p class="text-xs text-slate-500">{{ str($entry->balance_bucket ?: 'wallet')->replace('_',' ')->headline() }} · {{ $entry->posted_at?->format('d M Y, H:i') }}</p></div><span class="font-black {{ $entry->direction==='credit' ? 'text-emerald-700' : 'text-slate-900' }}">{{ $entry->direction==='credit'?'+':'-' }}{{ $wallet->currency }} {{ $fmt((int)($entry->amount_minor ?? \App\Support\Money::toMinor((string)$entry->amount))) }}</span></div>@empty<p class="py-6 text-sm text-slate-500">No wallet activity yet.</p>@endforelse</div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="text-lg font-black">Creator revenue</h2><div class="mt-3 divide-y">@forelse($allocations as $allocation)<div class="flex justify-between gap-3 py-3 text-sm"><span>{{ str($allocation->allocation_type)->headline() }} · {{ str($allocation->status)->replace('_',' ')->headline() }}</span><span class="font-bold">{{ $wallet->currency }} {{ $fmt((int)($allocation->amount_minor ?? \App\Support\Money::toMinor((string)$allocation->amount))) }}</span></div>@empty<p class="py-6 text-sm text-slate-500">No creator revenue yet.</p>@endforelse</div>{{ $allocations->links() }}</div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="text-lg font-black">Withdrawal history</h2><div class="mt-3 divide-y">@forelse($wallet->withdrawals->sortByDesc('created_at') as $withdrawal)<div class="flex justify-between gap-3 py-3 text-sm"><div><p class="font-semibold">{{ $wallet->currency }} {{ $fmt((int)($withdrawal->amount_minor ?? \App\Support\Money::toMinor((string)$withdrawal->amount))) }}</p><p class="text-xs text-slate-500">{{ $withdrawal->created_at?->format('d M Y, H:i') }}</p></div><span class="font-bold">{{ str($withdrawal->status)->headline() }}</span></div>@empty<p class="py-6 text-sm text-slate-500">No withdrawal requests yet.</p>@endforelse</div></div>
</div>
@endsection
