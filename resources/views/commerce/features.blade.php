@extends('layouts.app')
@section('title','Plans & Usage')
@section('page-title','Plans & Usage')
@section('page-subtitle','Pay for your workspace tools, receive included AI requests, and control any extra AI spending')
@section('content')
@if(session('success'))
    <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
@endif
@if(isset($errors) && $errors->any())
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Spending balance</p><p class="mt-2 text-3xl font-black">{{ $wallet->currency }} {{ \App\Support\Money::fromMinor((int)$wallet->spending_balance_minor) }}</p><a href="{{ route('commerce.wallet') }}" class="mt-3 inline-flex text-sm font-bold text-indigo-600">Fund wallet →</a></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Institution sponsorship</p><p class="mt-2 text-lg font-black">{{ $commercial ? 'Eligible & active' : ($institutionEligible ? 'No active commercial account' : 'Not eligible') }}</p><p class="mt-2 text-sm text-slate-500">Sponsored features/AI use the institution prepaid balance only for verified members of an eligible institution.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Billing safety</p><p class="mt-2 text-lg font-black">Reserve → complete → settle</p><p class="mt-2 text-sm text-slate-500">Pay-per-use and AI charges are reserved before execution and released if the protected operation fails.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">AI spend this month</p><p class="mt-2 text-2xl font-black">{{ $wallet->currency }} {{ \App\Support\Money::fromMinor($aiMonthSpendMinor) }}</p><form method="POST" action="{{ route('commerce.features.ai-spending-limit') }}" class="mt-3">@csrf @method('PUT')<label class="text-xs font-semibold text-slate-600">Monthly limit (0 = no limit)<div class="mt-1 flex gap-2"><input name="ai_monthly_spending_limit" inputmode="decimal" value="{{ old('ai_monthly_spending_limit', $wallet->ai_monthly_spending_limit_minor ? \App\Support\Money::fromMinor((int)$wallet->ai_monthly_spending_limit_minor) : '0') }}" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm"><button class="rounded-lg bg-slate-900 px-3 text-xs font-bold text-white">Save</button></div></label></form></div>
</div>

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Your plans</p><h2 class="mt-1 text-xl font-black">Choose the tools and AI allowance that fit your work</h2><p class="mt-1 text-sm leading-6 text-slate-600">Study Pro and Teaching Pro are role-specific workspace plans. AI Plus is an optional specialist AI package that students, lecturers and other members can buy alongside them. Each payment covers the tools or allowance shown for that plan.</p></div>
    <div class="mt-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950">
        <p class="font-black">When can an extra AI charge happen?</p>
        <p class="mt-1 leading-6">AcadSyntra uses your daily free AI requests first, then the AI requests included in your plan. Only after both are finished can a successful new AI request be charged to your wallet. Your monthly AI spending limit still applies, and a failed request is not charged.</p>
    </div>
    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        @forelse($featureRows->where('package', true) as $row)
            <article id="feature-{{ $row['key'] }}" class="rounded-2xl border p-5 {{ request('feature') === $row['key'] ? 'border-indigo-400 ring-2 ring-indigo-100' : '' }}">
                @php($state = data_get($row, 'package_status.state', $row['rule'] ? 'locked' : 'free'))
                <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-wide text-indigo-600">{{ ($row['package_kind'] ?? 'workspace') === 'ai_addon' ? 'Optional AI package' : 'Role workspace package' }}</p><h3 class="mt-1 text-xl font-black text-slate-900">{{ $row['title'] }}</h3><p class="mt-2 text-sm leading-6 text-slate-500">{{ $row['description'] }}</p></div>
                    @if(!$row['rule'] && ($row['catalogue_requires_price'] ?? false))<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Not offered yet</span>
                    @elseif(!$row['rule'])<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Free</span>
                    @elseif($row['sponsored'])<span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">Institution sponsored</span>
                    @elseif($state==='trial')<span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">Trial</span>
                    @elseif($state==='active')<span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">Active</span>
                    @elseif($state==='grace')<span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Read-only grace</span>
                    @elseif(!$row['available_for_purchase'])<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Purchases paused</span>
                    @else<span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">Paid</span>@endif
                </div>
                @if($row['ai_tools'] !== [])
                    <details class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3"><summary class="cursor-pointer text-sm font-bold text-slate-800">{{ count($row['ai_tools']) }} AI tool(s) use this plan’s allowance</summary><div class="mt-3 flex flex-wrap gap-2">@foreach($row['ai_tools'] as $tool)<span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">{{ $tool['title'] }}</span>@endforeach</div></details>
                @endif
                @if(in_array($state, ['active','trial'], true) && $row['entitlement'])
                    <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900"><p class="font-black">Access active{{ $row['entitlement']->expires_at ? ' until '.$row['entitlement']->expires_at->format('d M Y') : '' }}</p><p class="mt-1 text-xs">{{ number_format((int)($row['entitlement']->remaining_units ?? 0)) }} included AI request(s) remaining.</p></div>
                @elseif($state==='grace')
                    <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"><p class="font-black">Saved work remains readable until {{ data_get($row,'package_status.grace_ends_at')?->format('d M Y') }}.</p><p class="mt-1 text-xs">Renew to create, edit or generate new work.</p></div>
                @endif
                @if(!$row['available_for_purchase'] && !in_array($state, ['active','trial'], true))
                    <p class="mt-4 rounded-xl bg-slate-100 p-3 text-xs leading-5 text-slate-600">This allowance package is not needed while extra AI charging is disabled. You can continue using enabled AI tools under the current platform-funded setting.</p>
                @elseif($row['rule'] && !$row['sponsored'] && !in_array($state, ['active','trial'], true))
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($row['offers'] as $period => $offer)<form method="POST" action="{{ route('commerce.features.purchase') }}" class="rounded-xl border bg-slate-50 p-4">@csrf<input type="hidden" name="feature" value="{{ $row['key'] }}"><input type="hidden" name="billing_period" value="{{ $period }}"><input type="hidden" name="request_id" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><p class="text-xs font-black uppercase text-slate-500">{{ $offer['label'] }}</p><p class="mt-1 text-lg font-black">{{ $row['rule']->currency }} {{ \App\Support\Money::fromMinor($offer['amount_minor']) }}</p><p class="mt-1 text-xs font-semibold text-slate-700">Workspace tools for {{ $offer['duration_days'] }} days</p><p class="mt-1 text-xs text-slate-500">Includes {{ number_format($offer['included_ai_requests']) }} AI request(s). Extra AI is charged only after free and included requests finish.</p><button class="mt-3 w-full rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">Choose {{ strtolower($offer['label']) }}</button></form>@endforeach</div>
                    @if($row['trial_days'] > 0 && !$row['trial_used'])<form method="POST" action="{{ route('commerce.features.trial') }}" class="mt-3">@csrf<input type="hidden" name="feature" value="{{ $row['key'] }}"><button class="text-sm font-black text-indigo-700">Start {{ $row['trial_days'] }}-day trial →</button></form>@endif
                @endif
            </article>
        @empty<p class="text-sm text-slate-500">No role-specific package is available for this account.</p>@endforelse
    </div>
