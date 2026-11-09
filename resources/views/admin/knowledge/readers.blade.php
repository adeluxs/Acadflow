@extends('layouts.app')
@section('title', 'Readers · '.$publication->title)
@section('page-title', 'Readers')
@section('page-subtitle', $publication->title.' · by '.($publication->creator?->full_name ?? 'unknown'))
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.knowledge.readers.index') }}" class="text-sm font-bold text-indigo-700">← All guides</a>
        <a href="{{ route('knowledge.show', $publication) }}" class="text-sm font-bold text-slate-600">View guide →</a>
    </div>
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Total reads</p><p class="mt-2 text-3xl font-black">{{ number_format($summary['total_reads']) }}</p><p class="text-xs text-slate-500">{{ number_format($summary['signed_in']) }} signed in · {{ number_format($summary['visitors']) }} visitors</p></div>
        <div class="rounded-2xl border bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Last 7 days</p><p class="mt-2 text-3xl font-black">{{ number_format($summary['last_7']) }}</p></div>
        <div class="rounded-2xl border bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Verified reads</p><p class="mt-2 text-3xl font-black">{{ number_format($summary['verified']) }}</p></div>
        <div class="rounded-2xl border bg-white p-5"><p class="text-xs font-bold uppercase text-slate-500">Quiz pass rate</p><p class="mt-2 text-3xl font-black">{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '–' }}</p><p class="text-xs text-slate-500">{{ number_format($summary['quiz_taken']) }} took it{{ $summary['average_score'] !== null ? ' · avg '.$summary['average_score'].'%' : '' }}</p></div>
    </section>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div><h2 class="text-lg font-black">Signed-in readers</h2><p class="text-sm text-slate-500">Visible to admins only. Creators see totals, not names.</p></div>
            <form method="GET" class="flex gap-2"><label class="sr-only" for="reader-q">Search readers</label><input id="reader-q" name="q" value="{{ $search }}" placeholder="Name or email" class="rounded-xl border-slate-300 text-sm"><button class="rounded-xl bg-slate-950 px-3 py-2 text-sm font-bold text-white">Search</button></form>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y text-sm">
                <thead class="text-left text-xs font-black uppercase tracking-wide text-slate-500"><tr><th class="py-2 pr-4">Reader</th><th class="py-2 pr-4">University · department</th><th class="py-2 pr-4">Read</th><th class="py-2 pr-4">Quiz</th><th class="py-2 pr-4">Time reading</th><th class="py-2">Account age</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($readers as $reader)
                        <tr class="align-top">
                            <td class="py-2 pr-4"><p class="font-semibold text-slate-900">{{ trim($reader->first_name.' '.$reader->last_name) }}</p><p class="text-xs text-slate-500">{{ $reader->email }}</p></td>
                            <td class="py-2 pr-4 text-slate-600">{{ $reader->university ?? '–' }}@if($reader->department)<span class="block text-xs">{{ $reader->department }}</span>@endif</td>
                            <td class="py-2 pr-4 whitespace-nowrap text-slate-600">{{ \Illuminate\Support\Carbon::parse($reader->read_at)->format('j M Y, H:i') }}</td>
                            <td class="py-2 pr-4">
                                @if($reader->attempt_status)
                                    <span class="font-bold {{ $reader->passed ? 'text-emerald-700' : 'text-rose-700' }}">{{ $reader->score_percent }}% {{ $reader->passed ? 'passed' : 'failed' }}</span>
                                    <span class="block text-xs text-slate-500">{{ str_replace('_', ' ', $reader->attempt_status) }}{{ $reader->attempt_reason ? ' · '.str_replace('_', ' ', $reader->attempt_reason) : '' }}{{ $reader->points ? ' · '.$reader->points.' pts' : '' }}</span>
                                @else
                                    <span class="text-slate-400">Not taken</span>
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-slate-600">{{ $reader->read_seconds ? gmdate($reader->read_seconds >= 3600 ? 'G\h i\m' : 'i\m s\s', (int) $reader->read_seconds) : '–' }}</td>
                            <td class="py-2 text-slate-600">{{ $reader->joined_at ? \Illuminate\Support\Carbon::parse($reader->joined_at)->diffForHumans(null, true) : '–' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">No signed-in readers{{ $search ? ' match "'.$search.'"' : ' yet' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $readers->links() }}</div>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">Readers by university</h2>
            <div class="mt-3 divide-y text-sm">@forelse($universities as $group)<div class="flex justify-between py-2"><span>{{ $group['name'] }}</span><strong>{{ number_format($group['readers']) }}</strong></div>@empty<p class="py-2 text-slate-500">None yet.</p>@endforelse</div>
        </section>
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black">Questions</h2>
            <div class="mt-3 divide-y text-sm">@forelse($questions as $question)<div class="flex justify-between gap-3 py-2"><span>{{ \Illuminate\Support\Str::limit($question['question'], 80) }}</span><strong class="shrink-0 {{ $question['correct_rate'] < 50 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $question['correct_rate'] }}%</strong></div>@empty<p class="py-2 text-slate-500">No answers yet.</p>@endforelse</div>
        </section>
    </div>
</div>
@endsection
