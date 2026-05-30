{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Dashboard')
@section('page-subtitle', 'Manage universities, users, courses, and system operations')

@section('content')
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Total Students</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['total_students'] ?? 0 }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Total Lecturers</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['total_lecturers'] ?? 0 }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Total Courses</p>
        <h3 class="mt-2 text-3xl font-semibold text-slate-900">{{ $stats['total_courses'] ?? 0 }}</h3>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <p class="text-sm text-slate-500">Pending Payments</p>
        <h3 class="mt-2 text-3xl font-semibold text-amber-600">{{ $stats['pending_payments'] ?? 0 }}</h3>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">Quick Actions</h3>
            <span class="text-xs text-slate-500">Admin tools</span>
        </div>

        <div class="grid sm:grid-cols-2 gap-3">
            <a href="" class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                <p class="font-medium text-slate-900">Manage Users</p>
                <p class="text-sm text-slate-500 mt-1">Students, lecturers, admins</p>
            </a>

            <a href="{{ route('admin.courses') }}" class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                <p class="font-medium text-slate-900">Manage Courses</p>
                <p class="text-sm text-slate-500 mt-1">Create and organize courses</p>
            </a>

            <a href="{{ route('admin.reports') }}" class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                <p class="font-medium text-slate-900">View Reports</p>
                <p class="text-sm text-slate-500 mt-1">Analytics and usage data</p>
            </a>

            <a href="{{ route('admin.subscriptions') }}" class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                <p class="font-medium text-slate-900">Billing & Plans</p>
                <p class="text-sm text-slate-500 mt-1">Subscriptions and access tiers</p>
            </a>
        </div>
    </div>

    <div class="rounded-2xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-semibold">System Status</h3>
            <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">Healthy</span>
        </div>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-slate-600">Database</span>
                <span class="text-emerald-600 font-medium">Connected</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-600">Queue</span>
                <span class="text-emerald-600 font-medium">Running</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-600">Storage</span>
                <span class="text-emerald-600 font-medium">Available</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-slate-600">Notifications</span>
                <span class="text-emerald-600 font-medium">Active</span>
            </div>
        </div>
    </div>
</div>
@endsection