</section>

@if($featureRows->where('package', false)->isNotEmpty())
<details class="mt-6 rounded-3xl border bg-white p-6 shadow-sm"><summary class="cursor-pointer font-black text-slate-900">Additional usage-based features</summary><p class="mt-2 text-sm text-slate-500">These specialist actions keep their individual pricing and sponsorship controls.</p><div class="mt-5 grid gap-4 lg:grid-cols-2">@foreach($featureRows->where('package', false) as $row)<article class="rounded-2xl border p-4"><div class="flex justify-between gap-3"><div><h3 class="font-black">{{ $row['title'] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500">{{ $row['description'] }}</p></div><span class="h-fit rounded-full px-2.5 py-1 text-xs font-bold {{ $row['rule'] ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $row['rule'] ? ($row['sponsored'] ? 'Sponsored' : ($row['entitlement'] ? 'Active' : 'Paid')) : 'Free' }}</span></div>@if($row['rule'])<p class="mt-3 text-sm font-bold">{{ $row['rule']->currency }} {{ \App\Support\Money::fromMinor((int)$row['rule']->unit_amount_minor) }} · {{ str($row['pricing_type'])->replace('_',' ') }}</p>@if($row['pricing_type']==='fixed_access' && !$row['sponsored'] && !$row['entitlement'])<form method="POST" action="{{ route('commerce.features.purchase') }}" class="mt-3">@csrf<input type="hidden" name="feature" value="{{ $row['key'] }}"><input type="hidden" name="request_id" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-bold text-white">Activate access</button></form>@endif @endif</article>@endforeach</div></details>
@endif

<section class="mt-6 rounded-3xl border bg-white p-6 shadow-sm">
    <div><p class="text-xs font-black uppercase tracking-[.16em] text-violet-600">Extra AI usage</p><h2 class="mt-1 text-xl font-black">Prices after your allowances finish</h2><p class="mt-1 text-sm leading-6 text-slate-600">The prices below do not replace your package payment. They apply only to successful AI requests made after your daily free requests and plan-included requests have both been used. Set a monthly limit above to control this extra spending.</p></div>
    <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach($aiRows as $row)
            <div class="rounded-2xl border p-4"><p class="font-black">{{ $row['title'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $row['category'] }}</p>
                @if($row['included_in_packages'] !== [])<div class="mt-2 flex flex-wrap gap-1">@foreach($row['included_in_packages'] as $package)<span class="rounded-full bg-indigo-50 px-2 py-1 text-[11px] font-bold text-indigo-700">Included in {{ $package }}</span>@endforeach</div>@else<span class="mt-2 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold text-slate-600">No package allowance</span>@endif
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
