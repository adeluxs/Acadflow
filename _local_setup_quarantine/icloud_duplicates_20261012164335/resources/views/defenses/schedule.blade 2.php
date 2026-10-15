@extends('layouts.app')

@section('title', 'Schedule Defense')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Schedule Defense</h1>
        <p class="text-gray-600 mt-2">{{ $submission->title }} ({{ ucfirst($submission->type) }})</p>
    </div>

    @if($existingDefense)
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <h3 class="font-bold text-blue-900 mb-2">Existing Defense Scheduled</h3>
            <p class="text-blue-800">
                <strong>Date & Time:</strong> {{ $existingDefense->scheduled_at->format('F d, Y H:i') }}<br>
                <strong>Venue:</strong> {{ $existingDefense->venue ?? 'Not specified' }}<br>
                <strong>Duration:</strong> {{ $existingDefense->duration_minutes }} minutes<br>
                <strong>Status:</strong> {{ ucfirst($existingDefense->status) }}
            </p>
        </div>
    @endif

    <form action="{{ route('defenses.store', $submission) }}" method="POST" class="bg-white rounded-lg shadow p-8">
        @csrf

        <!-- Scheduled At -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Defense Date & Time *</label>
            <input type="datetime-local" name="scheduled_at" 
                   value="{{ old('scheduled_at', $existingDefense ? $existingDefense->scheduled_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   required>
            @error('scheduled_at')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Duration -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Duration (minutes) *</label>
            <input type="number" name="duration_minutes" 
                   value="{{ old('duration_minutes', $existingDefense->duration_minutes ?? 30) }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   min="15" max="180" required>
            <p class="text-xs text-gray-500 mt-1">15-180 minutes</p>
            @error('duration_minutes')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Venue -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Venue</label>
            <input type="text" name="venue" 
                   value="{{ old('venue', $existingDefense->venue ?? '') }}"
                   class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                   placeholder="e.g., Room 101, Main Building">
            @error('venue')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Notes -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
            <textarea name="notes" rows="4" 
                      class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Any additional notes for the defense...">{{ old('notes', $existingDefense->notes ?? '') }}</textarea>
            @error('notes')
                <span class="text-red-600 text-sm mt-1">{{ $message }}</span>
            @enderror
        </div>

        <!-- Buttons -->
        <div class="flex gap-3 pt-6 border-t">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold">
                {{ $existingDefense ? 'Update Defense' : 'Schedule Defense' }}
            </button>

            <a href="{{ route('submissions.show', $submission) }}" 
               class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
