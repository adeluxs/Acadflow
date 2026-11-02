@extends('layouts.app')

@section('title', 'Reflection journal')
@section('page-title', 'Reflection journal')
@section('page-subtitle', 'Notice what is working and adjust your study approach')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to use the reflection journal',
        'steps' => [
            'After a study session, choose the course (optional), add a prompt (optional) and write honestly about what you did.',
            'Save it — the AI coach adds short, gentle feedback.',
            'Use it to spot patterns: what helps you learn and what to change next time.',
        ],
        'tip' => 'Short, honest entries are more useful than long ones.',
    ])

    <form method="POST" action="{{ route('reflections.store') }}" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        @csrf
        <h2 class="text-base font-black text-slate-900">New reflection</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Course (optional)
                <select name="course_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">General</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Prompt (optional)
                <input type="text" name="prompt" maxlength="255" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="e.g. What worked in this study session?">
            </label>
        </div>
        <label class="block text-xs font-bold text-slate-600">Your reflection
            <textarea name="reflection" rows="4" required maxlength="5000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm" placeholder="What did you do? What helped? What will you change next time?"></textarea>
        </label>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Save reflection</button>
    </form>

    <section class="space-y-3">
        @forelse($reflections as $reflection)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-slate-500">{{ $reflection->course?->code ?? 'General' }} · {{ $reflection->created_at?->diffForHumans() }}</p>
                </div>
                @if($reflection->prompt)<p class="mt-2 text-xs font-bold uppercase tracking-wide text-indigo-600">{{ $reflection->prompt }}</p>@endif
                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $reflection->reflection }}</p>
                @if($reflection->ai_feedback)
                    <div class="mt-3 rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 text-sm text-slate-700">
                        <span class="text-xs font-black uppercase tracking-wide text-indigo-600">Coach feedback</span>
                        <p class="mt-1 whitespace-pre-line">{{ $reflection->ai_feedback }}</p>
                    </div>
                @endif
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No reflections yet.</div>
        @endforelse
        <div class="pt-2">{{ $reflections->links() }}</div>
    </section>
</div>
@endsection
