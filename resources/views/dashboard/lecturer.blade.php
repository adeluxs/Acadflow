{{-- resources/views/lecturer/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Lecturer Dashboard')
@section('page-title', 'Lecturer Dashboard')
@section('page-subtitle', 'Courses, submissions, attendance, and academic workflow')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">My Courses</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $courses->count() }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Pending Reviews</p>
        <h3 class="mt-2 text-3xl font-semibold text-amber-600">{{ $pendingReviews ?? 0 }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Active Sessions</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $activeSessions->count() }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Notifications</p>
        <h3 class="mt-2 text-3xl font-semibold text-blue-600">{{ auth()->user()->unreadNotifications->count() }}</h3>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6">
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold">My Courses</h3>
                <a href="{{ route('lecturer.courses') }}" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>

            @if($courses->isEmpty())
                <p class="text-slate-500">No courses assigned yet.</p>
            @else
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($courses as $course)
                        <div class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                            <p class="font-semibold text-slate-900">{{ $course->name }}</p>
                            <p class="text-sm text-slate-500">{{ $course->code }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold">Active Attendance Sessions</h3>
                <a href="{{ route('attendance.lecturer') }}" class="text-sm text-blue-600 hover:underline">Open attendance</a>
            </div>

            @if($activeSessions->isEmpty())
                <p class="text-slate-500">No active sessions.</p>
            @else
                <div class="space-y-3">
                    @foreach($activeSessions as $session)
                        <div class="flex items-center justify-between rounded-2xl border border-slate-200 p-4">
                            <div>
                                <p class="font-medium text-slate-900">{{ $session->course->name }}</p>
                                <p class="text-sm text-slate-500">Started: {{ $session->started_at->format('H:i') }}</p>
                            </div>
                            <a href="{{ route('attendance.session', $session->uuid) }}" class="text-blue-600 hover:underline text-sm">
                                Open
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>

            <div class="space-y-3">
            
                <a href="{{ route('attendance.start') }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                    Start Attendance
                </a>
                <a href="{{ route('submissions.dashboard') }}" class="block rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                    Review Submissions
                </a>
            </div>
        </div>

        <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
            <h3 class="text-lg font-semibold mb-4">Recent Activity</h3>

            @if($recentActivity ?? false)
                <div class="space-y-3">
                    @foreach($recentActivity as $activity)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="font-medium text-slate-900">{{ $activity->title }}</p>
                            <p class="text-sm text-slate-500">{{ $activity->description }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500">No recent activity yet.</p>
            @endif
        </div>
    </div>
</div>

<div class="mt-6">
    <a href="{{ route('attendance.my') }}" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 text-white px-5 py-3 font-medium hover:bg-slate-800 transition">
        View Attendance History
    </a>
</div>
@endsection