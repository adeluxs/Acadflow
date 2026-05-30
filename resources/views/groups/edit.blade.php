@extends('layouts.app')

@section('title', 'Edit Group')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-6">Edit Group</h1>

            <form method="POST" action="{{ route('groups.update', $group) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Group Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $group->name) }}" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @error('name')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="description" rows="4"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $group->description) }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="max_members" class="block text-sm font-medium text-gray-700 mb-2">Maximum Members</label>
                    <select name="max_members" id="max_members" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @for($i = 2; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ old('max_members', $group->max_members) == $i ? 'selected' : '' }}>{{ $i }} members</option>
                        @endfor
                    </select>
                    @error('max_members')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" id="status" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="forming" {{ old('status', $group->status) == 'forming' ? 'selected' : '' }}>Forming</option>
                        <option value="complete" {{ old('status', $group->status) == 'complete' ? 'selected' : '' }}>Complete</option>
                        <option value="archived" {{ old('status', $group->status) == 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                    @error('status')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                        Update Group
                    </button>
                    <a href="{{ route('groups.show', $group) }}" class="bg-gray-600 text-white px-6 py-2 rounded hover:bg-gray-700">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Current Members Info -->
        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <h2 class="text-lg font-semibold mb-4">Current Members ({{ $group->members->count() }}/{{ $group->max_members }})</h2>
            <div class="space-y-2">
                @foreach($group->members as $member)
                    <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                        <span>{{ $member->user->first_name }} {{ $member->user->last_name }}</span>
                        <span class="text-sm text-gray-600">{{ ucfirst($member->role) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
