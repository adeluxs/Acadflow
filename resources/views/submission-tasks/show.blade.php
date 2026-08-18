@extends('layouts.app')

@section('title', $task->title)

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="rounded-[2rem] bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 p-7 text-white shadow-xl sm:p-9 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-3xl font-black text-white sm:text-4xl">{{ $task->title }}</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">{{ $task->description }}</p>
            <div class="flex gap-4 mt-4 text-sm">
                <span class="px-3 py-1 rounded-full 
                    {{ $task->status === 'published' ? 'bg-green-100 text-green-800' : '' }}
                    {{ $task->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                    {{ $task->status === 'closed' ? 'bg-red-100 text-red-800' : '' }}
                ">
                    {{ ucfirst($task->status) }}
                </span>
                <span class="text-gray-700">Type: <strong>{{ ucfirst($task->type) }}</strong></span>
            </div>
        </div>
        
        <div class="text-right space-y-2">
            <a href="{{ route('submission-tasks.extensions', [$course, $task]) }}"
               class="block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Manage Extensions
            </a>
            @if($task->status === 'draft')
                <a href="{{ route('submission-tasks.edit', [$course, $task]) }}" 
                   class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm mb-2 block">
                    Edit
                </a>
                <form action="{{ route('submission-tasks.publish', [$course, $task]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm"
                            onclick="return confirm('Publish this assignment?')">
                        Publish
                    </button>
                </form>
            @elseif($task->status === 'published')
                <form action="{{ route('submission-tasks.close', [$course, $task]) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition text-sm"
                            onclick="return confirm('Close this assignment?')">
                        Close
                    </button>
                </form>
            @endif
        </div>
    </div>

    @include('ai._contextual-assistant', [
        'assistantFeature' => 'assignment_assistant',
        'assistantEndpoint' => route('ai.context.assignment', [$course, $task]),
    ])

    <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_340px]">
        <!-- Left: Details -->
        <div class="space-y-6">
            <!-- Instructions -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Instructions</h2>
                <div class="prose prose-sm max-w-none">
                    {!! nl2br(e($task->instructions)) !!}
                </div>
            </div>

            <!-- File Requirements -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-gray-900 mb-4">File Requirements</h2>
                <ul class="space-y-3">
                    <li class="flex justify-between">
                        <span class="text-gray-700">Allowed Formats:</span>
                         <span class="font-semibold">{{ implode(', ', $task->allowed_file_types ?? []) }}</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">Max File Size:</span>
                        <span class="font-semibold">{{ $task->max_file_size_mb }} MB</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">File Count:</span>
                        <span class="font-semibold">{{ $task->min_file_count }} - {{ $task->max_file_count }} files</span>
                    </li>
                    <li class="flex justify-between">
                        <span class="text-gray-700">Max Resubmissions:</span>
                        <span class="font-semibold">{{ $task->max_resubmissions ?? 'Unlimited' }}</span>
                    </li>
                </ul>
            </div>

            <!-- Attachments -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-900">Supporting Materials</h2>
                    @if($task->status === 'draft' || $task->status === 'published')
                        <button onclick="document.getElementById('attachmentForm').style.display = 'block'"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                            + Add File
                        </button>
                    @endif
                </div>

                <!-- Upload Form (Hidden) -->
                <div id="attachmentForm" class="bg-gray-50 p-4 rounded mb-4" style="display: none;">
                     <form action="{{ route('submission-tasks.attachment.upload', [$course, $task]) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">File Type</label>
                            <select name="type" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                                <option value="">Select type</option>
                                <option value="template">Template</option>
                                <option value="guide">Guide</option>
                                <option value="rubric">Rubric</option>
                                <option value="example">Example</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">File</label>
                            <input type="file" name="file" class="w-full" required>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                                Upload
                            </button>
                            <button type="button" onclick="document.getElementById('attachmentForm').style.display = 'none'"
                                    class="px-3 py-1 bg-gray-300 text-gray-700 text-sm rounded hover:bg-gray-400">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Attachments List -->
                @if($task->attachments && $task->attachments->count() > 0)
                    <div class="space-y-2">
                        @foreach($task->attachments as $attachment)
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded border">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $attachment->file_name }}</p>
                                <p class="text-xs text-gray-600">{{ ucfirst($attachment->type) }}</p>
                            </div>
                            <div class="flex gap-2">
                                 <a href="{{ route('submission-tasks.attachment.download', [$course, $task, $attachment]) }}"
                                    class="text-blue-600 hover:underline text-sm">Download</a>
                                @if($task->status === 'draft' || $task->status === 'published')
                                    <form action="{{ route('submission-tasks.attachment.delete', [$course, $task, $attachment]) }}" 
                                          method="POST" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:underline text-sm"
                                                onclick="return confirm('Delete this file?')">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 text-sm">No supporting materials uploaded yet.</p>
                @endif
            </div>
        </div>

        <!-- Right: Sidebar -->
        <div>
            <!-- Key Dates -->
            <div class="rounded-3xl border border-blue-200 bg-blue-50 p-6">
                <h3 class="font-bold text-gray-900 mb-4">Important Dates</h3>
                <ul class="space-y-3 text-sm">
                    <li>
                        <p class="text-gray-600">Opens</p>
                        <p class="font-semibold">{{ $task->open_at?->format('M d, Y H:i') ?? 'Not set' }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Due (Soft)</p>
                        <p class="font-semibold">{{ $task->due_date?->format('M d, Y H:i') ?? 'Not set' }}</p>
                    </li>
                    <li>
                        <p class="text-gray-600">Hard Deadline</p>
                        <p class="font-semibold">{{ $task->late_deadline?->format('M d, Y H:i') ?? 'Not set' }}</p>
                    </li>
                </ul>
            </div>

            <!-- Submission Stats -->
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                <h3 class="font-bold text-gray-900 mb-4">Submissions</h3>
                <div class="text-3xl font-bold text-green-600">{{ $stats['total_submissions'] ?? 0 }}</div>
                <p class="text-sm text-gray-600 mt-1">of {{ ($stats['total_submissions'] ?? 0) + ($nonSubmitters->count() ?? 0) }} enrolled students</p>
                <div class="mt-4 w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-green-600 h-2 rounded-full" 
                         style="width: {{ (($stats['total_submissions'] ?? 0) + ($nonSubmitters->count() ?? 0)) > 0 ? (($stats['total_submissions'] ?? 0) / (($stats['total_submissions'] ?? 0) + ($nonSubmitters->count() ?? 0)) * 100) : 0 }}%">
                    </div>
                </div>
            </div>

            <!-- Late Penalty Info -->
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                <h3 class="font-bold text-gray-900 mb-3">Late Submission Policy</h3>
                <ul class="space-y-2 text-sm">
                    <li class="flex items-start gap-2">
                        <span class="text-yellow-600 font-bold">•</span>
                        <span>{{ $task->allow_late_submissions ? 'Late submissions allowed' : 'No late submissions' }}</span>
                    </li>
                    @if($task->allow_late_submissions)
                        <li class="flex items-start gap-2">
                            <span class="text-yellow-600 font-bold">•</span>
                            <span>{{ $task->late_submission_penalty_percent }}% penalty applied</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Student Submissions</h2>
        
        @if($submissions && $submissions->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Student</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Status</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Submitted</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Grade</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($submissions as $submission)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-900">{{ $submission->user->name }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs rounded 
                                    {{ $submission->status === 'submitted' ? 'bg-green-100 text-green-800' : '' }}
                                    {{ $submission->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $submission->is_late ? 'bg-red-100 text-red-800' : '' }}
                                ">
                                    {{ $submission->is_late ? 'Late' : ucfirst($submission->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $submission->submitted_at?->format('M d, H:i') ?? 'Not submitted' }}
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ $submission->grade?->score ?? '-' }}/{{ $task->max_score }}
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('submissions.review', $submission) }}"
                                   class="text-blue-600 hover:underline text-sm">Review</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-500 text-center py-8">No submissions yet.</p>
        @endif
    </div>
    </div>

    <!-- Non-Submitters List -->
    @if(isset($nonSubmitters) && $nonSubmitters->count() > 0)
        <div class="bg-white rounded-lg shadow p-6 mt-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Students Not Submitted</h2>
            <p class="text-sm text-gray-600 mb-4">{{ $nonSubmitters->count() }} enrolled students have not submitted this assignment.</p>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Student ID</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Name</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Email</th>
                            <th class="px-4 py-2 text-left font-semibold text-gray-900">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nonSubmitters as $student)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-900">{{ $student->student_id ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $student->first_name }} {{ $student->last_name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $student->email }}</td>
                                <td class="px-4 py-3">
                                    <a href="mailto:{{ $student->email }}" class="text-blue-600 hover:underline text-sm">Send Reminder</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
