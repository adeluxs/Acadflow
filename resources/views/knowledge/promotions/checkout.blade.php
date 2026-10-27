@extends('layouts.app')
@section('title', 'Pay for promotion')
@section('page-title', 'Promotion checkout')
@section('page-subtitle', 'Review the campaign before payment')
@section('content')
<div class="mx-auto max-w-2xl space-y-6">
    @if(session('success'))<div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
    <section class="rounded-3xl border bg-white p-7 shadow-sm">
        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase text-amber-800">Sponsored campaign</span>
        <h1 class="mt-4 text-2xl font-black">{{ $campaign->publication?->title }}</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $campaign->package?->name }} · {{ $campaign->package?->duration_days }} days after verified payment</p>
        <p class="mt-5 text-3xl font-black">{{ config('app.currency_symbol', '₦') }}{{ number_format((float)$campaign->price, 2) }}</p>
        <form method="POST" action="{{ route('commerce.purchase-promotion', $campaign) }}" class="mt-6 grid gap-4">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <label class="text-sm font-bold">Payment method<select name="payment_method" class="mt-2 w-full rounded-xl border-slate-300"><option value="wallet">Wallet balance</option><option value="gateway">Payment gateway</option></select></label>
            <label class="text-sm font-bold">Gateway<select name="gateway" class="mt-2 w-full rounded-xl border-slate-300"><option value="">Select for gateway payment</option>@foreach($checkoutGateways as $gateway)<option value="{{ $gateway->code }}">{{ $gateway->name }}</option>@endforeach</select></label>
            <button class="rounded-xl bg-indigo-700 px-5 py-3 font-black text-white">Pay and activate</button>
        </form>
        <p class="mt-4 text-xs leading-5 text-slate-500">Payment buys labelled placement and campaign reporting. It does not influence moderation, search credibility, peer-review status or academic labels.</p>
    </section>
</div>
@endsection
