@extends('layouts.app')
@section('title', $challenge->title)
@section('page-title', 'AcadSyntra Writing Challenge')
@section('page-subtitle', $challenge->theme ?: $challenge->title)
@push('seo')
    @include('partials.seo', ['seo' => app(\App\Services\Seo\KnowledgeSeo::class)->writingChallenge($challenge)])
@endpush
@section('content')
@include('knowledge._nav')
@php
    $phase = $challenge->phase();
    $countTo = match ($phase) { 'upcoming' => $challenge->starts_at, 'open' => $challenge->submission_deadline, 'judging' => $challenge->results_at, default => null };
    $countLabel = match ($phase) { 'upcoming' => 'Opens in', 'open' => 'Entries close in', 'judging' => 'Winners announced in', default => null };
    $prizes = array_values((array) $challenge->prizes);
    $medals = ['🥇', '🥈', '🥉'];
    $naira = fn ($minor) => '₦'.number_format(intdiv((int) $minor, 100));
    $checkLabels = ['published' => 'Published publicly on the Knowledge Hub', 'new' => 'Published after the challenge opened', 'length' => 'At least '.number_format($challenge->min_words).' words', 'questions' => 'At least '.$challenge->min_questions.' Read & Earn questions', 'track' => 'In one of the challenge tracks', 'not_entered' => 'Not already entered'];
