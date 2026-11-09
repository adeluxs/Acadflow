@extends('layouts.app')
@section('title', 'Readers · '.$publication->title)
@section('page-title', 'Your readers')
@section('page-subtitle', $publication->title)
@section('content')
@php($max = max(1, $summary['daily']->max('reads')))
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('knowledge.manage') }}" class="text-sm font-bold text-indigo-700">← Creator dashboard</a>
        <a href="{{ route('knowledge.show', $publication) }}" class="text-sm font-bold text-slate-600">View guide →</a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total reads</p><p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($summary['total_reads']) }}</p><p class="mt-1 text-xs text-slate-500">{{ number_format($summary['signed_in']) }} signed in · {{ number_format($summary['visitors']) }} visitors</p></div>
        <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Last 7 days</p><p class="mt-2 text-3xl font-black text-slate-950">{{ number_format($summary['last_7']) }}</p><p class="mt-1 text-xs text-slate-500">new readers</p></div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-emerald-800">Verified reads</p><p class="mt-2 text-3xl font-black text-emerald-900">{{ number_format($summary['verified']) }}</p><p class="mt-1 text-xs text-emerald-800">Passed the quiz · these count for the creator pool and challenges</p></div>
        <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Quiz results</p><p class="mt-2 text-3xl font-black text-slate-950">{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '–' }}</p><p class="mt-1 text-xs text-slate-500">{{ $summary['quiz_taken'] ? number_format($summary['quiz_taken']).' took it · average score '.$summary['average_score'].'%' : 'No one has taken the quiz yet' }}</p></div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">New readers, last 30 days</h2>
        <div class="mt-5 flex h-40 items-end gap-1" role="img" aria-label="New readers per day over the last 30 days">
            @foreach($summary['daily'] as $day)
                <div class="group relative flex h-full flex-1 items-end">
                    <div class="w-full rounded-t bg-indigo-500" style="height: {{ $day['reads'] ? max(4, round(100 * $day['reads'] / $max)) : 1 }}%"></div>
                    <span class="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-0.5 text-xs text-white group-hover:block">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('j M') }}: {{ $day['reads'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-xs text-slate-500"><span>{{ \Illuminate\Support\Carbon::parse($summary['daily']->first()['date'])->format('j M') }}</span><span>Today</span></div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach(['Readers by university' => $universities, 'Readers by department' => $departments] as $heading => $groups)
            <section class="rounded-3xl border bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-slate-950">{{ $heading }}</h2>
                @php($groupMax = max(1, $groups->max('readers') ?? 1))
                <div class="mt-4 space-y-3">
                    @forelse($groups as $group)
                        <div>
                            <div class="flex justify-between gap-3 text-sm"><span class="truncate font-semibold text-slate-800">{{ $group['name'] }}</span><span class="shrink-0 font-bold text-slate-600">{{ number_format($group['readers']) }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-500" style="width: {{ round(100 * $group['readers'] / $groupMax) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No signed-in readers yet.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-lg font-black text-slate-950">How readers did on each question</h2>
        <p class="mt-1 text-sm text-slate-500">Questions most readers miss often point to a part of the guide that could be clearer, or a question that needs fixing.</p>
        <div class="mt-4 divide-y">
            @forelse($questions as $question)
                <div class="flex items-center justify-between gap-4 py-3 text-sm">
                    <span class="text-slate-800">{{ $question['question'] }} @if($question['source'] === 'ai')<span class="rounded bg-violet-100 px-1.5 py-0.5 text-xs font-black text-violet-800">AI</span>@endif</span>
                    <span class="shrink-0 text-right"><strong class="{{ $question['correct_rate'] < 50 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $question['correct_rate'] }}% right</strong><span class="block text-xs text-slate-500">{{ number_format($question['answered']) }} answered</span></span>
                </div>
            @empty
                <p class="py-3 text-sm text-slate-500">No quiz answers yet.</p>
            @endforelse
        </div>
        @if($questions->isNotEmpty())<a href="{{ route('knowledge.rewards.questions.edit', $publication) }}" class="mt-4 inline-flex text-sm font-bold text-indigo-700">Edit questions →</a>@endif
    </section>

    <p class="text-xs leading-5 text-slate-500">To protect readers' privacy, you see totals, not names. Universities and departments with fewer than {{ $minGroup }} readers are grouped under "Other". A read counts once per person.</p>
</div>
@endsection
