<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DeadlineCoachPlan;
use App\Services\Student\DeadlineCoachService;
use Illuminate\Http\Request;

/**
 * Advisory deadline coach: ordered action plans for upcoming deadlines.
 */
class DeadlineCoachController extends Controller
{
    public function __construct(private readonly DeadlineCoachService $coach) {}

    public function index(Request $request)
    {
        $plans = DeadlineCoachPlan::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(15);

        return view('student.deadline-coach', [
            'plans' => $plans,
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['course_id' => ['required', 'integer', 'exists:courses,id']]);
        $course = Course::with('department.faculty')->findOrFail($data['course_id']);
        abort_unless($request->user()->canAccessCourse($course), 403, 'You do not have access to that course.');

        $plan = $this->coach->generate($request->user(), $course);

        return redirect()->route('deadline-coach.show', $plan)->with('success', 'Your deadline plan is ready.');
    }

    public function show(Request $request, DeadlineCoachPlan $plan)
    {
        abort_unless($plan->user_id === $request->user()->id, 404);
        $plan->load('course:id,uuid,code,name');

        return view('student.deadline-coach-show', compact('plan'));
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