@endphp
<div class="space-y-7">
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-900">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif

    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-7 text-white shadow-2xl sm:p-10">
        <div aria-hidden="true" class="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-amber-300/20 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-24 left-10 h-64 w-64 rounded-full bg-fuchsia-400/20 blur-3xl"></div>
        <div class="relative">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-amber-300 px-3 py-1 text-xs font-black uppercase tracking-wider text-slate-950">Official · AcadSyntra</span>
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">{{ ['upcoming' => 'Coming soon', 'open' => 'Open for entries', 'judging' => 'Judging in progress', 'results' => 'Winners announced', 'draft' => 'Draft', 'cancelled' => 'Cancelled'][$phase] }}</span>
                <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-bold">{{ number_format($entryCount) }} {{ \Illuminate\Support\Str::plural('entry', $entryCount) }}</span>
            </div>
            <h1 class="mt-4 max-w-4xl text-3xl font-black leading-tight sm:text-5xl">{{ $challenge->title }}</h1>
            @if($challenge->theme)<p class="mt-3 text-lg font-bold text-amber-200">Theme: {{ $challenge->theme }}</p>@endif
            @if($challenge->description)<p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-7 text-indigo-100">{{ $challenge->description }}</p>@endif

            <div class="mt-7 grid gap-4 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Prize pool</p>
                    <p class="text-5xl font-black tracking-tight text-amber-300 sm:text-6xl">{{ $naira($challenge->prizePoolMinor()) }}</p>
                    <p class="mt-1 text-sm text-indigo-100">Paid by AcadSyntra straight into winners' withdrawable earnings.</p>
                </div>
                @if($countTo)
                    <div class="rounded-2xl bg-black/25 p-4 text-center" data-countdown="{{ $countTo->toIso8601String() }}">
                        <p class="text-xs font-black uppercase tracking-wider text-indigo-200">{{ $countLabel }}</p>
                        <p class="mt-1 font-mono text-3xl font-black tabular-nums" data-countdown-text>{{ now()->diffForHumans($countTo, true) }}</p>
                    </div>
                @endif
            </div>
            <div class="mt-6">@include('knowledge.writing._phases')</div>
        </div>
    </section>

    @if($phase === 'results' && $winners->isNotEmpty())
        <section class="rounded-[2rem] border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-6 shadow-sm sm:p-8">
            <h2 class="text-2xl font-black text-slate-950">🏆 The winners</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach($winners as $winner)
                    <a href="{{ $winner->publication ? route('knowledge.show', $winner->publication) : '#' }}" class="group rounded-3xl border border-amber-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $winner->rank === 1 ? 'md:order-2 md:scale-105 ring-2 ring-amber-300' : ($winner->rank === 2 ? 'md:order-1' : 'md:order-3') }}">
                        <p class="text-3xl">{{ $medals[($winner->rank ?? 99) - 1] ?? '🏅' }}</p>
                        <p class="mt-2 text-xs font-black uppercase tracking-wider text-amber-700">{{ $winner->award }}</p>
                        <p class="mt-1 font-black text-slate-950 group-hover:text-indigo-700">{{ $winner->publication?->title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $winner->user?->full_name }}{{ $winner->user?->university?->short_name ? ' · '.$winner->user->university->short_name : '' }}</p>
                        <p class="mt-2 text-xs text-slate-500">Score {{ number_format((float) $winner->final_score, 1) }} · {{ $winner->verified_reads }} verified reads</p>
                    </a>
                @endforeach
            </div>
            @if($finalists->isNotEmpty())
                <h3 class="mt-7 text-sm font-black uppercase tracking-wider text-slate-500">Finalists</h3>
                <div class="mt-3 flex flex-wrap gap-2">@foreach($finalists as $finalist)<a href="{{ $finalist->publication ? route('knowledge.show', $finalist->publication) : '#' }}" class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-900">#{{ $finalist->rank }} {{ $finalist->publication?->title }} · {{ $finalist->user?->first_name }}</a>@endforeach</div>
            @endif
            @if(! $paid)<p class="mt-4 text-xs text-slate-500">Prizes are being processed and will appear in winners' wallets shortly.</p>@endif
        </section>
    @endif

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]">
        <div class="space-y-6">
            <section class="rounded-[2rem] border bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-black text-slate-950">Prizes</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach($prizes as $i => $prize)
                        <div class="rounded-2xl border p-4 text-center {{ $i === 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200' }}"><p class="text-2xl">{{ $medals[$i] ?? '🏅' }}</p><p class="mt-1 text-xs font-black uppercase tracking-wider text-slate-500">{{ ['1st', '2nd', '3rd'][$i] ?? ($i + 1).'th' }} place</p><p class="text-2xl font-black text-slate-950">{{ $naira($prize) }}</p></div>
                    @endforeach
                </div>
                @if($challenge->track_prize_minor > 0 && $tracks->isNotEmpty())
                    <p class="mt-4 text-sm text-slate-600"><strong>Plus {{ $naira($challenge->track_prize_minor) }} "Best in track" bonus</strong> for the top entry in each track that didn't win an overall prize:</p>
                    <div class="mt-2 flex flex-wrap gap-2">@foreach($tracks as $track)<span class="rounded-full bg-violet-50 px-3 py-1 text-sm font-semibold text-violet-800 ring-1 ring-violet-200">{{ $track->name }}</span>@endforeach</div>
                @endif
                <p class="mt-4 text-sm text-slate-600">Every finalist gets a <strong>Finalist</strong> badge on their guide, and winners get a permanent 🏆 badge on the Knowledge Hub.</p>
            </section>

            <section class="rounded-[2rem] border bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-black text-slate-950">How winners are chosen</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-indigo-50 p-4"><p class="text-3xl font-black text-indigo-700">{{ $challenge->judge_weight }}%</p><p class="font-bold text-slate-900">Judges' score</p><p class="mt-1 text-sm text-slate-600">AcadSyntra judges score accuracy, clarity, usefulness to students and originality.</p></div>
                    <div class="rounded-2xl bg-emerald-50 p-4"><p class="text-3xl font-black text-emerald-700">{{ 100 - $challenge->judge_weight }}%</p><p class="font-bold text-slate-900">Readers' score</p><p class="mt-1 text-sm text-slate-600">Verified reads: readers who actually read your guide and passed its quiz. Clicks and likes don't count.</p></div>
                </div>
                <h3 class="mt-6 font-black text-slate-900">Rules</h3>
                <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm leading-6 text-slate-600">
                    <li>Publish an original guide or story on the Knowledge Hub after the challenge opens, then enter it here.</li>
                    <li>At least {{ number_format($challenge->min_words) }} words, with at least {{ $challenge->min_questions }} Read &amp; Earn questions (AcadSyntra's AI writes these for you when you publish).</li>
                    @if($tracks->isNotEmpty())<li>Your publication must be in one of the tracks: {{ $tracks->pluck('name')->implode(', ') }}.</li>@endif
                    <li>One entry per person. You can withdraw and enter a different piece until entries close.</li>
                    <li>Copied work, other people's notes and unedited AI text are disqualified. Faked reads are removed.</li>
                </ol>
            </section>
        </div>

        <div class="space-y-6">
            @if($phase === 'open' || $phase === 'upcoming')
                <section class="rounded-[2rem] border-2 border-violet-200 bg-white p-6 shadow-sm">
                    <h2 class="text-xl font-black text-slate-950">{{ $myEntry ? 'Your entry' : 'Enter the challenge' }}</h2>
                    @guest
                        <p class="mt-2 text-sm text-slate-600">Create a free account, write your guide on the Knowledge Hub, then enter it here.</p>
                        <div class="mt-4 flex gap-2"><a href="{{ route('register') }}" class="rounded-xl bg-violet-700 px-4 py-2.5 text-sm font-black text-white">Join free</a><a href="{{ route('login') }}" class="rounded-xl border px-4 py-2.5 text-sm font-bold">Sign in</a></div>
                    @else
                        @if($myEntry)
                            <div class="mt-3 rounded-2xl bg-violet-50 p-4"><p class="text-xs font-black uppercase text-violet-700">Entered</p><a href="{{ route('knowledge.show', $myEntry->publication) }}" class="font-black text-slate-950 underline">{{ $myEntry->publication?->title }}</a><p class="mt-1 text-sm text-slate-600">{{ $myEntry->verified_reads }} verified reads so far. Share your guide in class groups to climb the board.</p></div>
                            @if($phase === 'open')<form method="POST" action="{{ route('knowledge.writing.withdraw', $myEntry) }}" class="mt-3" onsubmit="return confirm('Withdraw this entry?')">@csrf @method('DELETE')<button class="text-sm font-bold text-rose-700 underline">Withdraw entry</button></form>@endif
                        @elseif($phase === 'upcoming')
                            <p class="mt-2 text-sm text-slate-600">Entries open {{ $challenge->starts_at->format('j M, g:ia') }}. Start drafting now: publish your piece after the challenge opens.</p>
                            <a href="{{ route('knowledge.manage.create') }}" class="mt-4 inline-flex rounded-xl bg-violet-700 px-4 py-2.5 text-sm font-black text-white">Start writing</a>
                        @else
                            <p class="mt-2 text-sm text-slate-600">Pick a guide or story you wrote. Each must tick every box.</p>
                            <div class="mt-4 space-y-3">
                                @forelse($eligibility as $row)
                                    <div class="rounded-2xl border p-4 {{ $row['eligible'] ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200' }}">
                                        <p class="font-bold text-slate-900">{{ $row['publication']->title }}</p>
                                        <ul class="mt-2 space-y-0.5 text-xs">@foreach($row['checks'] as $check => $ok)<li class="{{ $ok ? 'text-emerald-700' : 'text-rose-700' }}">{{ $ok ? '✓' : '✗' }} {{ $checkLabels[$check] }}@if($check === 'length') ({{ number_format($row['words']) }})@elseif($check === 'questions') ({{ $row['questions'] }})@endif</li>@endforeach</ul>
                                        @if($row['eligible'])
                                            <form method="POST" action="{{ route('knowledge.writing.enter', $challenge) }}" class="mt-3">@csrf<input type="hidden" name="publication_id" value="{{ $row['publication']->id }}"><button class="rounded-xl bg-violet-700 px-4 py-2 text-sm font-black text-white">Enter this piece</button></form>
                                        @elseif(! $row['checks']['questions'])
                                            <a href="{{ route('knowledge.rewards.questions.edit', $row['publication']) }}" class="mt-3 inline-flex text-sm font-bold text-violet-700 underline">Add quiz questions</a>
                                        @endif
                                    </div>
                                @empty
                                    <p class="rounded-2xl border border-dashed p-4 text-sm text-slate-500">You haven't written anything yet.</p>
                                @endforelse
                            </div>
                            <a href="{{ route('knowledge.manage.create') }}" class="mt-4 inline-flex rounded-xl border border-violet-200 px-4 py-2.5 text-sm font-black text-violet-800">+ Write a new guide or story</a>
                        @endif
                    @endguest
                </section>
            @endif

            <section class="rounded-[2rem] border bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-2"><h2 class="text-xl font-black text-slate-950">🔥 Readers' favourite</h2>@if($phase === 'open')<span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-700">LIVE</span>@endif</div>
                <p class="mt-1 text-xs text-slate-500">Ranked by verified reads: readers who passed the guide's quiz. This is {{ 100 - $challenge->judge_weight }}% of the final score.</p>
                <ol class="mt-4 space-y-2">
                    @forelse($board as $entry)
                        <li class="flex items-center gap-3 rounded-2xl border border-slate-100 p-3 {{ auth()->id() === $entry->user_id ? 'bg-violet-50' : '' }}">
                            <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full {{ $loop->iteration <= 3 ? 'bg-amber-300 text-slate-950' : 'bg-slate-100 text-slate-600' }} text-sm font-black">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1"><a href="{{ route('knowledge.show', $entry->publication) }}" class="block truncate text-sm font-bold text-slate-900 hover:text-violet-700">{{ $entry->publication?->title }}</a><p class="truncate text-xs text-slate-500">{{ $entry->user?->first_name }}{{ $entry->user?->university?->short_name ? ' · '.$entry->user->university->short_name : '' }}</p></div>
                            <span class="text-sm font-black text-slate-900">{{ $entry->verified_reads }}</span>
                        </li>
                    @empty
                        <li class="rounded-2xl border border-dashed p-5 text-center text-sm text-slate-500">No entries yet. Be the first!</li>
                    @endforelse
                </ol>
            </section>
        </div>
    </div>
</div>
<script>
    document.querySelectorAll('[data-countdown]').forEach((box) => {
        const end = new Date(box.dataset.countdown).getTime();
        const text = box.querySelector('[data-countdown-text]');
        const tick = () => {
            const left = Math.max(0, end - Date.now());
            const d = Math.floor(left / 864e5), h = Math.floor(left % 864e5 / 36e5), m = Math.floor(left % 36e5 / 6e4), s = Math.floor(left % 6e4 / 1e3);
            text.textContent = (d ? d + 'd ' : '') + String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's';
            if (left > 0) setTimeout(tick, 1000);
        };
        tick();
    });
</script>
@endsection
