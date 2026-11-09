@extends('layouts.app')
@section('title', 'Read & Earn')
@section('page-title', 'Read & Earn')
@section('page-subtitle', 'Learn on the Knowledge Hub and get rewarded for it')
@section('content')
@include('knowledge._nav')
@php
    $poolChecks = [
        ['label' => 'Account at least '.$rules['knowledge_reader_pool_min_account_days'].' days old', 'done' => $summary['account_days'] >= $rules['knowledge_reader_pool_min_account_days'], 'progress' => min($summary['account_days'], $rules['knowledge_reader_pool_min_account_days']).'/'.$rules['knowledge_reader_pool_min_account_days'].' days'],
        ['label' => $rules['knowledge_reader_pool_min_passed_reads'].' guides passed', 'done' => $summary['lifetime_reads'] >= $rules['knowledge_reader_pool_min_passed_reads'], 'progress' => min($summary['lifetime_reads'], $rules['knowledge_reader_pool_min_passed_reads']).'/'.$rules['knowledge_reader_pool_min_passed_reads']],
        ['label' => 'Guides from '.$rules['knowledge_reader_pool_min_creators'].' different creators', 'done' => $summary['lifetime_creators'] >= $rules['knowledge_reader_pool_min_creators'], 'progress' => min($summary['lifetime_creators'], $rules['knowledge_reader_pool_min_creators']).'/'.$rules['knowledge_reader_pool_min_creators']],
        ['label' => 'Average score of '.$rules['knowledge_reader_pool_min_average'].'% this month', 'done' => $summary['counted'] > 0 && $summary['average'] >= $rules['knowledge_reader_pool_min_average'], 'progress' => $summary['counted'] > 0 ? $summary['average'].'%' : 'No passed guides yet'],
    ];
    if ($rules['knowledge_read_earn_requires_pass']) {
        $poolChecks[] = ['label' => 'Active Reader Pass', 'done' => $hasPass, 'progress' => $hasPass ? 'Active' : 'Not active'];
    }
    $poolReady = collect($poolChecks)->every(fn ($check) => $check['done']);
