@extends('layouts.app')
@section('title', 'AcadSyntra Writing Challenge')
@section('page-title', 'AcadSyntra Writing Challenge')
@section('page-subtitle', 'Write. Get read. Win.')
@section('content')
@include('knowledge._nav')
@php($naira = fn ($minor) => '₦'.number_format(intdiv((int) $minor, 100)))
<div class="space-y-8">
    @if($live)
        <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-950 via-violet-900 to-fuchsia-800 p-7 text-white shadow-2xl sm:p-10">
            <div aria-hidden="true" class="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-amber-300/20 blur-3xl"></div>
            <div class="relative grid gap-6 lg:grid-cols-[1.3fr_1fr] lg:items-end">
                <div>
                    <span class="rounded-full bg-amber-300 px-3 py-1 text-xs font-black uppercase tracking-wider text-slate-950">Official · {{ ['upcoming' => 'Coming soon', 'open' => 'Open now', 'judging' => 'Judging'][$live->phase()] ?? '' }}</span>
                    <h1 class="mt-4 text-3xl font-black leading-tight sm:text-5xl">{{ $live->title }}</h1>
                    @if($live->theme)<p class="mt-3 text-lg font-bold text-amber-200">Theme: {{ $live->theme }}</p>@endif
                    <p class="mt-3 max-w-2xl text-sm leading-7 text-indigo-100">Write a guide or story on the Knowledge Hub. Readers who pass your quiz push you up the live board, and AcadSyntra judges pick the winners.</p>
                    <a href="{{ route('knowledge.writing.show', $live) }}" class="mt-6 inline-flex rounded-xl bg-amber-300 px-5 py-3 text-sm font-black text-slate-950 shadow-lg hover:bg-amber-200">{{ $live->phase() === 'open' ? 'Enter now →' : 'See the challenge →' }}</a>
                </div>
                <div class="rounded-3xl bg-black/25 p-6">
                    <p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Prize pool</p>
                    <p class="text-5xl font-black text-amber-300">{{ $naira($live->prizePoolMinor()) }}</p>
                    <p class="mt-2 text-sm text-indigo-100">{{ $live->entries_count }} {{ \Illuminate\Support\Str::plural('entry', $live->entries_count) }} · entries close {{ $live->submission_deadline->format('j M') }}</p>
                    <div class="mt-5">@include('knowledge.writing._phases', ['challenge' => $live])</div>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-[2rem] bg-gradient-to-br from-indigo-950 to-violet-900 p-8 text-white">
            <h1 class="text-3xl font-black">The next Writing Challenge is coming</h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-indigo-100">Start writing now: guides with quiz questions that readers love are the ones that win. Follow AcadSyntra announcements so you don't miss the launch.</p>
            <a href="{{ route('knowledge.manage.create') }}" class="mt-5 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-black text-indigo-900">Start writing</a>
        </section>
    @endif

    <section class="grid gap-4 md:grid-cols-4">
        @foreach([['✍️', 'Write', 'Publish an original guide or story on the Knowledge Hub.'], ['❓', 'Add a quiz', 'Add 3–5 questions so readers can prove they learned.'], ['📣', 'Get read', 'Every reader who passes your quiz moves you up the live board.'], ['🏆', 'Win', 'Judges and readers decide. Prizes land in your wallet.']] as [$icon, $title, $text])
            <div class="rounded-3xl border bg-white p-5 shadow-sm"><p class="text-3xl">{{ $icon }}</p><p class="mt-2 font-black text-slate-950">{{ $title }}</p><p class="mt-1 text-sm text-slate-600">{{ $text }}</p></div>
        @endforeach
    </section>

    @if($hallOfFame->isNotEmpty())
        <section>
            <h2 class="text-2xl font-black text-slate-950">🏆 Hall of Fame</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($hallOfFame as $entry)
                    <a href="{{ $entry->publication ? route('knowledge.show', $entry->publication) : '#' }}" class="rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm hover:shadow-md">
                        <p class="text-xs font-black uppercase tracking-wider text-amber-700">{{ $entry->award }} · {{ $entry->challenge?->theme ?: $entry->challenge?->title }}</p>
                        <p class="mt-2 font-black text-slate-950">{{ $entry->publication?->title }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $entry->user?->full_name }}{{ $entry->user?->university?->short_name ? ' · '.$entry->user->university->short_name : '' }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($challenges->count() > ($live ? 1 : 0))
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <h2 class="text-lg font-black text-slate-950">All Writing Challenges</h2>
            <div class="mt-3 divide-y">
                @foreach($challenges as $challenge)
                    <a href="{{ route('knowledge.writing.show', $challenge) }}" class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm hover:text-violet-700"><span class="font-bold">{{ $challenge->title }}</span><span class="text-slate-500">{{ ['upcoming' => 'Coming soon', 'open' => 'Open', 'judging' => 'Judging', 'results' => 'Winners announced'][$challenge->phase()] ?? '' }} · {{ $naira($challenge->prizePoolMinor()) }}</span></a>
                @endforeach
            </div>
        </section>
    @endif

    <p class="text-center text-sm text-slate-500">Looking for challenges run by lecturers, clubs and students? <a href="{{ route('knowledge.challenges.index') }}" class="font-bold text-violet-700 underline">Community challenges</a></p>
</div>
@endsection
