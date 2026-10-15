@extends('layouts.app')

@section('title', 'Edit Assignment')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Edit Assignment</h1>
        <p class="text-gray-600 mt-2">Course: {{ $course->name }} | Assignment: {{ $task->title }}</p>
    </div>

    <!-- Form -->
    <form action="{{ route('submission-tasks.update', [$course, $task]) }}" method="POST" class="bg-white rounded-lg shadow p-8">
        @csrf
        @method('PUT')
        
        @include('submission-tasks._form')
    </form>
</div>
@endsection
