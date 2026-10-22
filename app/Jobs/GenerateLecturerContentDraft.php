<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiContentDraft;
use App\Services\Ai\LecturerContentAssistantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued batch automation for lecturer content drafts.
 *
 * Runs a single AI draft generation in the background and records the result
 * on the advisory {@see AiContentDraft} row. This job never writes course
 * content; the lecturer applies a ready draft explicitly.
 */
class GenerateLecturerContentDraft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(private readonly int $draftId) {}

    public function handle(LecturerContentAssistantService $assistant): void
    {
        $draft = AiContentDraft::query()
            ->with(['course.department.faculty', 'material', 'user'])
            ->find($this->draftId);

        if (! $draft) {
            return;
        }

        // Never regenerate something a lecturer has already applied or discarded.
        if (in_array($draft->status, [AiContentDraft::STATUS_APPLIED, AiContentDraft::STATUS_DISCARDED], true)) {
            return;
        }

        if (! $draft->user || ! $draft->course) {
            $draft->update([
                'status' => AiContentDraft::STATUS_FAILED,
                'error_code' => 'AI_DRAFT_CONTEXT_MISSING',
            ]);
            return;
        }

        $draft->update(['status' => AiContentDraft::STATUS_GENERATING]);

        try {
            app(\App\Services\AcademicOrganisationService::class)->restoreCourseActor($draft->user,$draft->course,'lecturer');
            $result = $assistant->generate(
                $draft->user,
                $draft->course,
                $draft->action,
                (string) $draft->instruction,
                null,
                $draft->material,
            );
        } catch (\Throwable $e) {
            $draft->update([
                'status' => AiContentDraft::STATUS_FAILED,
                'error_code' => 'AI_REQUEST_FAILED',
            ]);

            try {
                Log::channel('ai_provider')->warning('Queued lecturer content draft generation failed.', [
                    'draft_id' => $draft->id,
                    'error_type' => $e::class,
                ]);
            } catch (\Throwable) {
                // Logging must never break queue processing.
            }

            return;
        }

        $success = (bool) ($result['success'] ?? false);

        $draft->update([
            'content' => $success ? ($result['answer'] ?? null) : null,
            'provider' => $result['provider'] ?? null,
            'model' => $result['model'] ?? null,
            'status' => $success ? AiContentDraft::STATUS_READY : AiContentDraft::STATUS_FAILED,
            'error_code' => $result['error_code'] ?? null,
            'generated_at' => now(),
            'meta' => [
                'request_id' => $result['request_id'] ?? null,
                'confidence' => $result['confidence'] ?? null,
                'feature' => $result['feature'] ?? null,
            ],
        ]);
    }
}
