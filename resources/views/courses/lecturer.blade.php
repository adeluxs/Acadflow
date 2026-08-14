@extends('layouts.app')

@section('title', 'My Teaching Courses')
@section('page-title', 'My Courses')
@section('page-subtitle', 'Manage the courses you teach, enroll students, and create secure invitations.')

@section('content')
<div class="space-y-6">
    @if(session('course_invitation_url'))
        <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-indigo-950">Student invitation created</p>
                    <p class="mt-1 text-xs text-indigo-700">Only the invited email address can accept this link, and the normal institution/department restrictions still apply.</p>
                </div>
                <button type="button" class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700" onclick="navigator.clipboard?.writeText('{{ session('course_invitation_url') }}'); this.textContent='Copied'">Copy invitation link</button>
            </div>
            <input type="text" readonly value="{{ session('course_invitation_url') }}" class="mt-3 w-full rounded-xl px-3 py-2 text-xs text-slate-600">
        </section>
    @endif

    <section>
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-black text-slate-950">Teaching courses</h2>
                <p class="mt-1 text-xs text-slate-500">You can manage membership only for courses on your teaching team.</p>
            </div>
            <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">{{ $courses->count() }} assigned</span>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            @forelse($courses as $course)
                <article class="acad-card overflow-hidden">
                    <div class="border-b border-slate-100 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-lg bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase tracking-wide text-indigo-700">{{ $course->code }}</span>
                                    <span class="text-[10px] font-semibold text-slate-400">{{ $course->level }} level · {{ $course->credit_hours }} credits</span>
                                </div>
                                <h3 class="mt-2 truncate text-base font-black text-slate-950">{{ $course->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ $course->department?->name }} · {{ $course->department?->faculty?->short_name ?? $course->department?->faculty?->name }}</p>
                            </div>
                            <div class="grid shrink-0 grid-cols-2 gap-2 text-center">
                                <div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-lg font-black text-slate-900">{{ $course->enrolled_students_count ?? 0 }}</p><p class="text-[9px] text-slate-500">Students</p></div>
                                <div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-lg font-black text-slate-900">{{ $course->assignments_count ?? 0 }}</p><p class="text-[9px] text-slate-500">Tasks</p></div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('courses.show', $course) }}" class="acad-primary-button !px-3 !py-2 !text-xs">Open course</a>
                            <a href="{{ route('submission-tasks.manage.index', $course) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Assignments</a>
                            <a href="{{ route('lecturer.materials.index', $course) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Materials</a>
                            <button type="button" onclick="navigator.clipboard?.writeText('{{ route('courses.join.link', $course->uuid) }}'); this.textContent='Join link copied'" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Copy student join link</button>
                        </div>
                    </div>

                    <div class="grid gap-4 bg-slate-50/50 p-5 md:grid-cols-2">
                        <form method="POST" action="{{ route('lecturer.courses.students.enroll', $course) }}" class="rounded-xl border border-slate-200 bg-white p-4">
                            @csrf
                            <p class="text-xs font-bold text-slate-900">Enroll existing student</p>
                            <p class="mt-1 text-[10px] leading-4 text-slate-500">Use email, student ID, or username. Cross-institution and restricted cross-department enrollment is blocked.</p>
                            <label class="mt-3 block text-[10px] font-semibold uppercase tracking-wide text-slate-500" for="student-{{ $course->id }}">Student</label>
                            <input id="student-{{ $course->id }}" name="student" value="{{ old('student') }}" required autocomplete="off" placeholder="student@example.edu.ng" class="mt-1 w-full rounded-xl px-3 py-2 text-xs">
                            <button class="mt-3 w-full rounded-xl border border-slate-200 bg-slate-950 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800">Enroll student</button>
                        </form>

                        <form method="POST" action="{{ route('lecturer.courses.students.invite', $course) }}" class="rounded-xl border border-slate-200 bg-white p-4">
                            @csrf
                            <p class="text-xs font-bold text-slate-900">Invite by email</p>
                            <p class="mt-1 text-[10px] leading-4 text-slate-500">AcadFlow creates a single-recipient, expiring invitation and sends it when email delivery is configured.</p>
                            <label class="mt-3 block text-[10px] font-semibold uppercase tracking-wide text-slate-500" for="invite-{{ $course->id }}">Email</label>
                            <input id="invite-{{ $course->id }}" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="student@example.edu.ng" class="mt-1 w-full rounded-xl px-3 py-2 text-xs">
                            <button class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-50">Create invitation</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="acad-card col-span-full p-10 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="1.7" d="M5 4h5a3 3 0 013 3v13a3 3 0 00-3-3H5zM19 4h-5a3 3 0 00-3 3v13a3 3 0 013-3h5z"/></svg>
                    </div>
                    <h3 class="mt-3 text-sm font-bold text-slate-900">No teaching course assigned yet</h3>
                    <p class="mt-1 text-xs text-slate-500">If self-assignment is enabled, choose an eligible course from your department below.</p>
                </div>
            @endforelse
        </div>
    </section>

    @if($availableCourses->isNotEmpty())
        <section class="acad-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-sm font-black text-slate-950">Available in your department</h2><p class="mt-1 text-xs text-slate-500">Only active courses in your own institution and department appear here.</p></div>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-[10px] font-bold text-indigo-700">Self-assignment enabled</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($availableCourses as $course)
                    <form method="POST" action="{{ route('lecturer.courses.self-assign', $course) }}" class="rounded-xl border border-slate-200 bg-white p-4">
                        @csrf
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="text-xs font-black text-indigo-700">{{ $course->code }}</p><p class="mt-1 truncate text-xs font-bold text-slate-900">{{ $course->name }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $course->level }} level · {{ $course->credit_hours }} credits</p></div>
                            <button class="shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-bold text-white hover:bg-indigo-700">Add myself</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
