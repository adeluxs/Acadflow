@extends('layouts.app')

@section('title', 'Flashcards')
@section('page-title', 'Flashcards')
@section('page-subtitle', 'Review a little every day')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to use flashcards',
        'steps' => [
            'Choose one of your enrolled courses, type a topic, then click Generate deck.',
            'Open the deck and answer each question in your own words before revealing the answer.',
            'Rate your recall (Easy, Good, Hard or Forgot) — this schedules when the card comes back.',
        ],
        'tip' => 'Review a little every day. Ratings of Hard or Forgot bring a card back sooner, so the cards you struggle with appear most often.',
    ])

    <form method="POST" action="{{ route('flashcards.store') }}" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        @csrf
        <h2 class="text-base font-black text-slate-900">Generate a flashcard deck</h2>
        <div class="grid gap-3 sm:grid-cols-3">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Select a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Topic
                <input type="text" name="topic" required maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="e.g. Functional dependencies">
            </label>
            <label class="text-xs font-bold text-slate-600">Cards
                <input type="number" name="count" value="10" min="1" max="30" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate deck</button>
    </form>

    <section class="space-y-3">
        @forelse($decks as $deck)
            <a href="{{ route('flashcards.show', $deck) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900">{{ $deck->title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $deck->course?->code }} · {{ $deck->cardCount() }} cards</p>
                </div>
                <span class="text-sm font-black text-indigo-700">Review →</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No flashcard decks yet.</div>
        @endforelse
        <div class="pt-2">{{ $decks->links() }}</div>
    </section>
</div>
@endsection
