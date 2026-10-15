<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateStudyPlan;
use App\Models\Course;
use App\Models\StudyPlan;
use App\Services\Ai\StudyPlanService;
use Illuminate\Http\Request;

/**
 * Student personal study plans. Generation runs on the queue.
 */
class StudyPlanController extends Controller
{
    public function __construct(private readonly StudyPlanService $plans) {}

    public function index(Request $request)
    {
        $plans = StudyPlan::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(20);

        return view('student.study-plans', [
            'plans' => $plans,
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $user = $request->user();
        $course = Course::with('department.faculty')->findOrFail($data['course_id']);
        abort_unless($user->canAccessCourse($course), 403, 'You do not have access to that course.');

        $plan = $this->plans->queue($user, $course, (int) ($data['weeks'] ?? 4));

        GenerateStudyPlan::dispatch($plan->id);

        return redirect()->route('study-plans.show', $plan)
            ->with('success', 'Your study plan is being generated. Refresh shortly to see it.');
    }

    public function show(Request $request, StudyPlan $plan)
    {
        abort_unless($plan->user_id === $request->user()->id, 404);

        $plan->load('course:id,uuid,code,name');

        return view('student.study-plan', compact('plan'));
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
