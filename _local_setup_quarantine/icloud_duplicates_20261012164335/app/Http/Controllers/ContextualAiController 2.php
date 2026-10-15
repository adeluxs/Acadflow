<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Discussion;
use App\Models\ResearchProject;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Services\Ai\ContextualAssistantService;
use App\Services\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContextualAiController extends Controller
{
    public function __construct(private readonly ContextualAssistantService $assistants) {}

    public function research(Request $request, ResearchProject $research): JsonResponse
    {
        $this->authorize('view', $research);
        return response()->json($this->assistants->research($request->user(), $research, $this->question($request)));
    }

    public function assignment(Request $request, Course $course, SubmissionTask $task): JsonResponse
    {
        abort_unless($task->course_id === $course->id, 404);
        $this->authorize('view', $task);
        return response()->json($this->assistants->assignment($request->user(), $task, $this->question($request)));
    }

    public function siwes(Request $request, ResearchProject $research): JsonResponse
    {
        $this->authorize('view', $research);
        return response()->json($this->assistants->siwes($request->user(), $research, $this->question($request)));
    }

    public function project(Request $request, Submission $submission): JsonResponse
    {
        $this->authorize('view', $submission);
        abort_unless($submission->type === 'project', 422, 'Project Assistant is available only for project submissions.');
        return response()->json($this->assistants->project($request->user(), $submission, $this->question($request)));
    }

    public function material(Request $request, Course $course, CourseMaterial $material): JsonResponse
    {
        abort_unless($material->course_id === $course->id, 404);
        $this->authorize('view', $material);
        return response()->json($this->assistants->material($request->user(), $material, $this->question($request)));
    }

    public function discussion(Request $request, Course $course, Discussion $discussion, EngagementService $engagement): JsonResponse
    {
        abort_unless($discussion->course_id === $course->id, 404);
        $this->authorize('view', $discussion);
        $replies = $engagement->commentsFor($discussion, 100)->getCollection();
        return response()->json($this->assistants->discussion($request->user(), $discussion, $replies, $this->question($request)));
    }

    private function question(Request $request): string
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        return trim((string) $validated['question']);
    }
}
