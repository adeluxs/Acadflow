<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->whereHas('course.department.faculty', fn ($query) => $query->where('university_id', $user->university_id))
            ->with([
                'semester',
                'course' => fn ($query) => $query
                    ->with(['department.faculty', 'lecturerAssignments.user'])
                    ->withCount([
                        'materials as visible_materials_count' => fn ($materials) => $materials->where('is_visible', true),
                        'submissionTasks as published_assignments_count' => fn ($tasks) => $tasks->where('status', 'published')->where('is_visible_to_students', true),
                    ]),
            ])
            ->latest('enrolled_at')
            ->get();

        return view('courses.index', compact('enrollments'));
    }

    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        $user = Auth::user();
        $isEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->exists();
        $isLecturer = $course->lecturerAssignments()->where('user_id', $user->id)->exists();

        $course->load('department.faculty', 'lecturerAssignments.user');
        $course->loadCount([
            'materials as visible_materials_count' => fn ($q) => $q->where('is_visible', true),
            'submissionTasks as published_assignments_count' => fn ($q) => $q->where('status', 'published')->where('is_visible_to_students', true),
            'enrollments as enrolled_students_count' => fn ($q) => $q->where('status', 'enrolled'),
        ]);

        $recentMaterials = $course->materials()
            ->when($user->isStudent(), fn ($q) => $q->where('is_visible', true))
            ->with('uploader')
            ->latest('published_at')
            ->limit(4)
            ->get();
        $recentTasks = $course->submissionTasks()
            ->when($user->isStudent(), fn ($q) => $q->where('status', 'published')->where('is_visible_to_students', true))
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit(4)
            ->get();
        $recentDiscussions = Discussion::query()
            ->where('course_id', $course->id)
            ->when($user->isStudent(), fn ($q) => $q->whereIn('status', ['open', 'resolved']))
            ->with('user')
            ->latest()
            ->limit(4)
            ->get();

        return view('courses.show', compact('course', 'isEnrolled', 'isLecturer', 'recentMaterials', 'recentTasks', 'recentDiscussions'));
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('view', $course);

        return $this->enrollStudent($course, $request->user());
    }

    public function joinViaLink(string $uuid): View|RedirectResponse
    {
        $course = Course::query()->where('uuid', $uuid)->where('is_active', true)->firstOrFail();
        $user = Auth::user();

        if (! $user->isStudent()) {
            return redirect()->route('courses.show', $course)
                ->with('error', 'Only student accounts can join a course.');
        }

        $this->assertStudentCourseScope($course, $user);

        $alreadyEnrolled = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->exists();

        $course->load('department.faculty');

        return view('courses.join', compact('course', 'alreadyEnrolled'));
    }

    public function processJoinLink(Request $request, string $uuid): RedirectResponse
    {
        $course = Course::query()->where('uuid', $uuid)->where('is_active', true)->firstOrFail();

        if (! $request->user()->isStudent()) {
            abort(403, 'Only student accounts can join a course.');
        }

        return $this->enrollStudent($course, $request->user());
    }

    public function myCourses(): View
    {
        $user = Auth::user();

        $courses = Course::query()
            ->whereHas('lecturerAssignments', fn ($query) => $query->where('user_id', $user->id))
            ->whereHas('department.faculty', fn ($query) => $query->where('university_id', $user->university_id))
            ->with(['department.faculty', 'lecturerAssignments.user'])
            ->withCount([
                'enrollments as enrolled_students_count' => fn ($query) => $query->where('status', 'enrolled'),
                'submissionTasks as assignments_count',
            ])
            ->orderBy('code')
            ->get();

        $availableCourses = collect();
        if ((bool) SettingService::get('lecturer_self_assignment_enabled', true, $user->university_id) && $user->department_id) {
            $availableCourses = Course::query()
                ->where('department_id', $user->department_id)
                ->where('is_active', true)
                ->whereDoesntHave('lecturerAssignments', fn ($query) => $query->where('user_id', $user->id))
                ->with('department.faculty')
                ->orderBy('code')
                ->get();
        }

        return view('courses.lecturer', compact('courses', 'availableCourses'));
    }

    public function adminIndex(): View
    {
        $user = Auth::user();
        $query = Course::query()->with('department.faculty', 'lecturerAssignments.user');

        if ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $query->whereHas('department.faculty', fn ($q) => $q->where('university_id', $user->university_id));
        }

        $courses = $query->orderBy('code')->get();

        return view('courses.admin', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $this->validatedCourseData($request);
        $department = Department::query()->with('faculty')->findOrFail($data['department_id']);

        $this->assertDepartmentScope($user, $department);
        Course::create($data);

        return redirect()->route('admin.courses')->with('success', 'Course created successfully.');
    }

    public function adminShow(Course $course): View
    {
        $this->assertDepartmentScope(Auth::user(), $course->department()->with('faculty')->firstOrFail());

        $course->load('department.faculty', 'enrollments.user', 'lecturerAssignments.user');
        $lecturers = User::query()
            ->where('role', 'lecturer')
            ->where('department_id', $course->department_id)
            ->orderBy('first_name')
            ->get();

        return view('courses.admin-show', compact('course', 'lecturers'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->assertDepartmentScope(Auth::user(), $course->department()->with('faculty')->firstOrFail());
        $course->update($this->validatedCourseData($request, $course));

        return redirect()->route('admin.courses')->with('success', 'Course updated successfully.');
    }

    protected function validatedCourseData(Request $request, ?Course $course = null): array
    {
        $request->merge([
            'credit_hours' => $request->input('credit_hours', $request->input('credits')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('courses', 'code')
                    ->where(fn ($query) => $query->where('department_id', $request->input('department_id')))
                    ->ignore($course?->id),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'credit_hours' => ['required', 'integer', 'min:1', 'max:30'],
            'level' => ['required', 'string', 'max:10'],
            'type' => ['required', 'in:compulsory,elective'],
            'semester' => ['required', 'string', 'max:10'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    protected function assertDepartmentScope(User $user, Department $department): void
    {
        if ($user->isDepartmentAdmin() && $department->id !== $user->department_id) {
            abort(403, 'Access denied.');
        }

        if ($user->isUniversityAdmin() && $department->faculty->university_id !== $user->university_id) {
            abort(403, 'Access denied.');
        }
    }

    protected function enrollStudent(Course $course, User $user): RedirectResponse
    {
        if (! $user->isStudent()) {
            abort(403, 'Only students can enroll in courses.');
        }

        $this->assertStudentCourseScope($course, $user);

        $semester = app(AcademicContextService::class)->requireActiveSemesterForCourse($course);
        $existing = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existing?->status === 'enrolled') {
            return redirect()->route('courses.show', $course)->with('info', 'You are already enrolled in this course.');
        }

        if ($course->max_capacity) {
            $enrolled = Enrollment::query()
                ->where('course_id', $course->id)
                ->where('semester_id', $semester->id)
                ->where('status', 'enrolled')
                ->count();

            if ($enrolled >= $course->max_capacity) {
                return back()->with('error', 'Course is at maximum capacity.');
            }
        }

        Enrollment::updateOrCreate(
            ['user_id' => $user->id, 'course_id' => $course->id, 'semester_id' => $semester->id],
            ['status' => 'enrolled', 'enrolled_at' => now()]
        );

        return redirect()->route('courses.show', $course)->with('success', 'Successfully enrolled.');
    }
    protected function assertStudentCourseScope(Course $course, User $user): void
    {
        $course->loadMissing('department.faculty');
        abort_unless(
            $course->department?->faculty?->university_id === $user->university_id,
            403,
            'You can only join courses from your institution.'
        );

        if ((bool) SettingService::get('restrict_course_membership_to_department', true, $user->university_id)) {
            abort_unless(
                $user->department_id && $user->department_id === $course->department_id,
                403,
                'You can only join courses in your department.'
            );
        }
    }

}
