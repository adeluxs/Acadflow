@extends('layouts.app')

@section('title', $submission->title)

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-2xl font-bold">{{ $submission->title }}</h2>
                    <p class="text-gray-500">{{ $submission->course->name }} • {{ ucfirst($submission->type) }}</p>
                </div>
                <span class="px-3 py-1 rounded text-sm 
                    @if($submission->status === 'graded') bg-green-100 text-green-800
                    @elseif($submission->status === 'approved') bg-blue-100 text-blue-800
                    @elseif(in_array($submission->status, ['submitted', 'under_review'])) bg-yellow-100 text-yellow-800
                    @else bg-gray-100 text-gray-800
                    @endif">
                    {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                </span>
            </div>

            @if($submission->description)
                <p class="text-gray-700 mb-6">{{ $submission->description }}</p>
            @endif

            <h3 class="font-bold mb-3">Files</h3>
            @if($submission->versions->isEmpty())
                <p class="text-gray-500 mb-4">No files uploaded yet.</p>
            @else
                <ul class="space-y-2 mb-6">
                    @foreach($submission->versions as $version)
                        <li class="flex items-center justify-between p-3 bg-gray-50 rounded">
                            <span>{{ $version->file_name }}</span>
                            <a href="{{ route('submission-versions.download', ['submission' => $submission, 'version' => $version->id]) }}" 
                                class="text-indigo-600 hover:underline text-sm">
                                Download
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if(in_array($submission->status, ['draft', 'correction_requested']))
                <form method="POST" action="{{ route('submissions.upload', $submission) }}" enctype="multipart/form-data" class="mb-4">
                    @csrf
                    <label class="block text-gray-700 text-sm font-bold mb-2">Upload File</label>
                    <input type="file" name="file" class="border rounded px-3 py-2 w-full" required>
                    <button type="submit" class="mt-2 bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
                        Upload
                    </button>
                </form>

                @if($submission->versions->isNotEmpty())
                    <form method="POST" action="{{ route('submissions.submit', $submission) }}">
                        @csrf
                        <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                            Submit for Review
                        </button>
                    </form>
                @endif
            @endif
        </div>

        @if($submission->type === 'project')
            @include('ai._contextual-assistant', [
                'assistantFeature' => 'project_assistant',
                'assistantEndpoint' => route('ai.context.project', $submission),
            ])
            <div class="h-6"></div>
        @endif

        <!-- Comments -->
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-4">Feedback</h3>
            @if($submission->comments->isEmpty())
                <p class="text-gray-500">No feedback yet.</p>
            @else
                @foreach($submission->comments as $comment)
                    <div class="border-b pb-3 mb-3">
                        <p class="font-medium">{{ $comment->user->full_name }}</p>
                        <p class="text-gray-700">{{ $comment->content }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ $comment->created_at->format('M d, Y H:i') }}</p>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div>
        <div class="bg-white p-6 rounded-lg shadow">
            <h3 class="font-bold mb-3">Details</h3>
            <dl class="space-y-2">
                <dt class="text-gray-500 text-sm">Version</dt>
                <dd class="font-medium">{{ $submission->version }}</dd>
                <dt class="text-gray-500 text-sm">Due Date</dt>
                <dd class="font-medium">{{ $submission->due_date?->format('M d, Y') ?? 'No deadline' }}</dd>
                <dt class="text-gray-500 text-sm">Submitted</dt>
                <dd class="font-medium">{{ $submission->submitted_at?->format('M d, Y H:i') ?? '-' }}</dd>
            </dl>
        </div>

        @if($submission->grade)
        <div class="bg-white p-6 rounded-lg shadow mt-4">
            <h3 class="font-bold mb-3">Grade</h3>
            <p class="text-3xl font-bold text-indigo-600">
                {{ $submission->grade->score }}/{{ $submission->grade->max_score }}
            </p>
            @if($submission->grade->feedback)
                <p class="text-gray-600 mt-2">{{ $submission->grade->feedback }}</p>
            @endif
        </div>
        @endif

        @php
            $aiAnalyses = \App\Models\AiAnalysis::where('submission_id', $submission->id)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->get();
        @endphp
        @if($aiAnalyses->isNotEmpty())
        <div class="bg-white p-6 rounded-lg shadow mt-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-bold">AI Analysis</h3>
                <a href="{{ route('ai.submission.analysis', $submission) }}" class="text-indigo-600 hover:underline text-sm">
                    View details
                </a>
            </div>
            <ul class="space-y-2">
                @foreach($aiAnalyses as $ai)
                    <li class="flex items-center justify-between text-sm">
                        <span class="capitalize">{{ str_replace('_', ' ', $ai->feature) }}</span>
                        <span class="font-medium">
                            @if(!is_null($ai->score)) {{ $ai->score }}/100 @else {{ ucfirst($ai->status) }} @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection