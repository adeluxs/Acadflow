<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    // Student: View available courses
    public function index()
    {
        $user = Auth::user();
        
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'enrolled')
            ->with('course.department')
            ->get();

        return view('courses.index', compact('enrollments'));
    }

    // Student/Lecturer: View course details
    public function show(Course $course)
    {
        $user = Auth::user();
        
        // Check if user is enrolled in the course (for students)
        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'enrolled')
            ->exists();

        // Check if user is assigned as lecturer
        $isLecturer = $course->lecturerAssignments()
            ->where('user_id', $user->id)
            ->exists();

        // Allow access if: enrolled student, assigned lecturer, or admin/role that manages courses
        if (! $isEnrolled && ! $isLecturer && ! $user->hasAnyRole(['department_admin', 'university_admin', 'super_admin'])) {
            abort(403, 'Access denied. You must be enrolled in this course or assigned as a lecturer.');
        }

        $course->load('department', 'lecturerAssignments.user', 'enrollments.user');

        return view('courses.show', compact('course', 'isEnrolled', 'isLecturer'));
    }

    // Student: Enroll in a course
    public function enroll(Request $request, Course $course)
    {
        $semester = Semester::where('is_active', true)->firstOrFail();

        $existing = Enrollment::where('user_id', Auth::id())
            ->where('course_id', $course->id)
            ->where('semester_id', $semester->id)
            ->first();

        if ($existing) {
            return back()->with('error', 'Already enrolled in this course.');
        }

        // Check capacity
        if ($course->max_capacity) {
            $enrolled = Enrollment::where('course_id', $course->id)->count();
            if ($enrolled >= $course->max_capacity) {
                return back()->with('error', 'Course is at maximum capacity.');
            }
        }

        Enrollment::create([
            'user_id' => Auth::id(),
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
        ]);

        return redirect()->route('courses.index')->with('success', 'Successfully enrolled.');
    }

    // Lecturer: My assigned courses
    public function myCourses()
    {
        $user = Auth::user();
        
        $courses = Course::whereHas('lecturerAssignments', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with('department', 'lecturerAssignments.user')->get();

        return view('courses.lecturer', compact('courses'));
    }

    // Admin: List courses (department scoped)
    public function adminIndex()
    {
        $user = Auth::user();
        
        $query = Course::with('department', 'lecturerAssignments.user');
        
        if ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        }
        if ($user->isUniversityAdmin()) {
            $query->whereHas('department', function ($q) use ($user) {
                $q->where('university_id', $user->university_id);
            });
        }
        
        $courses = $query->get();

        return view('courses.admin', compact('courses'));
    }

    // Admin: Store new course
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $department = Department::where('id', $request->department_id);  
        if ($user->isDepartmentAdmin() && $department->value('id') !== $user->department_id) {
            abort(403);
        }

        $course = Course::create($request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:courses',
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|string',
            'credits' => 'required|integer|min:1',
            'level' => 'required|in:undergraduate,graduate,doctoral',
            'type' => 'required|in:core,elective',
            'semester' => 'required|in:fall,spring,summer',
            'max_capacity' => 'nullable|integer|min:1',
            'max_students' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]));

        return redirect()->route('admin.courses')->with('success', 'Course created successfully.');
    }

    // Admin: Show course details
    public function adminShow(Course $course)
    {
        $user = Auth::user();

        // Check scope
        if ($user->isDepartmentAdmin() && $course->department_id !== $user->department_id) {
            abort(403, 'Access denied.');
        }
        if ($user->isUniversityAdmin() && $course->department->faculty->university_id !== $user->university_id) {
            abort(403, 'Access denied.');
        }

        $course->load('department', 'enrollments.user', 'lecturerAssignments.user');

        // Get available lecturers in the same department
        $lecturers = User::where('role', 'lecturer')
            ->where('department_id', $course->department_id)
            ->get();

        return view('courses.admin-show', compact('course', 'lecturers'));
    }

    // Admin: Update course
    public function update(Request $request, Course $course)
    {
        $user = Auth::user();

        // Check scope
        if ($user->isDepartmentAdmin() && $course->department_id !== $user->department_id) {
            abort(403, 'Access denied.');
        }
        if ($user->isUniversityAdmin() && $course->department->faculty->university_id !== $user->university_id) {
            abort(403, 'Access denied.');
        }

        $course->update($request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:courses,code,' . $course->id,
            'department_id' => 'required|exists:departments,id',
            'description' => 'nullable|string',
            'credits' => 'required|integer|min:1',
            'level' => 'required|in:undergraduate,graduate,doctoral',
            'type' => 'required|in:core,elective',
            'semester' => 'required|in:fall,spring,summer',
            'max_capacity' => 'nullable|integer|min:1',
            'max_students' => 'nullable|integer|min:1',
            'pass_mark' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]));

        return redirect()->route('admin.courses')->with('success', 'Course updated successfully.');
    }
}
