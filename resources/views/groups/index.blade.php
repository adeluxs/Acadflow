@extends('layouts.app')

@section('title', 'My Groups')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">My Groups</h1>
        <a href="{{ route('groups.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Create Group
        </a>
    </div>

    @if($groups->isEmpty())
        <div class="bg-gray-100 rounded-lg p-8 text-center">
            <p class="text-gray-600">You haven't joined any groups yet.</p>
            <a href="{{ route('groups.create') }}" class="text-blue-600 hover:underline mt-2 inline-block">
                Create a group
            </a>
        </div>
    @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($groups as $group)
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-semibold">{{ $group->name }}</h3>
                        <span class="px-2 py-1 text-xs rounded-full 
                            {{ $group->status === 'complete' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($group->status) }}
                        </span>
                    </div>
                    
                    <p class="text-gray-600 text-sm mb-4">{{ $group->description }}</p>
                    
                    <div class="text-sm text-gray-500 mb-4">
                        <p>Course: {{ $group->course->code }}</p>
                        <p>Members: {{ $group->members->count() }}/{{ $group->max_members }}</p>
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('groups.show', $group) }}" class="text-blue-600 hover:underline text-sm">
                            View
                        </a>
                        @if($group->leader_id === auth()->id())
                            <a href="{{ route('groups.edit', $group) }}" class="text-gray-600 hover:underline text-sm">
                                Edit
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection