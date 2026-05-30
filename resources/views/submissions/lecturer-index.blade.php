@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="bg-white shadow sm:rounded-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Student Submissions to Review</h1>

            @if($submissions->isEmpty())
                <p class="text-gray-500 text-center py-8">No submissions pending review.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Course</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Submitted</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($submissions as $submission)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $submission->user->first_name }} {{ $submission->user->last_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $submission->course->code }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ \Str::limit($submission->title, 30) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 rounded-full text-xs font-semibold 
                                            @if($submission->status === 'submitted') bg-yellow-100 text-yellow-800
                                            @elseif($submission->status === 'graded') bg-green-100 text-green-800
                                            @elseif($submission->status === 'under_review') bg-blue-100 text-blue-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $submission->submitted_at?->format('M d, Y') ?? 'Pending' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <a href="{{ route('submissions.review', $submission) }}" 
                                           class="text-indigo-600 hover:text-indigo-900 font-medium">
                                            Review
                                        </a>
                                        @can('feature', 'document_generation')
                                            <br>
                                            <a href="{{ route('export.grade-report', $submission) }}" 
                                               class="text-green-600 hover:text-green-900 text-xs font-medium"
                                               target="_blank">
                                                📄 Grade Report
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-6">
                    {{ $submissions->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
