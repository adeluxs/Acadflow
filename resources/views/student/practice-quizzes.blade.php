@extends('layouts.app')

@section('title', 'Practice quizzes')
@section('page-title', 'Practice quizzes')
@section('page-subtitle', 'Test yourself before it counts')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to use practice quizzes',
        'steps' => [
            'Choose one of your enrolled courses and type the topic you want to practise.',
            'Set how many questions and a difficulty, then click Generate quiz — the questions are written from your course context.',
            'Open the quiz, answer every question, then Submit answers to see your score.',
            'Your score feeds into Mastery & readiness so you can see what to revise next.',
        ],
        'tip' => 'Start with one clear topic (for example “Foreign keys”) rather than a whole course — focused topics produce better questions. Practice never affects your official grades.',
    ])

    <form method="POST" action="{{ route('practice-quizzes.store') }}" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        @csrf
        <h2 class="text-base font-black text-slate-900">Generate a practice quiz</h2>
        @error('topic')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        @error('course_id')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Select a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Topic
                <input type="text" name="topic" required maxlength="200" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="e.g. Database normalization">
            </label>
            <label class="text-xs font-bold text-slate-600">Number of questions
                <input type="number" name="count" value="5" min="1" max="15" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
            <label class="text-xs font-bold text-slate-600">Difficulty
                <select name="difficulty" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="mixed">Mixed</option>
                    <option value="easy">Easy</option>
                    <option value="medium">Medium</option>
                    <option value="hard">Hard</option>
                </select>
            </label>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate quiz</button>
    </form>

    <section class="space-y-3">
        @forelse($quizzes as $quiz)
            <a href="{{ route('practice-quizzes.show', $quiz) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900">{{ $quiz->title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $quiz->course?->code }} · {{ $quiz->questionCount() }} questions · {{ str_replace('_', ' ', $quiz->status) }}</p>
                </div>
                <span class="text-sm font-black text-indigo-700">Open →</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No practice quizzes yet.</div>
        @endforelse
        <div class="pt-2">{{ $quizzes->links() }}</div>
    </section>
</div>
@endsection
