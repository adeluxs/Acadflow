@extends('layouts.app')
@section('title', 'Assignments - '.$course->name)
@section('content')
@php
    $openCount = $tasks->filter(fn($task) => (!$task->open_at || now()->gte($task->open_at)) && (!$task->late_deadline || now()->lte($task->late_deadline)))->count();
    $submittedCount = $tasks->filter(fn($task) => $task->submissions->where('user_id', auth()->id())->isNotEmpty())->count();
@endphp
<div class="mx-auto max-w-7xl space-y-7 px-4 py-8 sm:px-6 lg:px-8">
    <section class="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-indigo-950 via-slate-950 to-slate-900 p-7 text-white shadow-xl sm:p-9">
        <div class="absolute -right-20 top-0 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div><p class="text-xs font-black uppercase tracking-[.25em] text-indigo-300">{{ $course->code }} · Coursework</p><h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Assignments</h1><p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">Track instructions, deadlines, submissions, feedback and grades from one focused workspace.</p></div>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black">{{ $tasks->count() }}</p><p class="text-[11px] text-slate-300">Total</p></div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black">{{ $openCount }}</p><p class="text-[11px] text-slate-300">Open</p></div>
                <div class="rounded-2xl bg-white/10 px-4 py-3 text-center backdrop-blur"><p class="text-2xl font-black">{{ $submittedCount }}</p><p class="text-[11px] text-slate-300">Started</p></div>
            </div>
        </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        @foreach([''=>'All','open'=>'Open','submitted'=>'Submitted','graded'=>'Graded'] as $value=>$label)
            <a href="{{ route('courses.assignments', array_merge(['course'=>$course->id], request()->except('status'), $value!=='' ? ['status'=>$value] : [])) }}" class="whitespace-nowrap rounded-xl px-4 py-2 text-sm font-bold transition {{ request('status','')===$value ? 'bg-indigo-600 text-white shadow' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="grid gap-5 lg:grid-cols-2">
        @forelse($tasks as $task)
            @php
                $mySubmission = $task->submissions->where('user_id', auth()->id())->first();
                $effectiveClose = $task->allow_late_submissions ? $task->late_deadline : ($task->due_date ?? $task->close_at);
                $isOpen = (!$task->open_at || now()->gte($task->open_at)) && (!$effectiveClose || now()->lte($effectiveClose));
                $canSubmit = $isOpen && (!$mySubmission || $task->max_resubmissions===null || $mySubmission->resubmission_count < $task->max_resubmissions);
            @endphp
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-xs font-black uppercase tracking-[.18em] text-indigo-600">{{ str($task->type)->headline() }}</p><h2 class="mt-1 text-xl font-black text-slate-900 group-hover:text-indigo-700">{{ $task->title }}</h2></div>
                        <span class="rounded-full px-3 py-1 text-xs font-bold {{ $isOpen ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $isOpen ? 'Open' : 'Closed' }}</span>
                    </div>
                    @if($task->description)<p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ $task->description }}</p>@endif
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Due</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $task->due_date?->format('M j') ?? '—' }}</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Points</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $task->max_score ?? '—' }}</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Files</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $task->min_file_count }}–{{ $task->max_file_count }}</p></div>
                        <div class="rounded-2xl bg-slate-50 p-3"><p class="text-[10px] font-black uppercase tracking-wide text-slate-400">Size</p><p class="mt-1 text-sm font-bold text-slate-800">{{ $task->max_file_size_mb }} MB</p></div>
                    </div>

                    @if($mySubmission)
                        <div class="mt-4 rounded-2xl border {{ $mySubmission->grade ? 'border-violet-200 bg-violet-50' : 'border-emerald-200 bg-emerald-50' }} p-4">
                            <div class="flex items-center justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Your work</p><p class="mt-1 text-sm font-bold text-slate-900">{{ $mySubmission->is_late ? 'Late · ' : '' }}{{ str($mySubmission->status)->headline() }}</p></div>@if($mySubmission->grade)<p class="text-xl font-black text-violet-700">{{ $mySubmission->grade->score }}/{{ $task->max_score }}</p>@endif</div>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                        @if($task->open_at && now()->lt($task->open_at))<span class="rounded-lg bg-slate-100 px-2.5 py-1 text-slate-600">Opens {{ $task->open_at->diffForHumans() }}</span>
                        @elseif($task->due_date && now()->lt($task->due_date))<span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-emerald-700">Due {{ $task->due_date->diffForHumans() }}</span>
                        @elseif($task->allow_late_submissions && $task->late_deadline && now()->lt($task->late_deadline))<span class="rounded-lg bg-amber-50 px-2.5 py-1 text-amber-700">Late window until {{ $task->late_deadline->format('M j') }}</span>
                        @endif
                        @if($task->late_submission_penalty_percent > 0)<span class="rounded-lg bg-rose-50 px-2.5 py-1 text-rose-700">{{ $task->late_submission_penalty_percent }}% late penalty</span>@endif
                    </div>
                </div>
                <div class="flex gap-2 border-t border-slate-100 bg-slate-50/70 p-4">
                    <a href="{{ route('submission-tasks.student.show', [$course,$task]) }}" class="flex-1 rounded-xl bg-slate-900 px-4 py-2.5 text-center text-sm font-black text-white hover:bg-slate-800">View assignment</a>
                    @if($canSubmit)<a href="{{ route('submissions.create') }}?task_id={{ $task->id }}" class="flex-1 rounded-xl bg-indigo-600 px-4 py-2.5 text-center text-sm font-black text-white hover:bg-indigo-700">{{ $mySubmission ? 'Resubmit' : 'Submit work' }}</a>@endif
                </div>
            </article>
        @empty
            <div class="lg:col-span-2 rounded-[2rem] border border-dashed border-slate-300 bg-white p-14 text-center"><div class="text-4xl">🗂️</div><h2 class="mt-4 text-xl font-black text-slate-900">No assignments yet</h2><p class="mt-2 text-sm text-slate-500">New coursework will appear here when it is published.</p></div>
        @endforelse
    </div>
</div>
@endsection
