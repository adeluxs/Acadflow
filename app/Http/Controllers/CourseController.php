<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Discussion;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\CourseMembershipScope;
use App\Services\IndependentAcademicContextService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->whereHas('course', function ($query) use ($user): void {
                $query->where('access_mode', 'independent');
                if ($user->university_id) {
                    $query->orWhereHas('department.faculty', fn ($faculty) => $faculty->where('university_id', $user->university_id));
                }
            })
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
        $discussionsEnabled = FeatureAccessService::canAccessFeature('course_discussions', $user);
        $recentDiscussions = $discussionsEnabled
            ? Discussion::query()
                ->where('course_id', $course->id)
                ->when($user->isStudent(), fn ($q) => $q->whereIn('status', ['open', 'resolved']))
                ->with('user')
                ->latest()
                ->limit(4)
                ->get()
            : collect();

        return view('courses.show', compact('course', 'isEnrolled', 'isLecturer', 'recentMaterials', 'recentTasks', 'recentDiscussions', 'discussionsEnabled'));
    }

    public function enroll(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('enroll', $course);

        return $this->enrollStudent($course, $request->user());
    }

    public function joinViaLink(string $uuid): View|RedirectResponse
    {
        $course = Course::query()->where('uuid', $uuid)->where('is_active', true)->firstOrFail();
        $user = Auth::user();

        if (! $user->isStudent()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only student accounts can join a course.');
        }

        // A student who already has access to the course (for example an existing
        // enrollment) must always be able to open the join page. Applying the
        // institution/department scope gate first produced a false Access Denied
        // for authorized students whose profile lacked or mismatched those fields.
        if ($user->canAccessCourse($course)) {
            $course->load('department.faculty');

            return view('courses.join', ['course' => $course, 'alreadyEnrolled' => true]);
        }

        if ($denial = CourseMembershipScope::denial($user, $course, $this->restrictStudentMembershipToDepartment($user))) {
            return redirect()->route('courses.index')->with('error', $denial);
        }

        $course->load('department.faculty');

        return view('courses.join', ['course' => $course, 'alreadyEnrolled' => false]);
    }

    public function processJoinLink(Request $request, string $uuid): RedirectResponse
    {
        $course = Course::query()->where('uuid', $uuid)->where('is_active', true)->firstOrFail();
        $user = $request->user();

        if (! $user->isStudent()) {
            return redirect()->route('dashboard')
                ->with('error', 'Only student accounts can join a course.');
        }

        return $this->enrollStudent($course, $user);
    }

    public function myCourses(): View
    {
        $user = Auth::user();

        $courses = Course::query()
            ->whereHas('lecturerAssignments', fn ($query) => $query->where('user_id', $user->id))
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


    public function storeIndependent(Request $request, IndependentAcademicContextService $independent): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isLecturer(), 403, 'Only lecturer accounts can create independent classrooms.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')],
            'description' => ['nullable', 'string', 'max:5000'],
            'credit_hours' => ['nullable', 'integer', 'min:1', 'max:30'],
            'level' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'string', 'max:20'],
            'type' => ['nullable', Rule::in(['compulsory', 'elective'])],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'pass_mark' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $context = $independent->context();
        $course = DB::transaction(function () use ($data, $user, $context): Course {
            $course = Course::query()->create([
                'department_id' => $context['department']->id,
                'access_mode' => 'independent',
                'owner_user_id' => $user->id,
                'name' => trim($data['name']),
                'code' => strtoupper(trim($data['code'])),
                'description' => $data['description'] ?? null,
                'credit_hours' => $data['credit_hours'] ?? 3,
                'level' => $data['level'] ?? 'General',
                'semester' => $data['semester'] ?? 'Independent',
                'type' => $data['type'] ?? 'elective',
                'max_capacity' => $data['max_capacity'] ?? null,
                'pass_mark' => $data['pass_mark'] ?? 40,
                'is_active' => true,
                'catalog_source' => 'independent_user',
                'is_catalog_template' => false,
            ]);

            LecturerCourseAssignment::query()->create([
                'course_id' => $course->id,
                'user_id' => $user->id,
                'semester_id' => $context['semester']->id,
                'is_coordinator' => true,
            ]);

            return $course;
        });

        return redirect()->route('courses.show', $course)
            ->with('success', 'Independent classroom created. Share the join link with your students.');
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

        $courses = $query->orderBy('code')->paginate(25)->withQueryString();

        return view('courses.admin', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $data = $this->validatedCourseData($request);
        $department = Department::query()->with('faculty')->findOrFail($data['department_id']);
        if($user->isAdmin())abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment($user,$department),403);

        $this->assertDepartmentScope($user, $department);
        Course::create($data);

        return redirect()->route('admin.courses')->with('success', 'Course created successfully.');
    }

    public function adminShow(Course $course): View
    {
        $this->assertDepartmentScope(Auth::user(), $course->department()->with('faculty')->firstOrFail());

        $course->load('department.faculty', 'lecturerAssignments.user')->loadCount('enrollments');
        $enrollments = $course->enrollments()
            ->select(['id', 'course_id', 'user_id', 'status'])
            ->with('user:id,email')
            ->orderBy('user_id')
            ->paginate(25, ['*'], 'students_page')
            ->withQueryString();
        $lecturers = User::query()
            ->whereHas('institutionAffiliations',fn($q)=>$q->approved()->where('affiliation_type','lecturer')->where('department_id',$course->department_id))
            ->orderBy('first_name')
            ->get();

        return view('courses.admin-show', compact('course', 'lecturers', 'enrollments'));
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->assertDepartmentScope(Auth::user(), $course->department()->with('faculty')->firstOrFail());
        $data=$this->validatedCourseData($request,$course);
        $this->assertDepartmentScope(Auth::user(),Department::with('faculty')->findOrFail($data['department_id']));
        $course->update($data);

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
        if($user->isAdmin())abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment($user,$department),403);
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
            return redirect()->route('dashboard')->with('error', 'Only student accounts can enroll in courses.');
        }

        $semester = app(AcademicContextService::class)->requireActiveSemesterForCourse($course);

        $existing = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existing?->status === 'enrolled') {
            return redirect()->route('courses.show', $course)->with('info', 'You are already enrolled in this course.');
        }

        // A student with an existing enrollment (any semester) already has access
        // and is never blocked by later institution/department scope changes.
        if (! $user->canAccessCourse($course)) {
            if ($denial = CourseMembershipScope::denial($user, $course, $this->restrictStudentMembershipToDepartment($user))) {
                return redirect()->route('courses.index')->with('error', $denial);
            }
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

    private function restrictStudentMembershipToDepartment(User $user): bool
    {
        return (bool) SettingService::get('restrict_course_membership_to_department', true, $user->university_id);
    }

}
