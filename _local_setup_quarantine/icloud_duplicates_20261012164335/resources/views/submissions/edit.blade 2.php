@extends('layouts.app')

@section('title', 'Edit Submission')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">Edit Submission</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('submissions.update', $submission) }}">
            @csrf
            @method('PUT')
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Title</label>
                <input name="title" type="text" class="w-full px-3 py-2 border rounded" value="{{ $submission->title }}" required>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" class="w-full px-3 py-2 border rounded" rows="4">{{ $submission->description }}</textarea>
            </div>

            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                Update
            </button>
            <a href="{{ route('submissions.show', $submission) }}" class="ml-4 text-gray-600">Cancel</a>
        </form>
    </div>
</div>
@endsection