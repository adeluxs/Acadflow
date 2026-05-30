@extends('layouts.app')

@section('title', 'Discussion: ' . $discussion->title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-4">
        <a href="{{ route('discussions.index', $course) }}" class="text-indigo-600 hover:underline">
            ← Back to Discussions
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start mb-4">
            <div>
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
                </div>
                <h1 class="text-2xl font-bold">{{ $discussion->title }}</h1>
                <p class="text-gray-600 text-sm mt-1">
                    Asked by {{ $discussion->user->full_name }} on {{ $discussion->created_at->format('M d, Y H:i') }}
                    @if($discussion->resolved_at)
                        • Resolved by {{ $discussion->resolver->full_name }} on {{ $discussion->resolved_at->format('M d, Y') }}
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                @if(auth()->id() === $discussion->user_id && $discussion->status === 'open')
                    <a href="{{ route('discussions.edit', [$course, $discussion]) }}" 
                       class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                       Edit
                    </a>
                @endif
                @if(auth()->user()->isLecturer() || auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('discussions.pin', [$course, $discussion]) }}" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                            {{ $discussion->is_pinned ? 'Unpin' : 'Pin' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="prose max-w-none mb-8">
            {!! nl2br(e($discussion->content)) !!}
        </div>

        @if($discussion->tags->count() > 0)
            <div class="flex gap-2 mb-6">
                @foreach($discussion->tags as $tag)
                    <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-sm">
                        {{ $tag->name }}
                    </span>
                @endforeach
            </div>
        @endif

        @if($discussion->material)
            <div class="bg-gray-50 p-4 rounded mb-6">
                <p class="text-sm text-gray-600">
                    Related to: 
                    <a href="{{ route('materials.show', [$course, $discussion->material]) }}" 
                       class="text-indigo-600 hover:underline">
                       {{ $discussion->material->title }}
                    </a>
                </p>
            </div>
        @endif

        <hr class="my-6">

        <!-- Replies -->
        <h2 class="text-xl font-bold mb-4">Replies ({{ $discussion->replies->count() }})</h2>

        <div class="space-y-4">
            @forelse($discussion->replies as $reply)
                <div class="border rounded-lg p-4 {{ $reply->is_accepted ? 'bg-green-50 border-green-200' : '' }}">
                    <div class="flex items-start gap-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                                    {{ \Str::upper(substr($reply->user->first_name, 0, 1)) }}
                                </div>
                            </div>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold">{{ $reply->user->full_name }}</p>
                                <div class="flex items-center gap-2">
                                    @if($reply->is_accepted)
                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded">Accepted Answer</span>
                                    @endif
                                    <span class="text-sm text-gray-500">{{ $reply->created_at->format('M d, Y H:i') }}</span>
                                </div>
                            </div>
                            <div class="prose max-w-none mt-2">
                                {!! nl2br(e($reply->content)) !!}
                            </div>
                            @if($reply->type === 'answer' && !$discussion->resolved_at && auth()->user()->isLecturer())
                                <form method="POST" action="{{ route('discussions.reply', [$course, $discussion]) }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="parent_reply_id" value="{{ $reply->id }}">
                                    <input type="hidden" name="accept" value="1">
                                    <button type="submit" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                        Mark as Accepted
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="text-gray-500">No replies yet.</p>
            @endforelse
        </div>

        <!-- Add Reply Form -->
        @if($discussion->status === 'open' || auth()->user()->isAdmin())
            <div class="mt-6 bg-white rounded-lg shadow p-6">
                <h3 class="font-bold mb-4">Add Reply</h3>
                <form method="POST" action="{{ route('discussions.reply', [$course, $discussion]) }}"
                      data-offline="true" data-action-type="reply_create">
                    @csrf
                    <div class="mb-4">
                        <textarea name="content" rows="4" class="w-full px-3 py-2 border rounded" 
                                  placeholder="Write your reply..." required></textarea>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                            Post Reply
                        </button>
                        @if(auth()->user()->isLecturer())
                            <select name="type" class="px-3 py-2 border rounded">
                                <option value="answer">Answer</option>
                                <option value="comment">Comment</option>
                                <option value="clarification">Clarification</option>
                            </select>
                        @endif
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
