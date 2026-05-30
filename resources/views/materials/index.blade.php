@extends('layouts.app')

@section('title', 'Course Materials - ' . $course->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">{{ $course->code }} - {{ $course->name }}</h1>
            <p class="text-gray-600">Course Materials</p>
        </div>
        <div>
            <a href="{{ route('courses.index') }}" class="text-indigo-600 hover:text-indigo-900">← Back to Courses</a>
        </div>
    </div>

    @if(auth()->user()->isLecturer() || auth()->user()->isAdmin())
        <div class="mb-6 flex gap-4">
            <a href="{{ route('lecturer.materials.create', $course) }}" 
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
               + Upload Material
            </a>
            @can('feature', 'document_generation')
                <a href="{{ route('materials.export-pdf', $course) }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"
                   target="_blank">
                   📄 Export PDF
                </a>
            @endcan
        </div>
    @endif

    @if($grouped->count() > 0)
        @foreach($grouped as $groupKey => $groupItems)
            <div class="mb-8">
                @if(\Str::startsWith($groupKey, 'topic_'))
                    <h2 class="text-lg font-bold mb-4 pb-2 border-b">
                        {{ \Str::after($groupKey, 'topic_') }}
                    </h2>
                @elseif(\Str::startsWith($groupKey, 'week_'))
                    <h2 class="text-lg font-bold mb-4 pb-2 border-b">
                        Week {{ \Str::after($groupKey, 'week_') }}
                    </h2>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($groupItems as $material)
                        <div class="bg-white rounded-lg shadow p-4 hover:shadow-lg transition">
                            <div class="flex items-start gap-3">
                                <div class="text-2xl">
                                    @php
                                        $icon = match ($material->type) {
                                            'lecture_note' => '📝',
                                            'slides' => '📊',
                                            'reading' => '📚',
                                            'video' => '🎬',
                                            'assignment' => '📋',
                                            'exam' => '✏️',
                                            'reference' => '📖',
                                            default => '📁',
                                        };
                                    @endphp
                                    {{ $icon }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-semibold truncate">
                                        <a href="{{ route('materials.show', [$course, $material]) }}" 
                                           class="text-indigo-600 hover:underline">
                                            {{ $material->title }}
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $material->type)) }}</p>
                                    @if($material->topic)
                                        <span class="inline-block mt-1 px-2 py-0.5 bg-blue-100 text-blue-800 text-xs rounded">
                                            {{ $material->topic }}
                                        </span>
                                    @endif
                                    @if($material->week_number)
                                        <span class="inline-block mt-1 px-2 py-0.5 bg-green-100 text-green-800 text-xs rounded ml-1">
                                            Week {{ $material->week_number }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="mt-3 flex justify-between items-center text-sm text-gray-500">
                                <span>{{ $material->file_name }}</span>
                                <span>{{ number_format($material->file_size / 1024, 1) }} KB</span>
                            </div>
                            <div class="mt-2 flex gap-2">
                                <a href="{{ route('materials.download', [$course, $material]) }}" 
                                   class="text-xs text-indigo-600 hover:underline">Download</a>
                                @if(auth()->user()->isLecturer() || auth()->user()->isAdmin())
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ route('lecturer.materials.edit', [$course, $material]) }}" 
                                       class="text-xs text-blue-600 hover:underline">Edit</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500">No course materials uploaded yet.</p>
        </div>
    @endif
</div>
@endsection
