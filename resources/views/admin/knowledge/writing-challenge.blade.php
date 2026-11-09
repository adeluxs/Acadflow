@extends('layouts.app')
@section('title', 'Judge: '.$challenge->title)
@section('page-title', 'Writing Challenge')
@section('page-subtitle', $challenge->title)
@section('content')
@php($phase = $challenge->phase())
@php($drafts = $payouts->where('status', 'draft'))
<div class="space-y-6">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif

    <section class="rounded-3xl bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-7 text-white">
        <a href="{{ route('admin.knowledge.read-earn.index') }}" class="text-sm font-bold text-indigo-200 underline">← Read &amp; Earn admin</a>
        <h1 class="mt-3 text-3xl font-black">{{ $challenge->title }}</h1>
        <p class="mt-1 text-indigo-100">{{ ucfirst($phase) }} · {{ $entries->count() }} entries · {{ $entries->whereNotNull('judge_score')->count() }} scored · prize pool ₦{{ number_format(intdiv($challenge->prizePoolMinor(), 100)) }}</p>
        <div class="mt-5">@include('knowledge.writing._phases')</div>
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('knowledge.writing.show', $challenge) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-950">Public page</a>
            @if(in_array($phase, ['upcoming', 'open'], true) && ! $challenge->announced_at)
                <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.announce', $challenge) }}" onsubmit="return confirm('Send an announcement to every active user (in-app, email and push)?')">@csrf<button class="rounded-xl bg-amber-300 px-4 py-2.5 text-sm font-black text-slate-950">📣 Announce to all users</button></form>
            @elseif($challenge->announced_at)
                <span class="rounded-xl bg-white/10 px-4 py-2.5 text-sm font-bold">Announced {{ $challenge->announced_at->diffForHumans() }}</span>
            @endif
            @if($phase === 'judging')
                <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.publish', $challenge) }}" onsubmit="return confirm('Publish results, notify every entrant and draft the prizes? This cannot be undone.')">@csrf<button class="rounded-xl bg-emerald-400 px-4 py-2.5 text-sm font-black text-emerald-950">🏆 Publish results</button></form>
            @endif
        </div>
    </section>

    @if($payouts->isNotEmpty())
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-xl font-black">Prizes</h2>
                @if($drafts->isNotEmpty())<form method="POST" action="{{ route('admin.knowledge.read-earn.pay') }}" onsubmit="return confirm('Pay {{ $drafts->count() }} prizes totalling ₦{{ number_format($drafts->sum('amount_minor') / 100) }}?')">@csrf<input type="hidden" name="scope_key" value="{{ $challenge->scopeKey() }}"><button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Pay {{ $drafts->count() }} · ₦{{ number_format($drafts->sum('amount_minor') / 100) }}</button></form>@endif
            </div>
            @include('admin.knowledge.partials.reward-rows', ['rows' => $payouts, 'pointsLabel' => 'score'])
        </section>
    @endif

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Entries</h2>
        <p class="mt-1 text-sm text-slate-500">Score each entry 0–25 on each criterion (100 total). Final score = {{ $challenge->judge_weight }}% judges + {{ 100 - $challenge->judge_weight }}% readers (verified reads, scaled to the most-read entry).</p>
        <div class="mt-4 space-y-4">
            @forelse($entries as $entry)
                @php($mine = $entry->scores->firstWhere('judge_id', auth()->id()))
                <article class="rounded-2xl border p-5 {{ $entry->status === 'disqualified' ? 'border-rose-200 bg-rose-50/50' : ($entry->award ? 'border-amber-300 bg-amber-50/40' : '') }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-wide text-slate-500">{{ $entry->rank ? '#'.$entry->rank.' · ' : '' }}{{ $entry->award ?? ucfirst($entry->status) }}{{ $entry->track ? ' · '.$entry->track->name : '' }}</p>
                            <a href="{{ route('knowledge.show', $entry->publication) }}" target="_blank" rel="noopener" class="font-black text-slate-950 underline">{{ $entry->publication?->title }}</a>
                            <p class="text-sm text-slate-500">{{ $entry->user?->full_name }} · {{ $entry->user?->email }} · {{ number_format((int) $entry->publication?->document?->word_count) }} words · entered {{ $entry->created_at->format('j M') }}</p>
                            @if($entry->note)<p class="text-xs text-rose-700">{{ $entry->note }}</p>@endif
                        </div>
                        <div class="text-right text-sm"><p><strong>{{ $entry->judge_score !== null ? number_format($entry->judge_score, 1) : '—' }}</strong> judges ({{ $entry->scores->count() }})</p><p><strong>{{ $entry->verified_reads }}</strong> verified reads ({{ number_format((float) $entry->reader_score, 0) }})</p>@if($entry->final_score !== null)<p class="text-lg font-black">{{ number_format($entry->final_score, 1) }} final</p>@endif</div>
                    </div>
                    @if(! $challenge->results_published_at && $entry->status !== 'disqualified')
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.score', $entry) }}" class="mt-4 grid gap-2 sm:grid-cols-5">
                            @csrf
                            @foreach(\App\Models\KnowledgeWritingChallenge::CRITERIA as $key => $label)
                                <label class="text-xs font-bold text-slate-600">{{ $label }}<input type="number" name="scores[{{ $key }}]" min="0" max="25" required value="{{ $mine->scores[$key] ?? '' }}" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label>
                            @endforeach
                            <button class="self-end rounded-lg bg-slate-900 px-3 py-2 text-sm font-bold text-white">{{ $mine ? 'Update score' : 'Save score' }}</button>
                            <input name="comment" maxlength="1000" value="{{ $mine->comment ?? '' }}" placeholder="Private note (optional)" class="rounded-lg border-slate-200 text-sm sm:col-span-5">
                        </form>
                    @endif
                    @if(! $challenge->results_published_at)
                        <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.disqualify', $entry) }}" class="mt-2 flex flex-wrap gap-2">@csrf @method('PATCH')
                            <input type="hidden" name="disqualified" value="{{ $entry->status === 'disqualified' ? 0 : 1 }}">
                            @if($entry->status !== 'disqualified')<input name="note" maxlength="500" placeholder="Reason (e.g. copied work)" class="w-64 rounded-lg border-slate-200 text-xs">@endif
                            <button class="rounded-lg border px-3 py-1.5 text-xs font-bold {{ $entry->status === 'disqualified' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry->status === 'disqualified' ? 'Reinstate' : 'Disqualify' }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">No entries yet.</p>
            @endforelse
        </div>
    </section>

    @if(! $challenge->results_published_at)
        <details class="rounded-3xl border bg-white p-6 shadow-sm">
            <summary class="cursor-pointer text-lg font-black">Edit challenge</summary>
            <form method="POST" action="{{ route('admin.knowledge.read-earn.writing.update', $challenge) }}" class="mt-4">@csrf @method('PUT')
                @include('admin.knowledge.partials.writing-challenge-fields', ['challenge' => $challenge, 'defaultPool' => 0])
                <button class="mt-4 rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-bold text-white">Save changes</button>
            </form>
        </details>
    @endif
</div>
@endsection
