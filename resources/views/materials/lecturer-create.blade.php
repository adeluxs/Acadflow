@extends('layouts.app')

@section('title', 'Upload Material')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Upload Course Material</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('lecturer.materials.store', $course) }}" enctype="multipart/form-data">
            @csrf

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Title *</label>
                <input type="text" name="title" value="{{ old('title') }}" 
                       class="w-full px-3 py-2 border rounded @error('title') border-red-500 @enderror"
                       required maxlength="255">
                @error('title')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" rows="3" 
                          class="w-full px-3 py-2 border rounded">{{ old('description') }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">File *</label>
                <input type="file" name="file" 
                       class="w-full px-3 py-2 border rounded @error('file') border-red-500 @enderror"
                       required>
                @error('file')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
                <p class="text-sm text-gray-500 mt-1">Maximum file size: 50MB. Supported: PDF, DOC, DOCX, images, etc.</p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Material Type *</label>
                <select name="type" class="w-full px-3 py-2 border rounded" required>
                    <option value="">Select type</option>
                    <option value="lecture_note" {{ old('type') === 'lecture_note' ? 'selected' : '' }}>Lecture Notes</option>
                    <option value="slides" {{ old('type') === 'slides' ? 'selected' : '' }}>Presentation Slides</option>
                    <option value="reading" {{ old('type') === 'reading' ? 'selected' : '' }}>Reading Material</option>
                    <option value="video" {{ old('type') === 'video' ? 'selected' : '' }}>Video</option>
                    <option value="assignment" {{ old('type') === 'assignment' ? 'selected' : '' }}>Assignment</option>
                    <option value="exam" {{ old('type') === 'exam' ? 'selected' : '' }}>Quiz</option>
                    <option value="reference" {{ old('type') === 'reference' ? 'selected' : '' }}>Reference</option>
                    <option value="other" {{ old('type') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Topic</label>
                    <input type="text" name="topic" value="{{ old('topic') }}" 
                           class="w-full px-3 py-2 border rounded" placeholder="e.g., Introduction">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Week Number</label>
                    <input type="number" name="week_number" value="{{ old('week_number') }}" 
                           min="1" max="20" class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Sort Order (optional)</label>
                <input type="number" name="sequence_order" value="{{ old('sequence_order', 0) }}" 
                       min="0" class="w-full px-3 py-2 border rounded">
                <p class="text-sm text-gray-500">Materials with lower numbers appear first.</p>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Visibility</label>
                <div class="space-y-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_public" {{ old('is_public') ? 'checked' : '' }} 
                               class="rounded border-gray-300">
                        <span class="ml-2">Make publicly accessible (not enrolled students can view)</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="requires_enrollment" {{ old('requires_enrollment', true) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <span class="ml-2">Require enrollment to access</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_visible" {{ old('is_visible', true) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <span class="ml-2">Visible to students</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Upload Material
                </button>
                <a href="{{ route('materials.index', $course) }}" 
                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
