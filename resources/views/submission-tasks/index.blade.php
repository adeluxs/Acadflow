@extends('layouts.app')

@section('title', 'Assignment Management')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Assignment Management</h1>
            <p class="text-gray-600 mt-2">Course: {{ $course->name }}</p>
        </div>
        <a href="{{ route('submission-tasks.create', $course) }}" 
           class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            + Create Assignment
        </a>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex gap-3">
        <select class="px-4 py-2 border rounded-lg text-sm" onchange="filterStatus(this.value)">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="closed">Closed</option>
            <option value="archived">Archived</option>
        </select>
    </div>

    <!-- Assignments Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Title</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Deadline</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Submissions</th>
                    <th class="px-6 py-3 text-left text-sm font-semibold text-gray-900">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ $task->title }}</h3>
                            <p class="text-sm text-gray-600">{{ \Str::limit($task->description, 60) }}</p>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            {{ $task->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $task->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $task->status === 'closed' ? 'bg-red-100 text-red-800' : '' }}
                            {{ $task->status === 'archived' ? 'bg-gray-100 text-gray-800' : '' }}
                        ">
                            {{ ucfirst($task->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        @if($task->due_date)
                            {{ $task->due_date->format('M d, Y H:i') }}
                            @if($task->due_date < now())
                                <span class="ml-2 text-red-600">Overdue</span>
                            @endif
                        @else
                            <span class="text-gray-400">No deadline set</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        {{ $task->submissions_count ?? 0 }} submissions
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex gap-2">
                            <a href="{{ route('submission-tasks.lecturer.show', [$course, $task]) }}" 
                               class="text-blue-600 hover:underline text-sm">View</a>
                            
                            @if($task->status === 'draft')
                                <a href="{{ route('submission-tasks.edit', [$course, $task]) }}" 
                                   class="text-blue-600 hover:underline text-sm">Edit</a>
                                
                                <form action="{{ route('submission-tasks.publish', [$course, $task]) }}" 
                                      method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:underline text-sm"
                                            onclick="return confirm('Publish this assignment?')">
                                        Publish
                                    </button>
                                </form>
                            @endif
                            
                            @if($task->status === 'published')
                                <form action="{{ route('submission-tasks.close', [$course, $task]) }}" 
                                      method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-orange-600 hover:underline text-sm"
                                            onclick="return confirm('Close this assignment?')">
                                        Close
                                    </button>
                                </form>
                            @endif
                            
                            @if($task->status === 'draft')
                                <form action="{{ route('submission-tasks.destroy', [$course, $task]) }}" 
                                      method="POST" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline text-sm"
                                            onclick="return confirm('Delete this assignment?')">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                        No assignments yet. 
                        <a href="{{ route('submission-tasks.create', $course) }}" class="text-blue-600 hover:underline">
                            Create one
                        </a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Legend -->
    <div class="mt-8 grid grid-cols-4 gap-4 text-sm">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 bg-yellow-200 rounded"></span>
            <span>Draft - Not visible to students</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 bg-green-200 rounded"></span>
            <span>Published - Students can submit</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 bg-red-200 rounded"></span>
            <span>Closed - No more submissions</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 bg-gray-200 rounded"></span>
            <span>Archived - Completed</span>
        </div>
    </div>
</div>

<script>
function filterStatus(status) {
    const url = new URL(window.location);
    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    window.location = url;
}
</script>
@endsection
