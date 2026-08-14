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

        @php
            $adminActions = [
                ['Manage Users', 'Students, lecturers, admins', 'admin.users'],
                ['Manage Courses', 'Create and organize courses', 'admin.courses'],
                ['View Reports', 'Analytics and usage data', 'admin.reports'],
                ['Billing & Plans', 'Subscriptions and access tiers', 'admin.subscriptions'],
            ];
        @endphp
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach($adminActions as [$label, $description, $routeName])
                @if(Route::has($routeName))
                    @php
                        $featureKey = \App\Services\FeatureAccessService::featureForRoute($routeName);
                        $featureStatus = $featureKey
                            ? \App\Services\FeatureAccessService::effectiveStatus($featureKey, auth()->user()?->university_id)
                            : null;
                    @endphp
                    <a href="{{ route($routeName) }}" class="rounded-2xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium text-slate-900">{{ $label }}</p>
                            @if($featureStatus === \App\Services\FeatureAccessService::STATUS_MAINTENANCE)
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-[9px] font-bold uppercase text-amber-700">Maintenance</span>
                            @elseif($featureStatus === \App\Services\FeatureAccessService::STATUS_DISABLED)
                                <span class="rounded-full bg-slate-200 px-2 py-1 text-[9px] font-bold uppercase text-slate-600">Disabled</span>
                            @endif
                        </div>
                        <p class="text-sm text-slate-500 mt-1">{{ $description }}</p>
                    </a>
                @endif
            @endforeach
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