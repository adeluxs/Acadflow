@extends('layouts.app')

@section('title', 'My Courses')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Courses</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($enrollments as $enrollment)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold">{{ $enrollment->course->code }}</h3>
                <p class="text-gray-600">{{ $enrollment->course->name }}</p>
                <div class="mt-4">
                    <span class="text-sm text-gray-500">{{ $enrollment->course->credit_hours }} Credits</span>
                </div>
                <div class="mt-4">
                    <a href="{{ route('courses.show', $enrollment->course->uuid) }}" class="text-indigo-600 hover:text-indigo-900">View Course</a>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No enrollments found.</p>
        @endforelse
    </div>
</div>
@endsection