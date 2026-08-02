@extends('layouts.app')

@section('title', 'My Teaching Courses')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">My Teaching Courses</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($courses as $course)
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold">{{ $course->code }}</h3>
                <p class="text-gray-600">{{ $course->name }}</p>
                <div class="mt-4 flex gap-4 text-sm text-gray-500">
                    <span>{{ $course->enrollments->count() }} Students</span>
                </div>
                <div class="mt-4">
                    <a href="{{ route('courses.show', $course) }}" class="text-indigo-600 hover:text-indigo-900">View Course</a>
                </div>
            </div>
        @empty
            <p class="text-gray-500">No courses found.</p>
        @endforelse
    </div>
</div>
@endsection