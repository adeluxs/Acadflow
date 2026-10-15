@extends('layouts.app')

@section('title', $quiz->title)
@section('page-title', $quiz->title)
@section('page-subtitle', ($quiz->course?->code ?? '').' · Practice quiz')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'Answering a practice quiz',
        'steps' => [
            'Read each question and choose or type your answer.',
            'Submit answers once — your score is shown and saved under Recent attempts.',
            'Review anything you missed, then take another quiz or open Mastery & readiness.',
        ],
        'tip' => 'This is practice only and does not change your official grades. Attempt every question before submitting so your score reflects your knowledge.',
    ])

    @php($questions = is_array($quiz->questions) ? $quiz->questions : [])

    @if($quiz->status === 'failed' || empty($questions))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
            This quiz could not be generated ({{ $quiz->error_code ?: 'no questions returned' }}). Try again with a clearer topic.
        </div>
    @else
        <form method="POST" action="{{ route('practice-quizzes.submit', $quiz) }}" class="space-y-5">
            @csrf
            @foreach($questions as $index => $question)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Question {{ $index + 1 }} · {{ $question['difficulty'] ?? 'medium' }}</p>
                    <p class="mt-2 font-semibold text-slate-900">{{ $question['question'] }}</p>
                    <div class="mt-3 space-y-2">
                        @if(!empty($question['options']))
                            @foreach($question['options'] as $option)
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                                    <input type="radio" name="answers[{{ $index }}]" value="{{ $option }}"> {{ $option }}
                                </label>
                            @endforeach
                        @else
                            <input type="text" name="answers[{{ $index }}]" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="Your answer">
                        @endif
                    </div>
                </section>
            @endforeach
            <button type="submit" class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Submit answers</button>
        </form>
    @endif

    @if($attempts->isNotEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-sm font-black text-slate-900">Recent attempts</h2>
            <div class="mt-3 space-y-2">
                @foreach($attempts as $attempt)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">{{ $attempt->completed_at?->diffForHumans() }}</span>
                        <span class="font-black text-slate-900">{{ $attempt->score }}/{{ $attempt->total }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <a href="{{ route('practice-quizzes.index') }}" class="inline-block text-sm font-semibold text-slate-500 hover:text-indigo-700">← All practice quizzes</a>
</div>
@endsection
