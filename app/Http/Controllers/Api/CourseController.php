<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Semester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['department', 'enrollments']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        return $query->paginate(20);
    }

    public function show(Course $course)
    {
        return $course->load(['department', 'enrollments.user', 'lecturerAssignments.user']);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['department_admin', 'university_admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string',
            'description' => 'nullable|string',
            'credit_hours' => 'required|integer|min:1|max:6',
            'level' => 'required|string',
            'semester' => 'required|string',
            'type' => 'required|in:compulsory,elective',
            'max_capacity' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'submission_types' => 'nullable|array',
            'submission_types.*' => 'string',
        ]);

        $course = Course::create([
            'uuid' => app('Illuminate\Support\Str')->uuid(),
            'department_id' => $validated['department_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'credit_hours' => $validated['credit_hours'],
            'level' => $validated['level'],
            'semester' => $validated['semester'],
            'type' => $validated['type'],
            'max_capacity' => $validated['max_capacity'] ?? null,
            'pass_mark' => $validated['pass_mark'] ?? 40,
            'submission_types' => $validated['submission_types'] ?? ['assignment', 'project', 'siwes'],
            'is_active' => true,
        ]);

        return response()->json($course, 201);
    }

    public function update(Request $request, Course $course)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['department_admin', 'university_admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'credit_hours' => 'nullable|integer|min:1|max:6',
            'level' => 'nullable|string',
            'semester' => 'nullable|string',
            'type' => 'nullable|in:compulsory,elective',
            'max_capacity' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'submission_types' => 'nullable|array',
            'submission_types.*' => 'string',
            'is_active' => 'nullable|boolean',
        ]);

        $course->update($validated);

        return response()->json($course);
    }

    public function destroy(Course $course)
    {
        $user = Auth::user();

        if (! in_array($user->role, ['department_admin', 'university_admin', 'super_admin'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted successfully.']);
    }

    public function enroll(Request $request, Course $course)
    {
        $user = Auth::user();

        $semester = Semester::where('is_active', true)->first();

        if (! $semester) {
            return response()->json(['message' => 'No active semester available.'], 422);
        }

        $existing = $course->enrollments()->where('user_id', $user->id)->where('semester_id', $semester->id)->first();
        if ($existing) {
            return response()->json(['message' => 'Already enrolled'], 400);
        }

        $course->enrollments()->create([
            'user_id' => $user->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return response()->json(['message' => 'Enrolled successfully']);
    }

    public function enrollments(Course $course)
    {
        return $course->enrollments()->with('user')->get();
    }

    public function report(Request $request)
    {
        $query = Course::withCount('enrollments');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return $query->get();
    }
}
