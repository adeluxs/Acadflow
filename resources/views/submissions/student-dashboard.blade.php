@extends('layouts.app')

@section('title', 'My Submissions Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Submissions Dashboard</h1>
        <p class="text-gray-600 mt-2">Track all your submissions across courses</p>
    </div>

    <!-- Stats Summary -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-gray-900">{{ $submissions->total() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Submitted</p>
            <p class="text-2xl font-bold text-green-600">{{ $stats['submitted'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Graded</p>
            <p class="text-2xl font-bold text-blue-600">{{ $stats['graded'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <p class="text-sm text-gray-600 uppercase tracking-wide">Needs Correction</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['correction_requested'] }}</p>
        </div>
    </div>

    <!-- Submissions List -->
    <div class="bg-white rounded-lg shadow">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-900">All Submissions</h2>
        </div>

        @if($submissions && $submissions->count() > 0)
            <div class="divide-y">
                @foreach($submissions as $submission)
                    <div class="p-6 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="font-bold text-gray-900">{{ $submission->title }}</h3>
                                    <span class="px-2 py-1 text-xs rounded-full
                                        @if($submission->status === 'submitted' || $submission->status === 'resubmitted') bg-green-100 text-green-800
                                        @elseif($submission->status === 'graded' || $submission->status === 'approved') bg-blue-100 text-blue-800
                                        @elseif($submission->status === 'correction_requested') bg-red-100 text-red-800
                                        @else bg-gray-100 text-gray-800
                                        @endif
                                    ">
                                        {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        @if($submission->is_late)
                                            <span class="ml-1 text-red-600">LATE</span>
                                        @endif
                                    </span>
                                </div>

                                <div class="flex gap-6 text-sm text-gray-600">
                                    <span>{{ $submission->course->code }} - {{ $submission->course->name }}</span>
                                    <span>{{ ucfirst($submission->type) }}</span>
                                    @if($submission->submitted_at)
                                        <span>Submitted: {{ $submission->submitted_at->format('M d, Y H:i') }}</span>
                                    @endif
                                </div>

                                @if($submission->grade)
                                    <div class="mt-2">
                                        <span class="text-sm font-semibold text-gray-900">Grade: </span>
                                        <span class="text-lg font-bold {{ $submission->grade->score >= ($submission->task->max_score ?? 100) * 0.6 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $submission->grade->score }}/{{ $submission->task->max_score ?? 100 }}
                                        </span>
                                    </div>
                                @endif

                                @if($submission->comments->where('type', 'feedback')->count() > 0)
                                    <div class="mt-2 p-3 bg-blue-50 rounded border border-blue-200">
                                        <p class="text-sm font-semibold text-blue-900 mb-1">Latest Feedback:</p>
                                        <p class="text-sm text-blue-800">
                                            {{ Str::limit($submission->comments->where('type', 'feedback')->last()->comment, 100) }}
                                        </p>
                                    </div>
                                @endif
                            </div>

                            <div class="flex gap-2 ml-4">
                                <a href="{{ route('submissions.show', $submission) }}" 
                                   class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                    View
                                </a>

                                @if(in_array($submission->status, ['correction_requested', 'draft']))
                                    <a href="{{ route('submissions.show', $submission) }}" 
                                       class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                                        {{ $submission->status === 'correction_requested' ? 'Resubmit' : 'Edit' }}
                                    </a>
                                @endif

                                @if($submission->versions->where('is_current', true)->first())
                                    <a href="{{ route('submissions.view', [$submission, $submission->versions->where('is_current', true)->first()->id]) }}" 
                                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 text-sm"
                                       target="_blank">
                                        View File
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-6">
                {{ $submissions->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <p class="text-gray-500 text-lg mb-2">No submissions yet</p>
                <p class="text-gray-400">Enroll in courses and start submitting assignments</p>
            </div>
        @endif
    </div>
</div>
@endsection
