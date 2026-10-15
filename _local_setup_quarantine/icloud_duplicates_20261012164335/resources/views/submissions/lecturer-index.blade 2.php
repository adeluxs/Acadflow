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
                                            @if($submission->status === 'submitted')
                                                bg-yellow-100 text-yellow-800
                                            @elseif($submission->status === 'graded')
                                                bg-green-100 text-green-800
                                            @elseif($submission->status === 'under_review')
                                                bg-blue-100 text-blue-800
                                            @else
                                                bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                                        </span>
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $submission->submitted_at?->format('M d, Y') ?? 'Pending' }}
                                    </td>

                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex flex-col gap-2">

                                            <!-- Review -->
                                            <a href="{{ route('submissions.review', $submission) }}"
                                                class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700 transition-colors">

                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2">
                                                    <path stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0s-3-7-9-7-9 7-9 7 3 7 9 7 9-7 9-7z" />
                                                </svg>

                                                <span>Review</span>
                                            </a>

                                            <!-- AI Analysis -->
                                            <a href="{{ route('ai.submission.analysis', $submission) }}"
                                                class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-purple-600 text-white text-xs font-medium hover:bg-purple-700 transition-colors">

                                                <svg xmlns="http://www.w3.org/2000/svg"
                                                    class="w-4 h-4"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2">
                                                    <path stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M9.75 3v2.25M14.25 3v2.25M3 9.75h2.25M18.75 9.75H21M5.636 5.636l1.591 1.591M16.773 16.773l1.591 1.591M5.636 18.364l1.591-1.591M16.773 7.227l1.591-1.591M12 7a5 5 0 100 10 5 5 0 000-10z" />
                                                </svg>

                                                <span>AI Analysis</span>
                                            </a>

                                            @can('feature', 'document_generation')
                                                <!-- Grade Report -->
                                                <a href="{{ route('export.grade-report', $submission) }}"
                                                    target="_blank"
                                                    class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-md bg-green-600 text-white text-xs font-medium hover:bg-green-700 transition-colors">

                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="w-4 h-4"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="2">
                                                        <path stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="M9 12h6m-6 4h6M8 4h8l4 4v12a2 2 0 01-2 2H8a2 2 0 01-2-2V6a2 2 0 012-2z" />
                                                    </svg>

                                                    <span>Grade Report</span>
                                                </a>
                                            @endcan

                                        </div>
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