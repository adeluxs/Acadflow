@extends('layouts.app')

@section('title', 'Course Assignments')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Assignments</h1>
        <p class="text-gray-600 mt-2">{{ $course->name }}</p>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-6 flex gap-2 border-b">
        <a href="{{ route('courses.assignments', $course) }}" 
           class="px-4 py-3 border-b-2 {{ !request('status') ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
            All
        </a>
        <a href="{{ route('courses.assignments', array_merge(['course' => $course->id], request()->except('status'), ['status' => 'open'])) }}" 
           class="px-4 py-3 border-b-2 {{ request('status') === 'open' ? 'border-green-600 text-green-600 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
            Open
        </a>
        <a href="{{ route('courses.assignments', array_merge(['course' => $course->id], request()->except('status'), ['status' => 'submitted'])) }}" 
           class="px-4 py-3 border-b-2 {{ request('status') === 'submitted' ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
            Submitted
        </a>
        <a href="{{ route('courses.assignments', array_merge(['course' => $course->id], request()->except('status'), ['status' => 'graded'])) }}" 
           class="px-4 py-3 border-b-2 {{ request('status') === 'graded' ? 'border-purple-600 text-purple-600 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
            Graded
        </a>
    </div>

    <!-- Assignments Grid -->
    <div class="grid grid-cols-1 gap-6">
        @forelse($tasks as $task)
        <div class="bg-white rounded-lg shadow hover:shadow-lg transition">
            <div class="p-6">
                <!-- Title & Status -->
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h3 class="text-xl font-bold text-gray-900">{{ $task->title }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ $task->type }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                        Open
                    </span>
                </div>

                <!-- Description -->
                <p class="text-gray-600 mb-4">{{ \Str::limit($task->description, 150) }}</p>

                <!-- Key Info -->
                <div class="grid grid-cols-4 gap-4 mb-4 py-4 border-y">
                    <div class="text-center">
                        <p class="text-xs text-gray-600 uppercase tracking-wide">Due</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1">
                            {{ $task->due_date?->format('M d') ?? 'N/A' }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $task->due_date?->format('H:i') ?? '' }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600 uppercase tracking-wide">Files</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1">
                            {{ $task->min_file_count }}-{{ $task->max_file_count }}
                        </p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600 uppercase tracking-wide">Max Size</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1">{{ $task->max_file_size_mb }}MB</p>
                    </div>
                    <div class="text-center">
                        <p class="text-xs text-gray-600 uppercase tracking-wide">Resubmit</p>
                        <p class="text-sm font-semibold text-gray-900 mt-1">
                            {{ $task->max_resubmissions ?? '∞' }}
                        </p>
                    </div>
                </div>

                <!-- Your Submission Status -->
                @php
                    $mySubmission = $task->submissions->where('user_id', auth()->id())->first();
                @endphp

                @if($mySubmission)
                    <div class="mb-4 p-3 rounded {{ $mySubmission->status === 'submitted' ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200' }}">
                        <p class="text-xs text-gray-600 uppercase tracking-wide">Your Submission</p>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ ucfirst($mySubmission->status) }}
                                @if($mySubmission->is_late)
                                    <span class="ml-2 text-red-600 text-xs">- LATE</span>
                                @endif
                            </span>
                            <span class="text-xs text-gray-600">
                                {{ $mySubmission->submitted_at?->format('M d, H:i') ?? 'Not submitted' }}
                            </span>
                        </div>
                        @if($mySubmission->grade)
                            <div class="mt-2 flex justify-between items-center">
                                <span class="text-xs text-gray-600">Grade:</span>
                                <span class="text-lg font-bold text-gray-900">
                                    {{ $mySubmission->grade->score }}/{{ $task->max_score }}
                                </span>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Timeline Indicators -->
                <div class="flex gap-3 mb-4 text-xs">
                    @if(now() < $task->open_at)
                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded">Not yet open</span>
                    @elseif(now() < $task->due_date)
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded">📅 Open for {{ $task->due_date->diffForHumans() }}</span>
                    @elseif($task->allow_late_submissions && now() < $task->late_deadline)
                        <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded">⏰ Late submissions until {{ $task->late_deadline?->format('M d') ?? 'N/A' }}</span>
                    @else
                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded">Closed</span>
                    @endif
                    
                    @if($task->late_submission_penalty_percent > 0)
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded">
                            ⚠ {{ $task->late_submission_penalty_percent }}% late penalty
                        </span>
                    @endif
                </div>

                <!-- File Requirements Preview -->
                <div class="mb-4 p-3 bg-blue-50 rounded border border-blue-100 text-sm">
                    <p class="text-xs text-gray-600 uppercase tracking-wide mb-2">Accepted Formats</p>
                    <p class="text-sm text-gray-900">
                         {{ strtoupper(implode(', ', $task->allowed_file_types ?? [])) }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <a href="{{ route('submission-tasks.student.show', [$course, $task]) }}" 
                       class="flex-1 px-4 py-2 bg-blue-600 text-white text-center rounded-lg hover:bg-blue-700 transition font-semibold text-sm">
                        View Details
                    </a>
                    
                    @if(!$mySubmission || (now() < $task->late_deadline && ($task->max_resubmissions === null || $mySubmission->resubmission_count < $task->max_resubmissions)))
                        @if(now() >= $task->open_at && now() <= $task->late_deadline)
                            <a href="{{ route('submissions.create') }}?task_id={{ $task->id }}" 
                               class="flex-1 px-4 py-2 bg-green-600 text-white text-center rounded-lg hover:bg-green-700 transition font-semibold text-sm">
                                {{ $mySubmission ? 'Resubmit' : 'Submit' }}
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="bg-gray-50 rounded-lg p-12 text-center">
            <p class="text-gray-600 text-lg mb-2">No assignments yet for this course</p>
            <p class="text-gray-500">Check back later for new assignments</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
