{{-- resources/views/student/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Student Dashboard')
@section('page-title', 'Student Dashboard')
@section('page-subtitle', 'Courses, submissions, groups, and progress')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">My Courses</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $enrollments->count() }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">My Groups</p>
        <h3 class="mt-2 text-3xl font-semibold text-emerald-600">{{ $groups->count() }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Submissions</p>
        <h3 class="mt-2 text-3xl font-semibold text-blue-600">{{ $submissions->count() }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Pending Payments</p>
        <h3 class="mt-2 text-3xl font-semibold text-amber-600">{{ $pendingInvoices->count() }}</h3>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">My Courses</h3>
            <a href="{{ route('courses.index') }}" class="text-sm text-blue-600 hover:underline">Browse</a>
        </div>

        @if($enrollments->isEmpty())
            <p class="text-slate-500">You have not enrolled in any courses yet.</p>
        @else
            <div class="space-y-3">
                @foreach($enrollments as $enrollment)
                    <a href="{{ route('courses.index', $enrollment->course) }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900">{{ $enrollment->course->name }}</p>
                        <p class="text-sm text-slate-500">{{ $enrollment->course->code }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">My Groups</h3>
            <a href="{{ route('groups.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>

        @if($groups->isEmpty())
            <p class="text-slate-500">You haven't joined any groups yet.</p>
            <a href="{{ route('groups.create') }}" class="inline-flex mt-3 text-emerald-600 hover:underline">Create a group</a>
        @else
            <div class="space-y-3">
                @foreach($groups as $group)
                    <a href="{{ route('groups.show', $group) }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900">{{ $group->name }}</p>
                        <p class="text-sm text-slate-500">{{ $group->course->name }}</p>
                        <p class="text-xs text-slate-400 mt-1">
                            {{ $group->members->count() }}/{{ $group->max_members }} members
                        </p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">Recent Submissions</h3>
            <a href="{{ route('submissions.dashboard') }}" class="text-sm text-blue-600 hover:underline">Open</a>
        </div>

        @if($submissions->isEmpty())
            <p class="text-slate-500">You have no submissions yet.</p>
        @else
            <div class="space-y-3">
                @foreach($submissions as $submission)
                    <a href="{{ route('submissions.show', $submission) }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <p class="font-medium text-slate-900">{{ $submission->title }}</p>
                        <p class="text-sm text-slate-500">{{ $submission->course->name }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    <a href="{{ route('submissions.create') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 text-white px-5 py-3 font-medium hover:bg-slate-800 transition">
        + New Submission
    </a>
    <a href="{{ route('groups.index') }}" class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 text-white px-5 py-3 font-medium hover:bg-emerald-700 transition">
        My Groups
    </a>
    <a href="{{ route('attendance.my') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-200 text-slate-900 px-5 py-3 font-medium hover:bg-slate-300 transition">
        View Attendance
    </a>
</div>
@endsection