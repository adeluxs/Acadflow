<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ScopesTenantData;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\Semester;
use App\Services\AcademicContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    use ScopesTenantData;

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->scopeCourseQuery(Course::query()->with(['department', 'enrollments']), $user);

        if ($user->isStudent()) {
            $query->whereHas('enrollments', fn ($scope) => $scope->where('user_id', $user->id)->where('status', 'enrolled'));
        } elseif ($user->isLecturer()) {
            $query->whereHas('lecturerAssignments', fn ($scope) => $scope->where('user_id', $user->id));
        }

        $query->when($request->filled('department_id'), fn ($scope) => $scope->where('department_id', $request->integer('department_id')))
            ->when($request->filled('level'), fn ($scope) => $scope->where('level', $request->string('level')->toString()));

        return $query->paginate(20);
    }

    public function show(Request $request, Course $course)
    {
        $this->assertCourseTenant($request->user(), $course);
        $this->authorize('view', $course);

        return $course->load(['department', 'enrollments.user', 'lecturerAssignments.user']);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Course::class);
        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string', 'description' => 'nullable|string',
            'credit_hours' => 'required|integer|min:1|max:12', 'level' => 'required|string',
            'semester' => 'required|string', 'type' => 'required|in:compulsory,elective',
            'max_capacity' => 'nullable|integer|min:1', 'pass_mark' => 'nullable|integer|min:0|max:100',
            'submission_types' => 'nullable|array', 'submission_types.*' => 'in:assignment,project,siwes,group,seminar',
        ]);
        $department = Department::with('faculty')->findOrFail($validated['department_id']);
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $department->faculty?->university_id === $user->university_id, 403);
        if ($user->isDepartmentAdmin()) abort_unless($department->id === $user->department_id, 403);

        $course = Course::create([
            'uuid' => (string) Str::uuid(), 'department_id' => $department->id,
            'code' => $validated['code'], 'name' => $validated['name'], 'description' => $validated['description'] ?? null,
            'credit_hours' => $validated['credit_hours'], 'level' => $validated['level'], 'semester' => $validated['semester'],
            'type' => $validated['type'], 'max_capacity' => $validated['max_capacity'] ?? null,
            'pass_mark' => $validated['pass_mark'] ?? 40,
            'submission_types' => $validated['submission_types'] ?? ['assignment', 'project', 'siwes'], 'is_active' => true,
        ]);

        return response()->json($course, 201);
    }

    public function update(Request $request, Course $course)
    {
        $this->assertCourseTenant($request->user(), $course);
        $this->authorize('update', $course);
        $validated = $request->validate([
            'name' => 'nullable|string', 'description' => 'nullable|string', 'credit_hours' => 'nullable|integer|min:1|max:12',
            'level' => 'nullable|string', 'semester' => 'nullable|string', 'type' => 'nullable|in:compulsory,elective',
            'max_capacity' => 'nullable|integer|min:1', 'pass_mark' => 'nullable|integer|min:0|max:100',
            'submission_types' => 'nullable|array', 'submission_types.*' => 'in:assignment,project,siwes,group,seminar', 'is_active' => 'nullable|boolean',
        ]);
        $course->update($validated);
        return response()->json($course);
    }

    public function destroy(Request $request, Course $course)
    {
        $this->assertCourseTenant($request->user(), $course);
        $this->authorize('delete', $course);
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully.']);
    }

    public function enroll(Request $request, Course $course, AcademicContextService $academicContext)
    {
        $this->assertCourseTenant($request->user(), $course);
        $this->authorize('enroll', $course);
        $user = Auth::user();
        $semester = $academicContext->activeSemesterForCourse($course);
        if (! $semester) return response()->json(['message' => 'No active semester available.'], 422);
        abort_unless($semester->academicSession?->university_id === $course->department?->faculty?->university_id, 422, 'The active semester does not belong to this course institution.');

        $existing = $course->enrollments()->where('user_id', $user->id)->where('semester_id', $semester->id)->first();
        if ($existing) return response()->json(['message' => 'Already enrolled'], 409);
        if ($course->max_capacity && $course->enrollments()->where('semester_id', $semester->id)->where('status', 'enrolled')->count() >= $course->max_capacity) {
            return response()->json(['message' => 'Course capacity has been reached.'], 422);
        }
        $course->enrollments()->create(['user_id' => $user->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()]);
        return response()->json(['message' => 'Enrolled successfully']);
    }

    public function enrollments(Request $request, Course $course)
    {
        $this->assertCourseTenant($request->user(), $course);
        abort_unless($request->user()->isAdmin() || $course->lecturerAssignments()->where('user_id', $request->user()->id)->exists(), 403);
        return $course->enrollments()->with('user')->paginate(50);
    }

    public function report(Request $request)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isLecturer(), 403);
        $query = $this->scopeCourseQuery(Course::query()->withCount('enrollments'), $request->user());
        if ($request->user()->isLecturer()) $query->whereHas('lecturerAssignments', fn ($scope) => $scope->where('user_id', $request->user()->id));
        $query->when($request->filled('department_id'), fn ($scope) => $scope->where('department_id', $request->integer('department_id')));
        return $query->paginate(100);
    }
}
