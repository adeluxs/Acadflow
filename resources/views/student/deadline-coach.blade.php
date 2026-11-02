@extends('layouts.app')

@section('title', 'Deadline coach')
@section('page-title', 'Deadline coach')
@section('page-subtitle', 'Turn upcoming deadlines into an action plan')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    @if(session('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif

    @include('student._help', [
        'title' => 'How to use the deadline coach',
        'steps' => [
            'Choose one of your courses that has upcoming deadlines.',
            'Click Generate action plan — the AI breaks the work into ordered steps with suggested timing.',
            'Open the plan and start with the earliest step.',
        ],
        'tip' => 'Generate a fresh plan whenever a deadline moves or new work is set, so your steps stay realistic.',
    ])

    <form method="POST" action="{{ route('deadline-coach.store') }}" class="flex flex-wrap items-end gap-3 rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm">
        @csrf
        <label class="text-xs font-bold text-slate-600">Course
            <select name="course_id" required class="mt-1.5 block rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <option value="">Select a course</option>
                @foreach($courses as $course)
                    <option value="{{ $course->id }}">{{ $course->code }} — {{ $course->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Generate action plan</button>
    </form>

    <section class="space-y-3">
        @forelse($plans as $plan)
            <a href="{{ route('deadline-coach.show', $plan) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-indigo-200">
                <div>
                    <h3 class="font-black text-slate-900">Deadline plan · {{ $plan->course?->code }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500">{{ is_array($plan->items) ? count($plan->items) : 0 }} steps · {{ $plan->generated_at?->diffForHumans() }}</p>
                </div>
                <span class="text-sm font-black text-indigo-700">Open →</span>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center text-sm text-slate-500">No deadline plans yet.</div>
        @endforelse
        <div class="pt-2">{{ $plans->links() }}</div>
    </section>
</div>
@endsection
