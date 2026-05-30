@extends('layouts.app')

@section('title', $task->title)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-3 gap-8">
        <!-- Left: Assignment Details -->
        <div class="col-span-2">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h1 class="text-3xl font-bold text-gray-900">{{ $task->title }}</h1>
                <p class="text-gray-600 mt-2">{{ $task->type }}</p>

                <!-- Status -->
                <div class="flex gap-4 mt-4">
                    @php
                        $mySubmission = $task->submissions->where('user_id', auth()->id())->first();
                    @endphp

                    @if($mySubmission)
                        <span class="px-3 py-1 rounded-full text-sm font-semibold
                            {{ $mySubmission->status === 'submitted' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $mySubmission->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                        ">
                            {{ $mySubmission->is_late ? 'Late' : ucfirst($mySubmission->status) }}
                        </span>

                        @if($mySubmission->grade)
                            <span class="text-lg font-bold text-gray-900">
                                Grade: {{ $mySubmission->grade->score }}/{{ $task->max_score }}
                            </span>
                        @endif
                    @else
                        <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                            Not Submitted
                        </span>
                    @endif
                </div>
            </div>

            <!-- Description -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">About This Assignment</h2>
                <p class="text-gray-700 whitespace-pre-wrap mb-6">{{ $task->description }}</p>

                <div class="bg-blue-50 border-l-4 border-blue-500 p-4">
                    <h3 class="font-bold text-gray-900 mb-2">Instructions</h3>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        {!! nl2br(e($task->instructions)) !!}
                    </div>
                </div>
            </div>

            <!-- Supporting Materials -->
            @if($task->attachments && $task->attachments->count() > 0)
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">📎 Supporting Materials</h2>
                <div class="space-y-2">
                    @foreach($task->attachments as $attachment)
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                        <div>
                            <p class="font-semibold text-gray-900">{{ $attachment->file_name }}</p>
                            <p class="text-xs text-gray-600">{{ ucfirst($attachment->type) }}</p>
                        </div>
                         <a href="{{ route('submission-tasks.attachment.download', [$course, $task, $attachment]) }}"
                            class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 transition">
                            Download
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Previous Submissions -->
            @if($mySubmission && $mySubmission->files && $mySubmission->files->count() > 0)
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">📤 Your Submissions</h2>
                
                @php
                    $submissions = $task->submissions->where('user_id', auth()->id());
                @endphp

                <div class="space-y-4">
                    @foreach($submissions as $sub)
                    <div class="border rounded-lg p-4 {{ $sub->status === 'submitted' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200' }}">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-semibold text-gray-900">
                                    Submission {{ $loop->index + 1 }}
                                    <span class="text-sm font-normal text-gray-600 ml-2">
                                        {{ $sub->submitted_at ? '✓ Submitted ' . $sub->submitted_at->diffForHumans() : 'Draft' }}
                                    </span>
                                </p>
                                @if($sub->is_late)
                                    <p class="text-sm text-red-600 font-semibold">⚠ Submitted Late</p>
                                @endif
                            </div>
                            @if($sub->grade)
                                <span class="text-xl font-bold text-gray-900">
                                    {{ $sub->grade->score }}/{{ $task->max_score }}
                                </span>
                            @endif
                        </div>

                        <!-- Files -->
                        @if($sub->files && $sub->files->count() > 0)
                        <div class="bg-gray-50 rounded p-3 mb-3">
                            <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Files</p>
                            <div class="space-y-1">
                                @foreach($sub->files as $file)
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-900">{{ $file->original_name }}</span>
                                    <a href="{{ route('submissions.downloadFile', $file) }}"
                                       class="text-blue-600 hover:underline">Download</a>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Comments -->
                        @if($sub->comments && $sub->comments->count() > 0)
                        <div class="bg-white rounded p-3">
                            <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Comments</p>
                            <div class="space-y-2">
                                @foreach($sub->comments as $comment)
                                <div class="text-sm border-l-2 border-gray-300 pl-3">
                                    <p class="font-semibold text-gray-900">{{ $comment->user->name }}</p>
                                    <p class="text-gray-700">{{ $comment->comment }}</p>
                                    <p class="text-xs text-gray-500">{{ $comment->created_at->format('M d, H:i') }}</p>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <!-- Right: Sidebar -->
        <div>
            <!-- Key Dates -->
            <div class="bg-blue-50 rounded-lg p-6 mb-6 border border-blue-200">
                <h3 class="font-bold text-gray-900 mb-4">📅 Important Dates</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Opens</p>
                        <p class="font-semibold text-gray-900">{{ $task->open_at?->format('M d, Y H:i') ?? 'Not set' }}</p>
                        @if(now() < $task->open_at)
                            <p class="text-xs text-orange-600 mt-1">Opens in {{ $task->open_at?->diffForHumans() ?? '' }}</p>
                        @endif
                    </li>
                    <li class="pt-3 border-t">
                        <p class="text-gray-600">Due (Soft)</p>
                        <p class="font-semibold text-gray-900">{{ $task->due_date?->format('M d, Y H:i') ?? 'Not set' }}</p>
                        @if(now() < $task->due_date)
                            <p class="text-xs text-green-600 mt-1 font-semibold">Due in {{ $task->due_date?->diffForHumans() ?? '' }}</p>
                        @elseif(now() < $task->late_deadline)
                            <p class="text-xs text-orange-600 mt-1 font-semibold">⚠ Overdue - late submissions allowed</p>
                        @else
                            <p class="text-xs text-red-600 mt-1 font-semibold">❌ Closed</p>
                        @endif
                    </li>
                    @if($task->allow_late_submissions)
                    <li class="pt-3 border-t">
                        <p class="text-gray-600">Hard Deadline</p>
                        <p class="font-semibold text-gray-900">{{ $task->late_deadline?->format('M d, Y H:i') ?? 'Not set' }}</p>
                        @if(now() < $task->late_deadline)
                            <p class="text-xs text-orange-600 mt-1">Last chance: {{ $task->late_deadline?->diffForHumans() ?? '' }}</p>
                        @endif
                    </li>
                    @endif
                </ul>
            </div>

            <!-- File Requirements -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="font-bold text-gray-900 mb-4">📋 Requirements</h3>
                <ul class="space-y-3 text-sm">
                    <li class="flex justify-between">
                        <span class="text-gray-600">File Count:</span>
                        <span class="font-semibold">{{ $task->min_file_count }}-{{ $task->max_file_count }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Max Size:</span>
                        <span class="font-semibold">{{ $task->max_file_size_mb }}MB</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-600">Formats:</span>
                        <span class="font-semibold text-right">{{ implode(', ', array_map('strtoupper', $task->allowed_file_types ?? [])) }}</span>
                    </li>
                </ul>
            </div>

            <!-- Submission Policy -->
            <div class="bg-yellow-50 rounded-lg p-6 mb-6 border border-yellow-200">
                <h3 class="font-bold text-gray-900 mb-4">⚙️ Submission Rules</h3>
                <ul class="space-y-2 text-sm text-gray-700">
                    <li class="flex items-start gap-2">
                        <span class="font-bold text-yellow-600 mt-1">•</span>
                        <span>
                            <strong>Resubmissions:</strong> 
                            {{ $task->max_resubmissions ?? 'Unlimited' }}
                        </span>
                    </li>
                    @if($task->late_submission_penalty_percent > 0)
                    <li class="flex items-start gap-2">
                        <span class="font-bold text-yellow-600 mt-1">•</span>
                        <span>
                            <strong>Late Penalty:</strong> 
                            {{ $task->late_submission_penalty_percent }}% off grade
                        </span>
                    </li>
                    @endif
                    @if($task->allow_group_submissions)
                    <li class="flex items-start gap-2">
                        <span class="font-bold text-yellow-600 mt-1">•</span>
                        <span>
                            <strong>Group:</strong> 
                            {{ $task->min_group_size }}-{{ $task->max_group_size }} members
                        </span>
                    </li>
                    @endif
                </ul>
            </div>

            <!-- Max Score -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <p class="text-gray-600 text-sm mb-1">Total Points</p>
                <p class="text-4xl font-bold text-blue-600">{{ $task->max_score }}</p>
            </div>

            <!-- Submit Button -->
            @if(now() >= $task->open_at && now() <= $task->late_deadline)
                <div class="bg-white rounded-lg shadow p-6">
                    @php
                        $canResubmit = !$mySubmission || ($task->max_resubmissions === null || $mySubmission->resubmission_count < $task->max_resubmissions);
                    @endphp

                    @if($canResubmit)
                        <a href="{{ route('submissions.create') }}?task_id={{ $task->id }}" 
                           class="block w-full px-4 py-3 bg-green-600 text-white text-center rounded-lg hover:bg-green-700 transition font-bold text-lg mb-3">
                            {{ $mySubmission ? '🔄 Resubmit' : '📤 Submit Assignment' }}
                        </a>
                    @else
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-red-700 font-semibold text-center">❌ Max resubmissions reached</p>
                        </div>
                    @endif

                    @if($mySubmission && $mySubmission->status === 'draft')
                        <form action="{{ route('submissions.submit', $mySubmission) }}" method="POST">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-3 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition font-semibold"
                                    onclick="return confirm('Submit this assignment? You cannot edit it after submission.')">
                                ✓ Submit Draft
                            </button>
                        </form>
                    @endif
                </div>
            @elseif(now() < $task->open_at)
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg text-center">
                        <p class="text-gray-700 font-semibold">🔒 Not yet open</p>
                        <p class="text-gray-600 text-sm mt-2">Opens {{ $task->open_at->diffForHumans() }}</p>
                    </div>
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-center">
                        <p class="text-red-700 font-semibold">❌ Closed</p>
                        <p class="text-red-600 text-sm mt-2">No more submissions allowed</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
