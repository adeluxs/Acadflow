@extends('layouts.app')

@section('title', $group->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Group Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $group->name }}</h1>
                    <p class="text-gray-600 mt-1">{{ $group->course->code }} - {{ $group->course->name }}</p>
                </div>
                <div class="text-right">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold 
                        @if($group->status === 'complete') bg-green-100 text-green-800
                        @elseif($group->status === 'forming') bg-yellow-100 text-yellow-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($group->status) }}
                    </span>
                    @if($group->leader_id === auth()->id())
                        <div class="mt-2">
                            <a href="{{ route('groups.edit', $group) }}" class="text-blue-600 hover:underline text-sm">
                                Edit Group
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <p class="text-gray-700 mb-4">{{ $group->description }}</p>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <p class="text-gray-600">Leader</p>
                    <p class="font-medium">{{ $group->leader->first_name }} {{ $group->leader->last_name }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Members</p>
                    <p class="font-medium">{{ $group->members->count() }} / {{ $group->max_members }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Created</p>
                    <p class="font-medium">{{ $group->formed_at->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600">Submissions</p>
                    <p class="font-medium">{{ $group->submissions->count() }}</p>
                </div>
            </div>
        </div>

        <!-- Members Section -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Group Members</h2>
                @if($group->status === 'forming' && $group->members->count() < $group->max_members && !$group->members->contains('user_id', auth()->id()))
                    <form method="POST" action="{{ route('groups.join', $group) }}">
                        @csrf
                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            Join Group
                        </button>
                    </form>
                @endif
            </div>

            <div class="space-y-3">
                @foreach($group->members as $member)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                        <div class="flex items-center">
                            <div>
                                <p class="font-medium">{{ $member->user->first_name }} {{ $member->user->last_name }}</p>
                                <p class="text-sm text-gray-600">{{ ucfirst($member->role) }}</p>
                            </div>
                        </div>
                        @if($group->leader_id === auth()->id() && $member->user_id !== auth()->id())
                            <form method="POST" action="{{ route('groups.remove-member', $group) }}" 
                                  onsubmit="return confirm('Are you sure you want to remove this member?')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="user_id" value="{{ $member->user_id }}">
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    Remove
                                </button>
                            </form>
                        @elseif($member->user_id === auth()->id() && $member->role !== 'leader')
                            <form method="POST" action="{{ route('groups.leave', $group) }}" 
                                  onsubmit="return confirm('Are you sure you want to leave this group?')">
                                @csrf
                                <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                    Leave Group
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            @if($group->leader_id === auth()->id() && $group->members->count() > 1)
                <div class="mt-6 pt-4 border-t">
                    <h3 class="text-lg font-medium mb-3">Transfer Leadership</h3>
                    <form method="POST" action="{{ route('groups.transfer-leadership', $group) }}">
                        @csrf
                        <div class="flex gap-3">
                            <select name="new_leader_id" required class="flex-1 px-3 py-2 border border-gray-300 rounded-md">
                                <option value="">Select new leader</option>
                                @foreach($group->members->where('user_id', '!=', auth()->id()) as $member)
                                    <option value="{{ $member->user_id }}">{{ $member->user->first_name }} {{ $member->user->last_name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                                Transfer
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <!-- Submissions Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Group Submissions</h2>

            @if($group->submissions->isEmpty())
                <p class="text-gray-600">No submissions yet.</p>
            @else
                <div class="space-y-3">
                    @foreach($group->submissions as $submission)
                        <div class="p-4 border rounded">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="font-medium">{{ $submission->title }}</h3>
                                    <p class="text-sm text-gray-600">{{ $submission->type }} • {{ $submission->status }}</p>
                                </div>
                                <a href="{{ route('submissions.show', $submission) }}" class="text-blue-600 hover:underline">
                                    View
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Actions -->
        @if($group->leader_id === auth()->id())
            <div class="mt-6 flex gap-3">
                <form method="POST" action="{{ route('groups.destroy', $group) }}" 
                      onsubmit="return confirm('Are you sure you want to delete this group? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        Delete Group
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
