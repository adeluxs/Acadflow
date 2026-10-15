@extends('layouts.app')

@section('title', 'Study plans')
@section('page-title', 'Study plans')
@section('page-subtitle', 'A personal plan for every course')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to use study plans',
        'steps' => [
            'Choose a course and how many weeks you want to plan for.',
            'Click Generate study plan — the AI turns your course topics into week-by-week steps.',
            'Open the plan and work through each step in order.',
        ],
        'tip' => 'Plans can take a moment to generate; if a plan shows as pending, refresh shortly. Create a new plan any time your schedule changes.',
    ])

    <form method="POST" action="{{ route('study-plans.store') }}" class="space-y-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        @csrf
        <h2 class="text-base font-black text-slate-900">Create a study plan</h2>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="text-xs font-bold text-slate-600">Course
                <select name="course_id" required class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Select a course</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold text-slate-600">Weeks
                <input type="number" name="weeks" value="4" min="1" max="12" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </label>
        </div>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate study plan</button>
    </form>

    <section class="space-y-3">
        @forelse($plans as $plan)
            <a href="{{ route('study-plans.show', $plan) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900">{{ $plan->title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ $plan->course?->code }} · {{ $plan->itemCount() }} items · {{ str_replace('_', ' ', $plan->status) }}</p>
                </div>
                <span class="text-sm font-black text-indigo-700">Open →</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No study plans yet.</div>
        @endforelse
        <div class="pt-2">{{ $plans->links() }}</div>
    </section>
</div>
@endsection
