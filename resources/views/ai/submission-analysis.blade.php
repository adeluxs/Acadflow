@extends('layouts.app')

@section('title', 'AI Analysis - ' . $submission->title)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold">AI Analysis</h2>
            <p class="text-gray-500">{{ $submission->title }} • {{ ucfirst($submission->type) }}</p>
        </div>
        <form method="POST" action="{{ route('ai.submission.reanalyze', $submission) }}">
            @csrf
            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 text-sm">
                Re-run Analysis
            </button>
        </form>
    </div>

    @if($analyses->isEmpty())
        <div class="bg-white p-8 rounded-lg shadow text-center">
            <p class="text-gray-500">No AI analysis yet. It runs automatically after submission.</p>
        </div>
    @else
        @foreach($analyses as $analysis)
            <div class="bg-white p-6 rounded-lg shadow mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold capitalize">
                        {{ str_replace('_', ' ', $analysis->feature) }}
                    </h3>
                    <span class="px-3 py-1 rounded text-xs font-medium
                        @if($analysis->status === 'completed') bg-green-100 text-green-800
                        @elseif($analysis->status === 'failed') bg-red-100 text-red-800
                        @else bg-amber-100 text-amber-800 @endif">
                        {{ ucfirst($analysis->status) }}
                    </span>
                </div>

                @if($analysis->status === 'completed')
                    @if(!is_null($analysis->score))
                        <div class="mb-4">
                            <div class="flex items-center gap-3">
                                <div class="text-3xl font-bold
                                    @if($analysis->score >= 75) text-green-600
                                    @elseif($analysis->score >= 50) text-amber-600
                                    @else text-red-600 @endif">
                                    {{ $analysis->score }}/100
                                </div>
                                <div class="text-sm text-gray-500">Readiness Score</div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div class="h-2 rounded-full
                                    @if($analysis->score >= 75) bg-green-500
                                    @elseif($analysis->score >= 50) bg-amber-500
                                    @else bg-red-500 @endif"
                                    style="width: {{ $analysis->score }}%"></div>
                            </div>
                        </div>
                    @endif

                    @if($analysis->summary)
                        <p class="text-gray-700 mb-4">{{ $analysis->summary }}</p>
                    @endif

                    @if(!empty($analysis->issues))
                        <h4 class="font-medium mb-2">Issues & Suggestions</h4>
                        <ul class="space-y-3">
                            @foreach($analysis->issues as $issue)
                                <li class="border rounded p-3
                                    @if(($issue['severity'] ?? '') === 'critical') border-red-200 bg-red-50
                                    @elseif(($issue['severity'] ?? '') === 'warning') border-amber-200 bg-amber-50
                                    @else border-gray-200 bg-gray-50 @endif">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="font-medium text-sm">
                                            {{ $issue['message'] ?? '' }}
                                        </span>
                                        <span class="text-xs px-2 py-0.5 rounded
                                            @if(($issue['severity'] ?? '') === 'critical') bg-red-200 text-red-800
                                            @elseif(($issue['severity'] ?? '') === 'warning') bg-amber-200 text-amber-800
                                            @else bg-gray-200 text-gray-700 @endif">
                                            {{ ucfirst($issue['severity'] ?? 'info') }}
                                        </span>
                                    </div>
                                    @if(!empty($issue['suggestion']))
                                        <p class="text-sm text-gray-600 mt-1">
                                            <span class="font-medium">Fix:</span> {{ $issue['suggestion'] }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-green-700 text-sm">No issues detected.</p>
                    @endif
                @elseif($analysis->status === 'queued' || $analysis->status === 'processing')
                    <p class="text-gray-500">Analysis is {{ $analysis->status }}… refresh to see results.</p>
                @endif
            </div>
        @endforeach
    @endif

    <a href="{{ route('submissions.show', $submission) }}" class="text-indigo-600 hover:underline text-sm">
        ← Back to submission
    </a>
</div>
@endsection
