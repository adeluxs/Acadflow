<?php

namespace App\Jobs;

use App\Ai\Features\PlagiarismModule;
use App\Ai\Features\SubmissionValidatorModule;
use App\Models\AiAnalysis;
use App\Models\Submission;
use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Runs AI analysis for a submission in the background.
 *
 * Creates an AiAnalysis record per feature, runs the relevant module through the
 * centralized AiManager, and updates the record with the result. On failure the
 * record is marked failed (never exposes an error to the user).
 */
class ProcessSubmissionAiAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public Submission $submission,
        public ?User $user,
        public string $feature,
    ) {}

    public function handle(
        SubmissionValidatorModule $validator,
        PlagiarismModule $plagiarism,
        \App\Ai\AiManager $manager,
    ): void {
        // Avoid provider calls/cost while the user-facing AI Assistant is paused.
        if (FeatureAccessService::effectiveStatus('ai_assistant', $this->user?->university_id) !== FeatureAccessService::STATUS_ENABLED) {
            return;
        }

        // Invalidate any cached result for this submission so a re-run always
        // reflects the latest document/version (Phase 7).
        $manager->invalidateScope($this->submission->uuid);

        $analysis = AiAnalysis::firstOrCreate(
            [
                'submission_id' => $this->submission->id,
                'feature' => $this->feature,
                'status' => 'queued',
            ],
            [
                'uuid' => Str::uuid(),
                'user_id' => $this->user?->id,
            ]
        );

        $analysis->update(['status' => 'processing', 'started_at' => now(), 'attempts' => $analysis->attempts + 1]);

        try {
            $response = match ($this->feature) {
                'submission_validator' => $validator->validate($this->submission, $this->user),
                'plagiarism' => $plagiarism->analyze($this->submission, $this->user),
                default => null,
            };

            if (! $response) {
                $analysis->markFailed();

                return;
            }

            $analysis->markCompleted(
                source: $response->source,
                score: $response->score,
                issues: $response->issues,
                summary: $response->summary,
                data: $response->data,
            );
        } catch (\Throwable $e) {
            report($e);
            $analysis->markFailed();
        }
    }
}
