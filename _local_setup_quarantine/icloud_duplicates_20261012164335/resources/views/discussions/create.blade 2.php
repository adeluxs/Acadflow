@extends('layouts.app')

@section('title', 'Create Discussion')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <h1 class="text-2xl font-bold mb-6">Create New Discussion</h1>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('discussions.store', $course) }}"
              data-offline="true" data-action-type="discussion_create">
            @csrf

            @if($material)
                <input type="hidden" name="material_id" value="{{ $material->id }}">
                <div class="mb-4 p-3 bg-blue-50 rounded">
                    <p class="text-sm text-blue-800">
                        Question about: <strong>{{ $material->title }}</strong>
                    </p>
                </div>
            @endif

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
                <label class="block text-gray-700 text-sm font-bold mb-2">Description *</label>
                <textarea name="content" rows="6" 
                          class="w-full px-3 py-2 border rounded @error('content') border-red-500 @enderror"
                          required>{{ old('content') }}</textarea>
                @error('content')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Priority</label>
                <select name="priority" class="w-full px-3 py-2 border rounded">
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="normal" {{ old('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                </select>
            </div>

            @if($tags->count() > 0)
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Tags</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $tag)
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" 
                                       {{ in_array($tag->id, old('tag_ids', [])) ? 'checked' : '' }}
                                       class="rounded border-gray-300">
                                <span class="ml-2 text-sm">{{ $tag->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Create Discussion
                </button>
                <a href="{{ route('discussions.index', $course) }}" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
