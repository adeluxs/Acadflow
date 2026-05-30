@extends('layouts.app')

@section('title', $material->title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-start mb-6">
        <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
                <a href="{{ route('materials.index', $course) }}" class="text-indigo-600 hover:underline">
                    ← Back to Materials
                </a>
            </div>
            <h1 class="text-2xl font-bold">{{ $material->title }}</h1>
            <p class="text-gray-600">{{ $course->name }}</p>
            <div class="flex gap-2 mt-2">
                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                    {{ ucfirst(str_replace('_', ' ', $material->type)) }}
                </span>
                @if($material->topic)
                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-sm">
                        {{ $material->topic }}
                    </span>
                @endif
                @if($material->week_number)
                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-sm">
                        Week {{ $material->week_number }}
                    </span>
                @endif
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('materials.download', [$course, $material]) }}" 
               class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
               Download
            </a>
            @if(auth()->user()->isLecturer() || auth()->user()->isAdmin())
                <a href="{{ route('lecturer.materials.edit', [$course, $material]) }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                   Edit
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                @if($material->description)
                    <h2 class="text-lg font-bold mb-3">Description</h2>
                    <p class="text-gray-700 mb-6">{{ $material->description }}</p>
                @endif

                <div class="border-t pt-6">
                    <h2 class="text-lg font-bold mb-3">File Information</h2>
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-gray-500 text-sm">File Name</dt>
                            <dd class="font-medium">{{ $material->file_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-sm">File Size</dt>
                            <dd class="font-medium">{{ number_format($material->file_size / 1024, 2) }} KB</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-sm">MIME Type</dt>
                            <dd class="font-medium">{{ $material->mime_type }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-sm">Uploaded By</dt>
                            <dd class="font-medium">{{ $material->uploader->full_name ?? 'Unknown' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-sm">Uploaded At</dt>
                            <dd class="font-medium">{{ $material->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 text-sm">Downloads</dt>
                            <dd class="font-medium">{{ $material->download_count }}</dd>
                        </div>
                    </dl>
                </div>

                @if($material->is_public)
                    <div class="mt-6 p-4 bg-green-50 rounded">
                        <span class="text-green-800">✓ This material is publicly accessible</span>
                    </div>
                @endif
            </div>

            <!-- Q&A Section -->
            <div class="bg-white rounded-lg shadow p-6 mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold">Questions & Discussion</h2>
                    <a href="{{ route('discussions.create', ['course' => $course, 'material_id' => $material->id]) }}" 
                       class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                        Ask Question
                    </a>
                </div>
                
                @if($discussions && $discussions->count() > 0)
                    <div class="space-y-4">
                        @foreach($discussions as $discussion)
                            <div class="border-b pb-4">
                                <h4 class="font-semibold">
                                    <a href="{{ route('discussions.show', [$course, $discussion]) }}" 
                                       class="text-indigo-600 hover:underline">
                                        {{ $discussion->title }}
                                    </a>
                                </h4>
                                <p class="text-sm text-gray-600 mt-1">
                                    by {{ $discussion->user->full_name }} • {{ $discussion->created_at->format('M d, Y') }}
                                    @if($discussion->is_pinned)
                                        <span class="px-2 py-0.5 bg-yellow-100 text-yellow-800 text-xs rounded ml-1">Pinned</span>
                                    @endif
                                    @if($discussion->status === 'resolved')
                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded ml-1">Resolved</span>
                                    @endif
                                </p>
                                <p class="text-sm text-gray-700 mt-1">
                                    {{ \Str::limit($discussion->content, 150) }}
                                </p>
                                <div class="flex gap-2 mt-2">
                                    @foreach($discussion->tags as $tag)
                                        <span class="px-2 py-0.5 bg-gray-100 text-gray-700 text-xs rounded">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    {{ $discussions->links() }}
                @else
                    <p class="text-gray-500">No questions yet. Be the first to ask!</p>
                @endif
            </div>
        </div>

        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                <h3 class="font-bold mb-4">Material Details</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-gray-500 text-sm">Type</dt>
                        <dd>{{ ucfirst(str_replace('_', ' ', $material->type)) }}</dd>
                    </div>
                    @if($material->topic)
                        <div>
                            <dt class="text-gray-500 text-sm">Topic</dt>
                            <dd>{{ $material->topic }}</dd>
                        </div>
                    @endif
                    @if($material->week_number)
                        <div>
                            <dt class="text-gray-500 text-sm">Week</dt>
                            <dd>{{ $material->week_number }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 text-sm">Visibility</dt>
                        <dd>
                            @if($material->is_public)
                                <span class="text-green-600">Public</span>
                            @elseif($material->requires_enrollment)
                                <span class="text-blue-600">Enrolled Only</span>
                            @else
                                <span class="text-gray-600">Restricted</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-6">
                    <h3 class="font-bold mb-3">Access Log</h3>
                    @if($material->accessLogs->count() > 0)
                        <div class="space-y-2 text-sm">
                            @foreach($material->accessLogs->take(5) as $log)
                                <div class="flex justify-between">
                                    <span>{{ $log->user->full_name }}</span>
                                    <span class="text-gray-500">{{ $log->action }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            {{ $material->accessLogs->count() }} total accesses
                        </p>
                    @else
                        <p class="text-sm text-gray-500">No access logs yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
