@extends('layouts.app')

@section('title', 'My Documents')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">My Generated Documents</h1>
        <p class="text-gray-600 mt-2">Download your finalized academic documents</p>
    </div>

    <div class="bg-white rounded-lg shadow">
        @if($documents && $documents->count() > 0)
            <div class="divide-y">
                @foreach($documents as $doc)
                    <div class="p-6 flex justify-between items-center hover:bg-gray-50">
                        <div class="flex-1">
                            <h3 class="font-bold text-gray-900">{{ $doc->title }}</h3>
                            <div class="flex gap-4 text-sm text-gray-600 mt-1">
                                <span>Type: {{ $doc->template?->type ?? 'N/A' }}</span>
                                <span>Size: {{ round($doc->file_size / 1024, 2) }} KB</span>
                                <span>Generated: {{ $doc->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('documents.download', $doc) }}" 
                               class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                Download PDF
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="p-6">
                {{ $documents->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <p class="text-gray-500 text-lg mb-2">No documents generated yet</p>
                <p class="text-gray-400">Documents are generated from approved/graded submissions</p>
            </div>
        @endif
    </div>
</div>
@endsection
