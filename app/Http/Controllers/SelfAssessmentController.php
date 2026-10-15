<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\SelfAssessment;
use App\Models\SubmissionTask;
use Illuminate\Http\Request;

/**
 * Pre-submission rubric self-assessment for students.
 */
class SelfAssessmentController extends Controller
{
    public function show(Request $request, Course $course, SubmissionTask $task)
    {
        abort_unless((int) $task->course_id === (int) $course->id, 404);
        $this->authorize('view', $task);

        $latest = SelfAssessment::query()
            ->where('user_id', $request->user()->id)
            ->where('submission_task_id', $task->id)
            ->latest('id')
            ->first();

        return view('student.self-assessment', [
            'course' => $course,
            'task' => $task,
            'criteria' => $this->criteriaFrom($task),
            'latest' => $latest,
        ]);
    }

    public function store(Request $request, Course $course, SubmissionTask $task)
    {
        abort_unless((int) $task->course_id === (int) $course->id, 404);
        $this->authorize('view', $task);

        $data = $request->validate([
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['string', 'max:255'],
            'confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $assessment = SelfAssessment::query()->create([
            'user_id' => $request->user()->id,
            'submission_task_id' => $task->id,
            'checklist' => array_values($data['checklist'] ?? []),
            'confidence' => (int) ($data['confidence'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ]);

        AuditLog::log('self_assessment_created', $request->user()->id, SelfAssessment::class, $assessment->id, null, ['task_id' => $task->id], $request->ip(), $request->userAgent());

        return back()->with('success', 'Self-assessment saved. Use it to improve your draft before submitting.');
    }

    /** @return list<string> */
    private function criteriaFrom(SubmissionTask $task): array
    {
        $task->loadMissing('rubric');

        $criteria = [];
        $rubric = $task->rubric;
        if ($rubric && is_array($rubric->criteria)) {
            foreach ($rubric->criteria as $criterion) {
                if (is_array($criterion)) {
                    $label = trim((string) ($criterion['title'] ?? $criterion['name'] ?? ''));
                } else {
                    $label = trim((string) $criterion);
                }
                if ($label !== '') {
                    $criteria[] = $label;
                }
            }
        }

        return $criteria;
    }
}
