@extends('layouts.app')

@section('title', 'Mastery & readiness')
@section('page-title', 'Mastery & readiness')
@section('page-subtitle', 'See where you are strong and what to revise next')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to read this page',
        'steps' => [
            'Take practice quizzes so your mastery data can build up — flashcard reviews count too.',
            'Exam readiness (advisory) blends mastery, practice, deadlines and card reviews into one percentage.',
            'Focus next on lists your lowest-mastery topics — revise those first.',
            'After a quiz, click Refresh from practice to recompute your figures.',
        ],
        'tip' => 'Readiness is advisory only — always check your lecturer\'s requirements and your official marks.',
    ])

    <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('mastery.index') }}" class="flex flex-wrap items-end gap-3">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" class="mt-1.5 block rounded-xl border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
                    <option value="">All courses</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" @selected(request('course_id') == $course->id)>{{ $course->code }} — {{ $course->name }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <form method="POST" action="{{ route('mastery.recompute') }}" class="flex items-end">
            @csrf
            <input type="hidden" name="course_id" value="{{ request('course_id') }}">
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700" @disabled(!request('course_id'))>Refresh from practice</button>
        </form>
    </div>

    @if($readiness)
        <section class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/60 p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-indigo-600">Exam readiness (advisory)</p>
                    <p class="mt-1 text-3xl font-black text-slate-900">{{ $readiness->score }}%</p>
                </div>
                <span class="text-xs text-slate-500">{{ $readiness->generated_at?->diffForHumans() }}</span>
            </div>
            <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-4">
                <div class="rounded-xl bg-white p-3">Mastery: <strong>{{ $readiness->signals['mastery'] ?? 0 }}%</strong></div>
                <div class="rounded-xl bg-white p-3">Practice: <strong>{{ $readiness->signals['practice'] ?? 0 }}%</strong></div>
                <div class="rounded-xl bg-white p-3">Deadlines: <strong>{{ $readiness->signals['deadlines'] ?? 0 }}%</strong></div>
                <div class="rounded-xl bg-white p-3">Card reviews: <strong>{{ $readiness->signals['flashcard_reviews'] ?? 0 }}</strong></div>
            </div>
        </section>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black text-slate-900">Focus next on</h2>
        <div class="mt-3 space-y-2">
            @forelse($weak as $topic)
                <div class="flex items-center justify-between rounded-xl bg-rose-50 px-3 py-2 text-sm">
                    <span class="font-semibold text-slate-800">{{ $topic->topic }} @if($topic->course)<span class="text-xs text-slate-500">· {{ $topic->course->code }}</span>@endif</span>
                    <span class="font-black text-rose-700">{{ $topic->mastery }}%</span>
                </div>
            @empty
                <p class="text-sm text-slate-500">Take a practice quiz to build your mastery map.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black text-slate-900">All topics</h2>
        <div class="mt-3 space-y-3">
            @forelse($topics as $topic)
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-600">
                        <span>{{ $topic->topic }} @if($topic->course)<span class="text-slate-400">· {{ $topic->course->code }}</span>@endif</span>
                        <span class="font-bold">{{ $topic->mastery }}%</span>
                    </div>
                    <div class="mt-1 h-2 w-full rounded-full bg-slate-100">
                        <div class="h-2 rounded-full bg-indigo-500" style="width: {{ $topic->mastery }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">No mastery data yet.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
