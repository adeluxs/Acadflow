<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\StudyReflection;
use App\Services\Student\ReflectionCoachService;
use Illuminate\Http\Request;

/**
 * Metacognitive reflection journal with optional AI feedback.
 */
class StudyReflectionController extends Controller
{
    public function __construct(private readonly ReflectionCoachService $coach) {}

    public function index(Request $request)
    {
        $reflections = StudyReflection::query()
            ->where('user_id', $request->user()->id)
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->paginate(15);

        return view('student.reflections', [
            'reflections' => $reflections,
            'courses' => $this->enrolledCourses($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reflection' => ['required', 'string', 'min:5', 'max:5000'],
            'prompt' => ['nullable', 'string', 'max:255'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $user = $request->user();
        $courseId = isset($data['course_id']) ? (int) $data['course_id'] : null;
        if ($courseId) {
            $course = Course::with('department.faculty')->findOrFail($courseId);
            abort_unless($user->canAccessCourse($course), 403, 'You do not have access to that course.');
        }

        $reflection = StudyReflection::query()->create([
            'user_id' => $user->id,
            'course_id' => $courseId,
            'prompt' => $data['prompt'] ?? null,
            'reflection' => $data['reflection'],
        ]);

        app(\App\Services\AmbassadorQualificationService::class)->recordStudyAction($user, 'reflection', $reflection->reflection);

        try {
            $feedback = $this->coach->feedback($user, $reflection);
            if (is_string($feedback) && trim($feedback) !== '') {
                $reflection->update(['ai_feedback' => $feedback]);
            }
        } catch (\Throwable) {
            // AI feedback is optional; the reflection is still saved.
        }

        return back()->with('success', 'Reflection saved.');
    }

    private function enrolledCourses(Request $request)
    {
        return Course::query()
            ->whereHas('enrollments', fn ($query) => $query->where('user_id', $request->user()->id)->where('status', 'enrolled'))
            ->orderBy('code')
            ->get(['id', 'uuid', 'code', 'name']);
    }
}
