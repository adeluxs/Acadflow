<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateLecturerContentDraft;
use App\Models\AiContentDraft;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Services\AcademicContextService;
use App\Services\Ai\LecturerContentAssistantService;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lecturer AI Studio endpoints.
 *
 * Interactive endpoints return drafts only. Batch endpoints queue background
 * jobs that produce advisory {@see AiContentDraft} rows. Course content is only
 * created when the lecturer explicitly applies a ready draft.
 */
class LecturerContentAssistantController extends Controller
{
    private const MAX_BATCH_ITEMS = 10;

    public function __construct(
        private readonly LecturerContentAssistantService $assistant,
        private readonly AcademicContextService $academicContext,
    ) {}

    public function course(Request $request, Course $course): JsonResponse
    {
        $this->authorize('create', $course);

        $data = $this->validated($request);

        return response()->json($this->assistant->generate(
            $request->user(),
            $course,
            $data['action'],
            $data['instruction'],
            $data['current_content'] ?? null,
            null,
        ));
    }

    public function material(Request $request, Course $course, CourseMaterial $material): JsonResponse
    {
        abort_unless($material->course_id === $course->id, 404);

        $this->authorize('update', $material);

        $data = $this->validated($request);

        return response()->json($this->assistant->generate(
            $request->user(),
            $course,
            $data['action'],
            $data['instruction'],
            $data['current_content'] ?? null,
            $material,
        ));
    }

    /**
     * Queue a batch of advisory content drafts.
     */
    public function batch(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('create', $course);
        $user = $request->user();

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH_ITEMS],
            'items.*.action' => ['required', 'string', Rule::in(LecturerContentAssistantService::ACTIONS)],
            'items.*.instruction' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $queued = 0;
        foreach ($validated['items'] as $item) {
            $draft = AiContentDraft::query()->create([
                'user_id' => $user->id,
                'university_id' => $user->university_id,
                'course_id' => $course->id,
                'feature' => LecturerContentAssistantService::FEATURE,
                'action' => $item['action'],
                'instruction' => $item['instruction'],
                'status' => AiContentDraft::STATUS_PENDING,
            ]);

            GenerateLecturerContentDraft::dispatch($draft->id);
            $queued++;
        }

        AuditLog::log(
            'ai_content_draft_batch_queued',
            $user->id,
            Course::class,
            $course->id,
            null,
            ['queued' => $queued],
            $request->ip(),
            $request->userAgent(),
        );

        return back()->with('success', $queued.' AI draft(s) queued. They will appear in the draft library when ready.');
    }

    public function draftsPage(Request $request, Course $course)
    {
        $this->authorize('create', $course);

        $drafts = AiContentDraft::query()
            ->where('course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(30);

        return view('materials.ai-drafts', compact('course', 'drafts'));
    }

    public function applyDraft(Request $request, Course $course, AiContentDraft $draft): RedirectResponse
    {
        $this->authorize('create', $course);
        abort_unless($draft->course_id === $course->id && $draft->user_id === $request->user()->id, 404);
        abort_unless($draft->isReady(), 422, 'This draft is not ready to apply.');

        $content = $this->draftContentToHtml((string) $draft->content);

        $material = CourseMaterial::query()->create([
            'course_id' => $course->id,
            'semester_id' => $this->academicContext->requireActiveSemesterForCourse($course)->id,
            'uploaded_by' => $request->user()->id,
            'title' => $this->draftTitle($draft),
            'type' => 'lecture_note',
            'content' => $content,
            'content_format' => 'html',
            'content_updated_at' => now(),
            'source' => 'ai_assisted',
            'topic' => null,
            'sequence_order' => 0,
            'is_public' => false,
            'requires_enrollment' => true,
            'is_visible' => true,
            'published_at' => now(),
        ]);

        $draft->update([
            'status' => AiContentDraft::STATUS_APPLIED,
            'applied_at' => now(),
        ]);

        AuditLog::log(
            'ai_content_draft_applied',
            $request->user()->id,
            CourseMaterial::class,
            $material->id,
            null,
            ['draft_id' => $draft->id, 'action' => $draft->action],
            $request->ip(),
            $request->userAgent(),
        );

        return redirect()->route('materials.show', [$course, $material])
            ->with('success', 'AI draft applied as a course material. Review and edit it as needed.');
    }

    public function discardDraft(Request $request, Course $course, AiContentDraft $draft): RedirectResponse
    {
        $this->authorize('create', $course);
        abort_unless($draft->course_id === $course->id && $draft->user_id === $request->user()->id, 404);

        $draft->update(['status' => AiContentDraft::STATUS_DISCARDED]);

        return back()->with('success', 'Draft discarded.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'action' => ['required', 'string', Rule::in(LecturerContentAssistantService::ACTIONS)],
            'instruction' => ['required', 'string', 'min:3', 'max:4000'],
            'current_content' => ['nullable', 'string', 'max:200000'],
        ]);
    }

    private function draftTitle(AiContentDraft $draft): string
    {
        $base = trim((string) $draft->instruction);
        $base = Str::limit($base !== '' ? $base : Str::headline($draft->action), 200, '');

        return 'AI draft — '.($base !== '' ? $base : Str::headline($draft->action));
    }

    private function draftContentToHtml(string $content): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($content)) ?: [];
        $html = collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph))).'</p>')
            ->implode("\n");

        return HtmlSanitizer::clean($html);
    }
}
