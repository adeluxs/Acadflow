@extends('layouts.app')

@section('title', 'Student Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Welcome back, '.(auth()->user()->first_name ?? 'Student').' 👋')

@section('content')
@php
    $dashboardUser = auth()->user();
    $featureVisible = fn (string $feature): bool => \App\Services\FeatureAccessService::shouldShowInNavigation($feature, $dashboardUser);
@endphp
<div class="space-y-5">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['My Courses',$enrollments->count(),'Enrolled courses','bg-indigo-50 text-indigo-600'],
            ['Pending Tasks',$pendingTasks->count(),'Assignments','bg-orange-50 text-orange-600'],
            ['My Submissions',$submissionCount,'All submissions','bg-emerald-50 text-emerald-600'],
            ['CGPA',$cgpa !== null ? number_format($cgpa,2) : '—','Current CGPA','bg-blue-50 text-blue-600'],
        ] as $stat)
        <article class="acad-card p-4"><div class="flex items-start justify-between"><div><p class="text-xs text-slate-500">{{ $stat[0] }}</p><p class="mt-2 text-2xl font-black">{{ $stat[1] }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $stat[2] }}</p></div><div class="flex h-9 w-9 items-center justify-center rounded-xl {{ $stat[3] }}"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg></div></div></article>
        @endforeach
    </section>

    <section class="grid gap-5 xl:grid-cols-2">
        @if($featureVisible('assignments'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Upcoming Deadlines</h2><a href="{{ route('submissions.dashboard') }}" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2">
            @forelse($pendingTasks->take(5) as $task)
            @php($deadline=$task->close_at ?? $task->due_date)
            <a href="{{ route('courses.assignments', $task->course) }}" class="flex items-center gap-3 border-b border-slate-100 py-3 last:border-0"><div class="w-12 rounded-lg bg-indigo-50 p-2 text-center"><p class="text-[9px] font-bold uppercase text-indigo-500">{{ $deadline?->format('M') }}</p><p class="text-lg font-black text-indigo-800">{{ $deadline?->format('d') }}</p></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{{ $task->title }}</p><p class="text-[10px] text-slate-500">{{ $task->course?->code }}</p></div><span class="text-[10px] text-slate-500">{{ $deadline?->format('g:i A') }}</span></a>
            @empty <p class="rounded-xl bg-slate-50 p-5 text-center text-xs text-slate-500">You're caught up. No pending deadlines.</p> @endforelse
        </div></article>
        @endif

        @if($featureVisible('submissions'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">My Recent Submissions</h2><a href="{{ route('submissions.dashboard') }}" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2">
            @forelse($submissions->take(5) as $submission)<a href="{{ route('submissions.show',$submission) }}" class="flex items-center gap-3 rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.8" d="M5 12l4 4L19 6"/></svg></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold">{{ $submission->title }}</p><p class="text-[10px] text-slate-500">{{ $submission->course?->code }} · {{ str($submission->status)->headline() }}</p></div><span class="text-[10px] text-slate-400">{{ $submission->submitted_at?->format('M j') ?? $submission->created_at?->format('M j') }}</span></a>@empty<p class="text-xs text-slate-500">No submissions yet.</p>@endforelse
        </div></article>
        @endif
    </section>

    @if($featureVisible('courses'))
    <section class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Course Overview</h2><a href="{{ route('courses.index') }}" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        @forelse($courseProgress->take(5) as $item)<a href="{{ route('courses.show',$item['course']) }}" class="rounded-xl border border-slate-100 p-4 text-center hover:border-indigo-200"><p class="truncate text-[11px] font-bold">{{ $item['course']->name }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $item['course']->code }}</p><div class="acad-progress-ring mx-auto mt-3 flex h-20 w-20 items-center justify-center rounded-full" style="--progress:{{ $item['progress']*3.6 }}deg"><div class="flex h-14 w-14 items-center justify-center rounded-full bg-white"><span class="text-sm font-black">{{ $item['progress'] }}%</span></div></div><p class="mt-2 text-[10px] text-slate-500">Progress</p></a>@empty<p class="col-span-full text-xs text-slate-500">No enrolled courses yet.</p>@endforelse
    </div></section>
    @endif

    <section class="grid gap-5 xl:grid-cols-2">
        @if($featureVisible('ai_assistant'))
        <article class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-white to-indigo-50 p-5 shadow-sm"><div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-xs font-bold text-white">AI</div><div><h2 class="text-sm font-bold">AI Academic Assistant</h2><p class="text-[10px] text-slate-500">Get help with your academic work</p></div></div><div class="mt-4 grid grid-cols-2 gap-2 text-xs"><a href="{{ route('submissions.create') }}" class="rounded-lg border bg-white p-3">Check my assignment</a><a href="{{ route('ai.assistant', ['tool' => 'ask']) }}" class="rounded-lg border bg-white p-3">Explain a topic</a><a href="{{ route('ai.assistant', ['tool' => 'writing']) }}" class="rounded-lg border bg-white p-3">Improve my writing</a><a href="{{ route('knowledge.index') }}" class="rounded-lg border bg-white p-3">Study resources</a></div><a href="{{ route('ai.assistant') }}" class="mt-4 flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white">Ask AI Assistant →</a></article>
        @endif

        @if($featureVisible('notifications'))
        <article class="acad-card p-5"><div class="flex items-center justify-between"><h2 class="text-sm font-bold">Announcements</h2><a href="{{ route('notifications.index') }}" class="text-xs font-semibold acad-link">View all</a></div><div class="mt-4 space-y-2">@forelse($announcements as $notification)<a href="{{ route('notifications.index') }}" class="block rounded-xl border border-slate-100 p-3 hover:bg-slate-50"><p class="text-xs font-semibold">{{ $notification->title }}</p><p class="mt-1 line-clamp-1 text-[10px] text-slate-500">{{ $notification->message }}</p><p class="mt-1 text-[9px] text-slate-400">{{ $notification->created_at?->diffForHumans() }}</p></a>@empty<p class="text-xs text-slate-500">No announcements yet.</p>@endforelse</div></article>
        @endif
    </section>
</div>
@endsection
