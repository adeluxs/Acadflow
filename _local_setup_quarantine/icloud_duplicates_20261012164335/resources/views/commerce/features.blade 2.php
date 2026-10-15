@extends('layouts.app')
@section('title','Paid Feature Access')
@section('page-title','Paid Feature Access')
@section('page-subtitle','See what is free, sponsored, fixed-access, pay-per-use, or AI-billed before you use it')
@section('content')
@if(session('success'))
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif
@if(isset($errors) && $errors->any())
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Spending balance</p><p class="mt-2 text-3xl font-black">{{ $wallet->currency }} {{ \App\Support\Money::fromMinor((int)$wallet->spending_balance_minor) }}</p><a href="{{ route('commerce.wallet') }}" class="mt-3 inline-flex text-sm font-bold text-indigo-600">Fund wallet →</a></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Institution sponsorship</p><p class="mt-2 text-lg font-black">{{ $commercial ? 'Eligible & active' : ($institutionEligible ? 'No active commercial account' : 'Not eligible') }}</p><p class="mt-2 text-sm text-slate-500">Sponsored features/AI use the institution prepaid balance only for verified members of an eligible institution.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Billing safety</p><p class="mt-2 text-lg font-black">Reserve → complete → settle</p><p class="mt-2 text-sm text-slate-500">Pay-per-use and AI charges are reserved before execution and released if the protected operation fails.</p></div>
</div>

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Non-AI features</p><h2 class="mt-1 text-xl font-black">Feature pricing</h2></div>
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @foreach($featureRows as $row)
            <article class="rounded-2xl border p-5">
                <div class="flex items-start justify-between gap-4"><div><h3 class="font-black text-slate-900">{{ $row['title'] }}</h3><p class="mt-1 text-sm leading-6 text-slate-500">{{ $row['description'] }}</p></div>
                    @if(!$row['rule'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Free</span>
                    @elseif($row['sponsored'])<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Institution sponsored</span>
                    @elseif($row['pricing_type']==='fixed_access' && $row['entitlement'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Active</span>
                    @else<span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Paid</span>@endif
                </div>
                @if($row['rule'])
                    <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm">
                        <p class="font-bold">{{ str($row['pricing_type'])->replace('_',' ')->headline() }} · {{ $row['rule']->currency }} {{ \App\Support\Money::fromMinor((int)$row['rule']->unit_amount_minor) }}</p>
                        @if($row['pricing_type']==='fixed_access')<p class="mt-1 text-xs text-slate-500">Access duration: {{ (int)data_get($row['rule']->metadata,'duration_days',0) > 0 ? data_get($row['rule']->metadata,'duration_days').' days' : 'No expiry' }}</p>@else<p class="mt-1 text-xs text-slate-500">Charged per {{ data_get($row['rule']->metadata,'unit_label',$row['unit_label'] ?? 'use') }} after a successful operation.</p>@endif
                    </div>
                @endif
                @if($row['rule'] && $row['pricing_type']==='fixed_access' && !$row['sponsored'] && !$row['entitlement'])
                    <form method="POST" action="{{ route('commerce.features.purchase') }}" class="mt-4">@csrf<input type="hidden" name="feature" value="{{ $row['key'] }}"><input type="hidden" name="request_id" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white">Activate access</button></form>
                @elseif($row['entitlement'])
                    <p class="mt-3 text-xs font-semibold text-emerald-700">Active{{ $row['entitlement']->expires_at ? ' until '.$row['entitlement']->expires_at->format('d M Y, H:i') : ' with no expiry' }}.</p>
                @endif
            </article>
        @endforeach
    </div>
</section>

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-violet-600">AI usage</p><h2 class="mt-1 text-xl font-black">Assistant pricing</h2><p class="mt-1 text-sm text-slate-500">Assistant-specific pricing overrides the global AI price. If no override exists, the global free allowance and request charge apply.</p></div>
    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach($aiRows as $row)
            <div class="rounded-2xl border p-4"><p class="font-black">{{ $row['title'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['category'] }}</p>
                @if(!$row['pricing']['enabled'])<p class="mt-3 text-sm font-bold text-emerald-700">AI monetization disabled</p>
                @else<p class="mt-3 text-sm font-bold">{{ $row['pricing']['currency'] }} {{ \App\Support\Money::fromMinor((int)$row['pricing']['amount_minor']) }} / successful request</p><p class="mt-1 text-xs text-slate-500">{{ $row['pricing']['free_daily_requests'] }} free successful request(s) per day{{ $row['pricing']['feature_specific'] ? ' for this assistant' : ' across global AI pricing' }}.</p>@endif
            </div>
        @endforeach
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="rounded-3xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">Recent paid-feature usage</h2><div class="mt-3 divide-y">@forelse($featureUsage as $usage)<div class="py-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold">{{ data_get(config('monetization.pricing_features.'.$usage->feature,[]),'title',str($usage->feature)->headline()) }}</span><span class="font-black">{{ $usage->currency }} {{ \App\Support\Money::fromMinor((int)$usage->total_amount_minor) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ str($usage->pricing_type)->replace('_',' ')->headline() }} · {{ str($usage->funding_source)->replace('_',' ')->headline() }} · {{ str($usage->status)->headline() }} · {{ $usage->created_at?->format('d M Y, H:i') }}</p></div>@empty<p class="py-5 text-sm text-slate-500">No paid-feature usage yet.</p>@endforelse</div></section>
    <section class="rounded-3xl border bg-white p-6 shadow-sm"><h2 class="text-lg font-black">Recent AI billing</h2><div class="mt-3 divide-y">@forelse($aiUsage as $usage)<div class="py-3 text-sm"><div class="flex justify-between gap-3"><span class="font-semibold">{{ data_get(config('monetization.ai_features.'.$usage->feature,[]),'title',str($usage->feature)->headline()) }}</span><span class="font-black">{{ $usage->currency }} {{ \App\Support\Money::fromMinor((int)$usage->user_charge_minor) }}</span></div><p class="mt-1 text-xs text-slate-500">{{ $usage->provider ?: 'No provider charge' }} · {{ str(data_get($usage->metadata,'funding_source','platform'))->replace('_',' ')->headline() }} · {{ str($usage->status)->headline() }} · {{ $usage->created_at?->format('d M Y, H:i') }}</p></div>@empty<p class="py-5 text-sm text-slate-500">No AI billing records yet.</p>@endforelse</div></section>
</div>
@endsection
