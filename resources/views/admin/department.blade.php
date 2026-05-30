@extends('layouts.app')

@section('title', 'Department Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Department Dashboard</h1>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Students</div>
            <div class="text-3xl font-bold">{{ $stats['total_students'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Lecturers</div>
            <div class="text-3xl font-bold">{{ $stats['total_lecturers'] }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-gray-500 text-sm">Total Courses</div>
            <div class="text-3xl font-bold">{{ $stats['total_courses'] }}</div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4">Quick Links</h2>
            <div class="space-y-2">
                <a href="{{ route('admin.courses') }}" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Manage Courses</a>
                <a href="" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Manage Users</a>
                <a href="{{ route('admin.billing') }}" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Billing</a>
                <a href="{{ route('admin.reports') }}" class="block p-3 bg-gray-50 rounded hover:bg-gray-100">Reports</a>
            </div>
        </div>
    </div>
</div>
@endsection