@endphp
<div class="space-y-6">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif

    <section class="rounded-[2rem] bg-gradient-to-br from-emerald-900 via-indigo-950 to-indigo-800 p-7 text-white shadow-xl">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-emerald-200">Learn · Share · Earn</p>
        <h1 class="mt-2 text-3xl font-black">Your learning pays for more learning</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-100">Read a guide, then answer its quick questions. Pass to earn points and study credit. Reach the reading level below and you also share the monthly cash pool of ₦{{ number_format((float) $rules['knowledge_reader_pool_amount']) }}.</p>
        <div class="mt-6 grid gap-3 sm:grid-cols-4">
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Points this month</p><p class="mt-1 text-2xl font-black">{{ number_format($summary['points']) }}</p></div>
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Guides passed this month</p><p class="mt-1 text-2xl font-black">{{ $summary['counted'] }}</p></div>
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Study credit this month</p><p class="mt-1 text-2xl font-black">₦{{ number_format($summary['credit_minor'] / 100) }}<span class="text-sm font-bold text-indigo-200"> / ₦{{ number_format((float) $rules['knowledge_read_earn_monthly_credit_cap']) }}</span></p></div>
            <div class="rounded-2xl bg-white/10 p-4"><p class="text-xs text-indigo-200">Reader Pass</p><p class="mt-1 text-2xl font-black">{{ $hasPass ? 'Active' : 'Not active' }}</p></div>
        </div>
    </section>

    @if(! $hasPass && $prices['monthly_minor'])
        <section class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6">
            @if($summary['held_points'] > 0)<p class="mb-2 inline-flex rounded-full bg-emerald-600 px-3 py-1 text-xs font-black text-white">{{ number_format($summary['held_points']) }} points saved for you</p>@endif
            <h2 class="text-lg font-black text-slate-950">{{ $summary['held_points'] > 0 ? 'Get the Reader Pass to collect your points' : 'Get the Reader Pass' }}</h2>
            <p class="mt-1 text-sm text-slate-600">Unlimited reading{{ $rules['knowledge_read_earn_requires_pass'] ? ', and you need it to earn points, credit and cash' : '' }}.</p>
            <div class="mt-4">@include('knowledge.rewards._pass-buttons', ['prices' => $prices])</div>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Unlock the monthly cash pool</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $poolReady ? 'You qualify. Keep passing guides: your share grows with your points.' : 'Reach every step to share the pool at the end of the month.' }}</p>
            <ul class="mt-4 space-y-2">
                @foreach($poolChecks as $check)
                    <li class="flex items-center justify-between gap-3 rounded-xl border p-3 text-sm {{ $check['done'] ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200' }}">
                        <span class="font-semibold {{ $check['done'] ? 'text-emerald-900' : 'text-slate-700' }}">{{ $check['done'] ? '✓' : '○' }} {{ $check['label'] }}</span>
                        <span class="text-xs font-bold text-slate-500">{{ $check['progress'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-500">Cash goes to your withdrawable earnings. Withdraw from your <a href="{{ route('commerce.wallet') }}" class="font-bold text-indigo-700 underline">wallet</a> to a verified bank account in your name.</p>
        </section>

        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">How it works</h2>
            <ol class="mt-3 space-y-3 text-sm leading-6 text-slate-700">
                <li><strong>1. Read a guide properly.</strong> Answers sent faster than the guide can be read don't count.</li>
                <li><strong>2. Answer its questions.</strong> Score {{ $rules['knowledge_read_earn_pass_mark'] }}% or more to earn up to {{ $rules['knowledge_read_earn_points_per_read'] }} points. One attempt per guide.</li>
                <li><strong>3. Add what you learned</strong> for {{ $rules['knowledge_read_earn_reflection_bonus'] }} bonus points ({{ $rules['knowledge_read_earn_reflection_min_words'] }}+ words).</li>
                <li><strong>4. Get study credit straight away,</strong> up to ₦{{ number_format((float) $rules['knowledge_read_earn_monthly_credit_cap']) }} a month, to spend on AcadSyntra.</li>
                <li><strong>5. Share the cash pool</strong> at the end of the month once you reach the reading level.</li>
            </ol>
            <p class="mt-3 text-xs text-slate-500">Up to {{ $rules['knowledge_read_earn_daily_cap'] }} earning guides a day. Fake accounts and shared answers are removed before any cash is paid.</p>
        </section>
    </div>

    @if($writingChallenges->isNotEmpty())
        <section class="rounded-3xl bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-6 text-white">
            <p class="text-xs font-black uppercase tracking-[.2em] text-amber-300">Official · AcadSyntra Writing Challenge</p>
            <div class="mt-3 grid gap-3 md:grid-cols-3">
                @foreach($writingChallenges as $writing)
                    <a href="{{ route('knowledge.writing.show', $writing) }}" class="rounded-2xl bg-white/10 p-4 hover:bg-white/15">
                        <p class="font-black">{{ $writing->title }}</p>
                        <p class="mt-1 text-2xl font-black text-amber-300">₦{{ number_format(intdiv($writing->prizePoolMinor(), 100)) }}</p>
                        <p class="mt-1 text-sm text-indigo-100">{{ $writing->phase() === 'open' ? 'Entries close '.$writing->submission_deadline->format('j M') : 'Opens '.$writing->starts_at->format('j M') }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($challenges->isNotEmpty())
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">Reading Challenges</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @foreach($challenges as $challenge)
                    <a href="{{ route('knowledge.rewards.challenges.show', $challenge) }}" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 hover:border-amber-300">
                        <p class="text-xs font-black uppercase tracking-wide text-amber-700">{{ $challenge->isRunning() ? 'Live now' : ($challenge->starts_at->isFuture() ? 'Starts '.$challenge->starts_at->format('j M') : 'Ended') }} · ₦{{ number_format($challenge->prize_pool_minor / 100) }} in prizes</p>
                        <p class="mt-1 font-black text-slate-950">{{ $challenge->title }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $challenge->starts_at->format('j M') }} to {{ $challenge->ends_at->format('j M Y') }} · Your rank: {{ $myRanks[$challenge->id] ? '#'.$myRanks[$challenge->id] : 'not ranked yet' }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">Cash rewards paid to you</h2>
        <div class="mt-3 divide-y">
            @forelse($payouts as $payout)
                <div class="flex justify-between gap-3 py-3 text-sm"><span>{{ \App\Models\KnowledgeRewardPayout::KINDS[$payout->kind] ?? 'Reward' }} · {{ $payout->paid_at?->format('j M Y') }}</span><strong>₦{{ number_format($payout->amount_minor / 100) }}</strong></div>
            @empty
                <p class="py-4 text-sm text-slate-500">No cash rewards yet. Keep reading!</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
