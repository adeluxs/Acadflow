@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Reports & Analytics</h1>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Total Submissions</h3>
            <div class="text-3xl font-bold mt-2">{{ $stats['total_submissions'] ?? 0 }}</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Attendance Rate</h3>
            <div class="text-3xl font-bold mt-2">{{ $stats['attendance_rate'] ?? 0 }}%</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Payments Collected</h3>
            <div class="text-3xl font-bold mt-2">₦{{ number_format($stats['payments_collected'] ?? 0, 2) }}</div>
        </div>
        
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-sm font-medium text-gray-500 uppercase">Collection Rate</h3>
            <div class="text-3xl font-bold mt-2">{{ $stats['collection_rate'] ?? 0 }}%</div>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Submissions by Status</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span>Draft</span>
                    <span class="font-semibold">{{ $submissionStats['draft'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Submitted</span>
                    <span class="font-semibold">{{ $submissionStats['submitted'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Under Review</span>
                    <span class="font-semibold">{{ $submissionStats['under_review'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Graded</span>
                    <span class="font-semibold">{{ $submissionStats['graded'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Approved</span>
                    <span class="font-semibold">{{ $submissionStats['approved'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Attendance Overview</h2>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span>Present</span>
                    <span class="font-semibold text-green-600">{{ $attendanceStats['present'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Late</span>
                    <span class="font-semibold text-yellow-600">{{ $attendanceStats['late'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Absent</span>
                    <span class="font-semibold text-red-600">{{ $attendanceStats['absent'] ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex gap-4">
        <a href="{{ route('admin.reports.export', 'submissions') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Export Submissions
        </a>
        <a href="{{ route('admin.reports.export', 'attendance') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Export Attendance
        </a>
        <a href="{{ route('admin.reports.export', 'billing') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            Export Billing
        </a>
    </div>
</div>
@endsection