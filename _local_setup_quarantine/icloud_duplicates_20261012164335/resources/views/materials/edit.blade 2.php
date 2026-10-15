@extends('layouts.app')

@section('title', 'Edit Material')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Edit Material</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('lecturer.materials.update', [$course, $material]) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Title *</label>
                <input type="text" name="title" value="{{ old('title', $material->title) }}" 
                       class="w-full px-3 py-2 border rounded @error('title') border-red-500 @enderror"
                       required maxlength="255">
                @error('title')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description" rows="4" 
                          class="w-full px-3 py-2 border rounded">{{ old('description', $material->description) }}</textarea>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Type *</label>
                <select name="type" class="w-full px-3 py-2 border rounded" required>
                    @foreach(['lecture_note', 'slides', 'reading', 'video', 'assignment', 'exam', 'reference', 'other'] as $type)
                        <option value="{{ $type }}" {{ $material->type === $type ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Topic</label>
                <input type="text" name="topic" value="{{ old('topic', $material->topic) }}" 
                       class="w-full px-3 py-2 border rounded" placeholder="e.g., Introduction, Chapter 1">
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Week Number</label>
                    <input type="number" name="week_number" value="{{ old('week_number', $material->week_number) }}" 
                           min="1" max="20" class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Sequence Order</label>
                    <input type="number" name="sequence_order" value="{{ old('sequence_order', $material->sequence_order) }}" 
                           min="0" class="w-full px-3 py-2 border rounded">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Visibility</label>
                <div class="flex gap-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_public" 
                               {{ old('is_public', $material->is_public) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <span class="ml-2">Public (accessible without enrollment)</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="requires_enrollment" 
                               {{ old('requires_enrollment', $material->requires_enrollment) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <span class="ml-2">Requires Enrollment</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_visible" 
                               {{ old('is_visible', $material->is_visible) ? 'checked' : '' }}
                               class="rounded border-gray-300">
                        <span class="ml-2">Visible to Students</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Update Material
                </button>
                <a href="{{ route('materials.show', [$course, $material]) }}" 
                   class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>

        <hr class="my-6">

        <form method="POST" action="{{ route('lecturer.materials.destroy', [$course, $material]) }}" 
              onsubmit="return confirm('Are you sure? This will permanently delete the material file.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                Delete Material
            </button>
        </form>
    </div>
</div>
@endsection
