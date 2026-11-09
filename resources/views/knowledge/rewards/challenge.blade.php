@extends('layouts.app')
@section('title', $challenge->title)
@section('page-title', $challenge->title)
@section('page-subtitle', 'Reading Challenge leaderboard')
@section('content')
@include('knowledge._nav')
<div class="space-y-6">
    <section class="rounded-[2rem] bg-gradient-to-br from-amber-500 via-orange-600 to-rose-700 p-7 text-white shadow-xl">
        <p class="text-xs font-black uppercase tracking-[0.2em] text-amber-100">Reading Challenge · {{ $challenge->isRunning() ? 'Live now' : ($challenge->starts_at->isFuture() ? 'Starting soon' : 'Ended') }}</p>
        <h1 class="mt-2 text-3xl font-black">{{ $challenge->title }}</h1>
        @if($challenge->description)<p class="mt-3 max-w-3xl whitespace-pre-line text-sm leading-6 text-amber-50">{{ $challenge->description }}</p>@endif
        <div class="mt-5 flex flex-wrap gap-2 text-sm font-bold">
            <span class="rounded-full bg-white/15 px-3 py-1.5">{{ $challenge->starts_at->format('j M') }} to {{ $challenge->ends_at->format('j M Y, g:ia') }}</span>
            <span class="rounded-full bg-white/15 px-3 py-1.5">₦{{ number_format($challenge->prize_pool_minor / 100) }} in prizes</span>
            <span class="rounded-full bg-white/15 px-3 py-1.5">Pass at least {{ $challenge->min_passed_reads }} guides to rank</span>
        </div>
        @if($challenge->prizes)
            <p class="mt-4 text-sm text-amber-50">Prizes: @foreach($challenge->prizes as $prize)#{{ $loop->iteration }} ₦{{ number_format($prize / 100) }}@if(! $loop->last) · @endif @endforeach</p>
        @endif
    </section>

    @if($winners->isNotEmpty())
        <section class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
            <h2 class="text-lg font-black text-emerald-950">Winners</h2>
            <ol class="mt-3 space-y-1 text-sm">@foreach($winners as $winner)<li>#{{ $winner->rank }} {{ $names[$winner->user_id]?->full_name ?? 'AcadSyntra reader' }} · ₦{{ number_format($winner->amount_minor / 100) }}</li>@endforeach</ol>
        </section>
    @endif

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between"><h2 class="text-lg font-black text-slate-950">Leaderboard</h2><a href="{{ route('knowledge.index') }}" class="text-sm font-bold text-indigo-700">Find guides to read →</a></div>
        <p class="mt-1 text-xs text-slate-500">Points from guides whose quiz you passed during the challenge. Ties go to whoever got there first. Winners are checked before prizes are paid.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-500"><th class="py-2 pr-3">Rank</th><th class="py-2 pr-3">Reader</th><th class="py-2 pr-3">School</th><th class="py-2 pr-3 text-right">Guides</th><th class="py-2 text-right">Points</th></tr></thead>
                <tbody class="divide-y">
                    @forelse($board as $row)
                        @php($reader = $names[$row->user_id] ?? null)
                        <tr class="{{ (int) $row->user_id === auth()->id() ? 'bg-indigo-50 font-bold' : '' }}">
                            <td class="py-2.5 pr-3">#{{ $loop->iteration }}</td>
                            <td class="py-2.5 pr-3">{{ $reader ? $reader->first_name.' '.mb_substr((string) $reader->last_name, 0, 1).'.' : 'Reader' }}</td>
                            <td class="py-2.5 pr-3 text-slate-500">{{ $reader?->university?->short_name ?? '' }}</td>
                            <td class="py-2.5 pr-3 text-right">{{ $row->read_count }}</td>
                            <td class="py-2.5 text-right font-black">{{ number_format((int) $row->total_points) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">No one has ranked yet. Be the first!</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
