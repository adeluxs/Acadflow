@extends('layouts.app')

@section('title', 'Create Assignment')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Create New Assignment</h1>
        <p class="text-gray-600 mt-2">Course: {{ $course->name }}</p>
    </div>

    <!-- Form -->
    <form action="{{ route('submission-tasks.store', $course) }}" method="POST" class="bg-white rounded-lg shadow p-8">
        @csrf
        
        @include('submission-tasks._form')
    </form>
</div>
@endsection
