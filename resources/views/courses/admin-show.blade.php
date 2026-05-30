@extends('layouts.app')

@section('title', $course->name)

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold mb-6">{{ $course->code }} - {{ $course->name }}</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Course Info -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <div class="text-gray-500 text-sm">Credits</div>
                    <div>{{ $course->credits }}</div>
                </div>
                <div>
                    <div class="text-gray-500 text-sm">Status</div>
                    <span class="px-2 inline-flex text-sm font-semibold rounded-full {{ $course->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $course->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>

            <h2 class="text-lg font-bold mb-4">Course Lecturers ({{ $course->lecturerAssignments->count() }})</h2>
            @if($course->lecturerAssignments->count() > 0)
                <div class="space-y-3 mb-4">
                    @foreach($course->lecturerAssignments as $assignment)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <span class="text-indigo-600 font-semibold">{{ substr($assignment->user->name, 0, 1) }}</span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">{{ $assignment->user->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $assignment->user->email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($assignment->is_coordinator)
                                    <span class="px-2 py-1 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded">Coordinator</span>
                                @endif
                                <form method="POST" action="{{ route('admin.courses.lecturers.destroy', [$course, $assignment]) }}" onsubmit="return confirm('Are you sure you want to remove this lecturer?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-900 text-sm font-medium">Remove</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-gray-500 mb-4">No lecturers assigned to this course.</p>
            @endif

            <!-- Assign Lecturer Form -->
            <div class="border-t pt-4">
                <h3 class="text-md font-semibold mb-3">Assign New Lecturer</h3>
                <form method="POST" action="{{ route('admin.courses.lecturers.store', $course) }}" class="flex gap-2">
                    @csrf
                    <select name="user_id" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Select a lecturer...</option>
                        @foreach($lecturers as $lecturer)
                            @if(! $course->lecturerAssignments->contains('user_id', $lecturer->id))
                                <option value="{{ $lecturer->id }}">{{ $lecturer->name }} ({{ $lecturer->email }})</option>
                            @endif
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <label class="flex items-center gap-1 text-sm text-gray-700">
                            <input type="checkbox" name="is_coordinator" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Coordinator
                        </label>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                        Assign
                    </button>
                </form>
            </div>
        </div>

        <!-- Enrolled Students -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-bold mb-4">Enrolled Students ({{ $course->enrollments->count() }})</h2>
            @if($course->enrollments->count() > 0)
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Student</th>
                            <th class="px-4 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($course->enrollments as $enrollment)
                            <tr>
                                <td class="px-4 py-2">{{ $enrollment->user->email }}</td>
                                <td class="px-4 py-2">{{ $enrollment->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-4 py-2">No enrollments</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <p class="text-gray-500">No enrollments</p>
            @endif
        </div>
    </div>
</div>
@endsection
