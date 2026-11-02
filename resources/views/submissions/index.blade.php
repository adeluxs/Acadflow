@extends('layouts.app')

@section('title', 'My Submissions')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold">My Submissions</h2>
    <a href="{{ route('submissions.create') }}" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">
        + New Submission
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($submissions as $submission)
                <tr>
                    <td class="px-6 py-4">
                        <a href="{{ route('submissions.show', $submission) }}" class="text-indigo-600 hover:underline">
                            {{ $submission->title }}
                        </a>
                    </td>
                    <td class="px-6 py-4 text-gray-500">{{ $submission->course->name }}</td>
                    <td class="px-6 py-4 text-gray-500">{{ ucfirst($submission->type) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs 
                            @if($submission->status === 'graded') bg-green-100 text-green-800
                            @elseif($submission->status === 'approved') bg-blue-100 text-blue-800
                            @elseif(in_array($submission->status, ['submitted', 'under_review'])) bg-yellow-100 text-yellow-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ ucfirst(str_replace('_', ' ', $submission->status)) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $submission->submitted_at?->format('M d, Y') ?? '-' }}
                    </td>
                    <td class="px-6 py-4">
                        <a href="{{ route('submissions.show', $submission) }}" class="text-indigo-600 hover:underline text-sm">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                        No submissions yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $submissions->links() }}
</div>
@endsection