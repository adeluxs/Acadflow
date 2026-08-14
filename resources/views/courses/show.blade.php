@extends('layouts.app')

@section('title', $course->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('courses.index') }}" class="text-blue-600 hover:underline flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Back to My Courses
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2">
            <!-- Course Header -->
            <div class="bg-white rounded-2xl shadow p-6 mb-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $course->name }}</h1>
                        <p class="text-gray-500">{{ $course->code }}</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $course->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $course->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 border-t border-gray-100">
                    <div>
                        <p class="text-sm text-gray-500">Credits</p>
                        <p class="font-semibold">{{ $course->credit_hours }} hrs</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Level</p>
                        <p class="font-semibold">{{ ucfirst($course->level) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Semester</p>
                        <p class="font-semibold">{{ ucfirst($course->semester) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Type</p>
                        <p class="font-semibold">{{ ucfirst($course->type) }}</p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            @if($course->description)
            <div class="bg-white rounded-2xl shadow p-6 mb-6">
                <h2 class="text-lg font-bold mb-4">Course Description</h2>
                <p class="text-gray-700 leading-relaxed">{{ $course->description }}</p>
            </div>
            @endif

            <!-- Submissions (if any) -->
            @if($isEnrolled && $course->submissionTasks()->count() > 0)
            <div class="bg-white rounded-2xl shadow p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Assignments</h2>
                    <a href="{{ route('courses.assignments', $course) }}" class="text-sm text-blue-600 hover:underline">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse($course->submissionTasks()->where('status', 'published')->orderBy('due_date')->take(3)->get() as $task)
                        <a href="{{ route('submission-tasks.student.show', [$course, $task]) }}" 
                           class="block p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-900">{{ $task->title }}</h3>
                                    <p class="text-sm text-gray-600 mt-1">{{ $task->description ?? 'No description' }}</p>
                                    <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            {{ $task->due_date->format('M d, Y') }}
                                        </span>
                                        @if($task->max_score)
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                            {{ $task->max_score }} pts
                                        </span>
                                        @endif
                                    </div>
                                </div>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </a>
                    @empty
                        <p class="text-gray-500 text-center py-4">No published assignments</p>
                    @endforelse
                </div>
                @if($course->submissionTasks()->where('status', 'published')->count() > 3)
                <div class="text-center pt-4 border-t border-gray-100">
                    <a href="{{ route('courses.assignments', $course) }}" class="text-blue-600 hover:underline font-medium">View all assignments</a>
                </div>
                @endif
            </div>
            @endif

            <!-- Materials (if any) -->
            <div class="bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold">Course Materials</h2>
                    <a href="{{ route('materials.index', $course) }}" class="text-sm text-blue-600 hover:underline">View all</a>
                </div>
                <div class="space-y-3">
                    @forelse($course->materials()->where('is_visible', true)->latest()->take(3)->get() as $material)
                        <a href="{{ route('materials.show', [$course, $material]) }}" 
                           class="block p-4 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900">{{ $material->title }}</h3>
                                        <p class="text-sm text-gray-500">{{ $material->file_type }} • {{ $material->file_size_formatted }}</p>
                                    </div>
                                </div>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </div>
                        </a>
                    @empty
                        <p class="text-gray-500 text-center py-4">No materials available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="font-bold mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    @if($isEnrolled)
                        <a href="{{ route('courses.assignments', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            View Assignments
                        </a>
                        <a href="{{ route('materials.index', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Course Materials
                        </a>
                        <a href="{{ route('discussions.index', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Discussions
                        </a>
                    @endif
                    @if($isLecturer)
                        <a href="{{ route('lecturer.materials.index', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Manage Materials
                        </a>
                        <a href="{{ route('discussions.index', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            Discussions
                        </a>
                        <a href="{{ route('submission-tasks.manage.index', $course) }}" 
                           class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium">
    
                          <!-- Create / Add Icon -->
                           <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                           d="M12 4v16m8-8H4" />
                           </svg>

                          Create Assignments
                        </a>
                    @endif
                </div>
            </div>

            <!-- Course Stats -->
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="font-bold mb-4">Course Info</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Department</span>
                        <span class="font-medium">{{ $course->department->name ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Enrolled Students</span>
                        <span class="font-medium">{{ $course->enrollments()->where('status', 'enrolled')->count() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Lecturers</span>
                        <span class="font-medium">{{ $course->lecturerAssignments()->count() }}</span>
                    </div>
                    @if($course->max_capacity)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Capacity</span>
                        <span class="font-medium">{{ $course->enrollments()->where('status', 'enrolled')->count() }}/{{ $course->max_capacity }}</span>
                    </div>
                    @endif
                    @if($course->pass_mark)
                    <div class="flex justify-between">
                        <span class="text-gray-500">Pass Mark</span>
                        <span class="font-medium">{{ $course->pass_mark }}%</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Lecturers -->
            @if($course->lecturerAssignments->count() > 0)
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="font-bold mb-4">Course Lecturers</h3>
                <div class="space-y-3">
                    @forelse($course->lecturerAssignments as $assignment)
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-indigo-600 font-semibold">{{ substr($assignment->user->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $assignment->user->name }}</p>
                                <p class="text-sm text-gray-500">{{ $assignment->user->email }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-sm">No lecturers assigned</p>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
