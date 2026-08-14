@extends('layouts.app')

@section('title', 'Course Discussions')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">Discussions</h1>
            <p class="text-gray-600">{{ $course->code }} - {{ $course->name }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('courses.index') }}" class="text-indigo-600 hover:underline">← Back to Courses</a>
            <a href="{{ route('discussions.create', $course) }}" class="ml-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                + New Discussion
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <form method="GET" action="" class="flex gap-4 items-center">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search discussions..." 
                       class="w-full px-3 py-2 border rounded">
            </div>
            <div>
                <select name="tag" class="px-3 py-2 border rounded">
                    <option value="">All Tags</option>
                    @foreach($tags as $tag)
                        <option value="{{ $tag->name }}" {{ request('tag') == $tag->name ? 'selected' : '' }}>
                            {{ $tag->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">Filter</button>
        </form>
    </div>

    @if($discussions->count() > 0)
        <div class="space-y-4">
            @foreach($discussions as $discussion)
                <div class="bg-white rounded-lg shadow p-6 {{ $discussion->is_pinned ? 'border-l-4 border-yellow-500' : '' }}">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                @if($discussion->is_pinned)
                                    <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded">Pinned</span>
                                @endif
                                @if($discussion->status === 'resolved')
                                    <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded">Resolved</span>
                                @endif
                                @if($discussion->priority === 'high')
                                    <span class="px-2 py-0.5 bg-red-100 text-red-800 text-xs rounded">High Priority</span>
                                @endif
                                @foreach($discussion->tags as $tag)
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded">
                                        {{ $tag->name }}
                                    </span>
                                @endforeach
                            </div>
                            <h3 class="text-lg font-bold">
                                <a href="{{ route('discussions.show', [$course, $discussion]) }}" 
                                   class="text-indigo-600 hover:underline">
                                    {{ $discussion->title }}
                                </a>
                            </h3>
                            <p class="text-gray-600 text-sm mt-1">
                                by {{ $discussion->user->full_name }} • 
                                {{ $discussion->created_at->format('M d, Y H:i') }} •
                                {{ $discussion->engagementThread?->comments_count ?? 0 }} replies
                            </p>
                            @if($discussion->material)
                                <p class="text-sm text-gray-500 mt-1">
                                    Re: <a href="{{ route('materials.show', [$course, $discussion->material]) }}" 
                                           class="text-blue-600 hover:underline">{{ $discussion->material->title }}</a>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">
            {{ $discussions->links() }}
        </div>
    @else
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500">No discussions found. Start a discussion!</p>
        </div>
    @endif
</div>
@endsection
