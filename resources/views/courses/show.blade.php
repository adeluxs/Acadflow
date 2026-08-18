@extends('layouts.app')
@section('title', $course->name)
@section('page-title', $course->code)
@section('page-subtitle', $course->name)
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <a href="{{ auth()->user()->isLecturer() ? route('lecturer.courses') : route('courses.index') }}" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-indigo-700">← Back to courses</a>

    <section class="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
        <div class="relative bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-700 p-6 text-white sm:p-8 lg:p-10">
            <div class="absolute right-8 top-0 h-48 w-48 rounded-full bg-sky-400/10 blur-3xl"></div>
            <div class="relative flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-4xl"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-white/10 px-3 py-1 text-xs font-black ring-1 ring-white/15">{{ $course->code }}</span><span class="rounded-full px-3 py-1 text-xs font-black {{ $course->is_active ? 'bg-emerald-400/20 text-emerald-100 ring-1 ring-emerald-300/20' : 'bg-rose-400/20 text-rose-100' }}">{{ $course->is_active ? 'Active course' : 'Inactive course' }}</span></div><h1 class="mt-4 text-3xl font-black tracking-tight sm:text-4xl">{{ $course->name }}</h1><p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">{{ $course->description ?: 'Course workspace for learning materials, assignments, discussions and attendance.' }}</p></div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('materials.index',$course) }}" class="rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-900">Materials</a>
                    @if($isEnrolled)<a href="{{ route('courses.assignments',$course) }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold">Assignments</a>@elseif($isLecturer)<a href="{{ route('submission-tasks.manage.index',$course) }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold">Manage assignments</a>@endif
                    <a href="{{ route('discussions.index',$course) }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold">Discussions</a>
                </div>
            </div>
        </div>
        <div class="grid gap-px bg-slate-200 sm:grid-cols-3 lg:grid-cols-6">
            @foreach([
                ['Credits',$course->credit_hours],['Level',ucfirst($course->level)],['Semester',ucfirst($course->semester)],['Students',$course->enrolled_students_count ?? 0],['Materials',$course->visible_materials_count ?? 0],['Assignments',$course->published_assignments_count ?? 0]
            ] as [$label,$value])<div class="bg-white p-4"><p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $label }}</p><p class="mt-1 font-black text-slate-900">{{ $value }}</p></div>@endforeach
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_330px]">
        <main class="space-y-6">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-black text-slate-950">Upcoming & recent assignments</h2><p class="mt-1 text-sm text-slate-500">Stay ahead of the work published for this course.</p></div>@if($isEnrolled)<a href="{{ route('courses.assignments',$course) }}" class="text-sm font-black text-indigo-700">View all →</a>@endif</div>
                <div class="mt-5 space-y-3">@forelse($recentTasks as $task)<a href="{{ $isEnrolled ? route('submission-tasks.student.show',[$course,$task]) : route('submission-tasks.lecturer.show',[$course,$task]) }}" class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/40 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-lg bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase text-indigo-700">{{ ucfirst($task->type) }}</span><span class="text-xs text-slate-400">{{ $task->max_score }} pts</span></div><h3 class="mt-2 font-black text-slate-900">{{ $task->title }}</h3><p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $task->description ?: 'Open the assignment for instructions and submission requirements.' }}</p></div><div class="shrink-0 text-left sm:text-right"><p class="text-xs text-slate-400">Due</p><p class="font-bold {{ $task->due_date && $task->due_date->isPast() ? 'text-rose-600' : 'text-slate-800' }}">{{ $task->due_date?->format('M j, Y · H:i') ?? 'No date' }}</p></div></a>@empty<div class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">No assignments are available right now.</div>@endforelse</div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between"><div><h2 class="text-lg font-black text-slate-950">Latest materials</h2><p class="mt-1 text-sm text-slate-500">Lecture notes, slides, readings and resources.</p></div><a href="{{ route('materials.index',$course) }}" class="text-sm font-black text-indigo-700">All materials →</a></div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">@forelse($recentMaterials as $material)<a href="{{ route('materials.show',[$course,$material]) }}" class="group rounded-2xl border border-slate-200 p-4 transition hover:border-indigo-200 hover:shadow-md"><div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-lg">▤</div><div class="min-w-0"><p class="text-[10px] font-black uppercase tracking-wide text-sky-700">{{ str($material->type)->headline() }}</p><h3 class="mt-1 line-clamp-2 font-black text-slate-900">{{ $material->title }}</h3><p class="mt-2 text-xs text-slate-500">{{ $material->topic ?: ($material->week_number ? 'Week '.$material->week_number : 'Course resource') }}</p></div></div></a>@empty<div class="sm:col-span-2 rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">No course materials have been added yet.</div>@endforelse</div>
            </section>

            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between"><div><h2 class="text-lg font-black text-slate-950">Recent discussions</h2><p class="mt-1 text-sm text-slate-500">Questions and academic conversations from this course.</p></div><a href="{{ route('discussions.index',$course) }}" class="text-sm font-black text-indigo-700">Open forum →</a></div><div class="mt-5 space-y-3">@forelse($recentDiscussions as $discussion)<a href="{{ route('discussions.show',[$course,$discussion]) }}" class="block rounded-2xl border border-slate-200 p-4 hover:border-indigo-200"><div class="flex items-start justify-between gap-4"><div><h3 class="font-black text-slate-900">{{ $discussion->title }}</h3><p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ strip_tags($discussion->content) }}</p></div>@if($discussion->is_pinned)<span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-black text-amber-700">Pinned</span>@endif</div><p class="mt-3 text-xs text-slate-400">{{ $discussion->user?->full_name ?? 'Course member' }} · {{ $discussion->created_at?->diffForHumans() }}</p></a>@empty<div class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-slate-500">No discussion threads yet.</div>@endforelse</div></section>
        </main>

        <aside class="space-y-5">
            <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-black text-slate-950">Course team</h2><div class="mt-4 space-y-3">@forelse($course->lecturerAssignments as $assignment)<div class="flex items-center gap-3"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700">{{ strtoupper(substr($assignment->user?->first_name ?? 'L',0,1)) }}</div><div><p class="text-sm font-bold text-slate-900">{{ $assignment->user?->full_name ?? 'Lecturer' }}</p><p class="text-xs text-slate-500">{{ $assignment->is_coordinator ? 'Course coordinator' : 'Lecturer' }}</p></div></div>@empty<p class="text-sm text-slate-500">No lecturer has been assigned.</p>@endforelse</div></section>
            <section class="rounded-[1.75rem] border border-slate-200 bg-gradient-to-br from-indigo-50 to-sky-50 p-5"><h2 class="font-black text-slate-950">Quick access</h2><div class="mt-4 grid gap-2"><a href="{{ route('materials.index',$course) }}" class="rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm">▤ Course materials</a><a href="{{ route('discussions.index',$course) }}" class="rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm">◌ Discussions</a>@if($isEnrolled)<a href="{{ route('courses.assignments',$course) }}" class="rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm">✓ Assignments</a>@endif</div></section>
        </aside>
    </div>
</div>
@endsection
