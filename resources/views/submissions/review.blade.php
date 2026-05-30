@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white shadow sm:rounded-lg">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $submission->title }}</h1>
                        <p class="text-gray-600 mt-1">
                            By {{ $submission->user->first_name }} {{ $submission->user->last_name }} | 
                            {{ $submission->course->code }} - {{ $submission->course->name }}
                        </p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold 
                        @if($submission->status === 'submitted') bg-yellow-100 text-yellow-800
                        @elseif($submission->status === 'graded') bg-green-100 text-green-800
                        @elseif($submission->status === 'under_review') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                    </span>
                </div>
            </div>

            <!-- Submission Details -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Submitted</p>
                        <p class="text-gray-900 font-medium">{{ $submission->submitted_at?->format('M d, Y H:i') ?? 'Not submitted' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Type</p>
                        <p class="text-gray-900 font-medium">{{ ucfirst($submission->type) }}</p>
                    </div>
                </div>
            </div>

            <!-- Submission Content -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Submission Files</h2>
                @if($submission->versions->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($submission->versions as $version)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $version->file_name }}</p>
                                    <p class="text-sm text-gray-600">v{{ $version->version_number }} • {{ number_format($version->file_size / 1024, 2) }} KB • {{ $version->created_at?->format('M d, Y H:i') ?? 'Unknown' }}</p>
                                </div>
                                <a href="{{ route('submissions.download', ['submission' => $submission, 'version' => $version->id]) }}" 
                                   class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 text-sm">
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500">No files uploaded.</p>
                @endif
            </div>

            <!-- Grading Section -->
            @if(!$submission->grade)
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Grade Submission</h2>
                    <form action="{{ route('submissions.grade', $submission) }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label for="score" class="block text-sm font-medium text-gray-700">Score (0-100)</label>
                            <input type="number" name="score" id="score" min="0" max="100" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label for="feedback" class="block text-sm font-medium text-gray-700">Feedback</label>
                            <textarea name="feedback" id="feedback" rows="4"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            Submit Grade
                        </button>
                    </form>
                </div>
            @else
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Grade</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Score</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $submission->grade->score }}/100</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Feedback</p>
                            <p class="text-gray-900">{{ $submission->grade->feedback ?? 'No feedback provided' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Comments Section -->
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Comments</h2>
                
                <div class="space-y-4 mb-6">
                    @forelse($submission->comments as $comment)
                        <div class="p-3 bg-gray-50 rounded">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $comment->user->first_name }} {{ $comment->user->last_name }}</p>
                                    <p class="text-sm text-gray-600">{{ $comment->created_at->format('M d, Y H:i') }}</p>
                                </div>
                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded">{{ ucfirst($comment->type) }}</span>
                            </div>
                            <p class="text-gray-700 mt-2">{{ $comment->content }}</p>
                        </div>
                    @empty
                        <p class="text-gray-500">No comments yet.</p>
                    @endforelse
                </div>

                <!-- Add Comment Form -->
                <form action="{{ route('submissions.comment', $submission) }}" method="POST">
                    @csrf
                    <textarea name="comment" rows="3" placeholder="Add a comment..." required
                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <button type="submit" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Add Comment
                    </button>
                </form>
            </div>

            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Compare Versions</h2>
                <form action="{{ route('submissions.compare', $submission) }}" method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Version A</label>
                            <select name="version_a" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select version</option>
                                @foreach($submission->versions as $version)
                                    <option value="{{ $version->id }}">v{{ $version->version_number }} — {{ $version->file_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Version B</label>
                            <select name="version_b" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select version</option>
                                @foreach($submission->versions as $version)
                                    <option value="{{ $version->id }}">v{{ $version->version_number }} — {{ $version->file_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Compare Versions
                    </button>
                </form>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
                <form action="{{ route('submissions.approve', $submission) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Approve Submission
                    </button>
                </form>
                <form action="{{ route('submissions.request-correction', $submission) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded hover:bg-orange-700">
                        Request Correction
                    </button>
                </form>
                <form action="{{ route('submissions.reject', $submission) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Reject Submission
                    </button>
                </form>
                <a href="{{ route('submissions.lecturer-index') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
