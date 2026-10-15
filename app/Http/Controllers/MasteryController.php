<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\Student\ReadinessService;
use App\Services\Student\StudentMasteryService;
use Illuminate\Http\Request;

/**
 * Topic mastery and weak-area insights for students.
 */
class MasteryController extends Controller
{
    public function __construct(
        private readonly StudentMasteryService $mastery,
        private readonly ReadinessService $readiness,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();
        $courseId = $request->filled('course_id') ? $request->integer('course_id') : null;

        return view('student.mastery', [
            'topics' => $this->mastery->all($user, $courseId),
            'weak' => $this->mastery->weakTopics($user, $courseId),
            'readiness' => $this->readiness->latest($user, $courseId),
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function recompute(Request $request)
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id']]);
        $course = Course::with('department.faculty')->findOrFail($data['course_id']);
        abort_unless($request->user()->canAccessCourse($course), 403, 'You do not have access to that course.');

        $this->mastery->recompute($request->user(), $course);
        $this->readiness->compute($request->user(), $course);

        return back()->with('success', 'Mastery and readiness updated from your latest practice.');
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
