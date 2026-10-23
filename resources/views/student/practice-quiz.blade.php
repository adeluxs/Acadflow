@extends('layouts.app')

@section('title', $quiz->title)
@section('page-title', $quiz->title)
@section('page-subtitle', ($quiz->course?->code ?? '').' · Practice quiz')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2"><a href="{{ $quiz->studySession ? route('study.show', $quiz->studySession) : route('study.index') }}" class="text-sm font-bold text-indigo-700">← {{ $quiz->studySession ? 'Back to my source and explanation' : 'My Study Space' }}</a><span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">AI-generated practice · Unofficial</span></div>
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

    @if($errors->any())<div class="rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @php($questions = is_array($quiz->questions) ? $quiz->questions : [])

    @if($quiz->status === 'failed' || empty($questions))
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800">
            {{ \App\Support\Errors\UserFacingError::fromAiCode($quiz->error_code)['message'] }} Choose a readable source or try again with a clearer topic.
        </div>
    @else
        <form method="POST" action="{{ route('practice-quizzes.submit', $quiz) }}" class="space-y-5">
            @csrf
            <input type="hidden" name="submission_token" value="{{ \Illuminate\Support\Str::uuid() }}">
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
        @php($latestAttempt = $attempts->first())
        <section id="feedback" class="space-y-4 rounded-2xl border border-indigo-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black">Your feedback</h2><p class="mt-1 text-sm text-slate-500">{{ $latestAttempt->score }} of {{ $latestAttempt->total }} correct on your latest attempt. Short written answers use exact wording checks.</p></div><span class="rounded-xl bg-indigo-50 px-3 py-2 text-lg font-black text-indigo-800">{{ $latestAttempt->score }}/{{ $latestAttempt->total }}</span></div>
            @if($quiz->next_review_at)<div class="rounded-xl bg-indigo-50 p-3 text-sm text-indigo-900"><strong>Next revision {{ $quiz->next_review_at->diffForHumans() }}.</strong> {{ $latestAttempt->score < $latestAttempt->total ? 'Start with the questions you missed below.' : 'Leave a gap, then try recalling these answers again.' }} Saved practice needs no new AI request.</div>@endif
            @foreach($questions as $index => $question)
                @php($feedback = $latestAttempt->answers[$index] ?? [])
                <article class="rounded-xl border p-4 {{ !empty($feedback['is_correct']) ? 'border-emerald-100 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40' }}"><p class="text-xs font-bold {{ !empty($feedback['is_correct']) ? 'text-emerald-700' : 'text-amber-800' }}">Question {{ $index + 1 }} · {{ !empty($feedback['is_correct']) ? 'Correct' : 'Revisit this' }}</p><h3 class="mt-1 text-sm font-semibold">{{ $question['question'] }}</h3><dl class="mt-3 space-y-1 text-sm"><div><dt class="inline font-semibold">Your answer:</dt> <dd class="inline">{{ ($feedback['selected'] ?? '') ?: 'Not answered' }}</dd></div><div><dt class="inline font-semibold">Expected answer:</dt> <dd class="inline">{{ $feedback['expected'] ?? $question['answer'] }}</dd></div></dl>@if(!empty($question['explanation']))<p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{{ $question['explanation'] }}</p>@endif @if(!empty($question['source_excerpt']))<blockquote class="mt-3 border-l-2 border-indigo-200 pl-3 text-xs leading-5 text-slate-600">Source passage: {{ $question['source_excerpt'] }}</blockquote>@endif</article>
            @endforeach
            @if($quiz->studySession)<a href="{{ route('study.show', $quiz->studySession) }}" class="inline-block text-sm font-bold text-indigo-700">Review the source and ask about what you missed →</a>@elseif($quiz->material)<a href="{{ route('materials.show', [$quiz->course, $quiz->material]) }}" class="inline-block text-sm font-bold text-indigo-700">Review the original source →</a>@endif
        </section>
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
