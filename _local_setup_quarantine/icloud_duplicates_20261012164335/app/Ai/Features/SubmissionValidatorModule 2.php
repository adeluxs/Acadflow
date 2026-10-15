<?php

namespace App\Ai\Features;

use App\Ai\AiManager;
use App\Ai\Contracts\AiResponse;
use App\Ai\Support\TextExtractor;
use App\Models\Submission;
use App\Models\User;
use App\Services\Ai\AiRuntimeConfigService;

/**
 * AI Submission Validator (Phase 11).
 *
 * Before a lecturer reviews a submission, this module automatically inspects it
 * for missing chapters, sections, formatting, citations, word count, deadlines
 * and institution-specific rules. Produces a validation report, readiness score,
 * prioritized corrections and fix suggestions.
 *
 * Integrates with the submission lifecycle via the centralized AiManager.
 */
class SubmissionValidatorModule
{
    public function __construct(
        protected AiManager $manager,
        protected TextExtractor $extractor,
        protected AiRuntimeConfigService $runtime,
    ) {}

    public function isEnabled(?int $universityId = null): bool
    {
        return $this->runtime->featureEnabled('submission_validator', $universityId);
    }

    /**
     * Validate a submission. Returns a standardized AiResponse.
     */
    public function validate(Submission $submission, ?User $user = null): AiResponse
    {
        if (! $this->isEnabled($user?->university_id ?? $submission->user?->university_id)) {
            return new AiResponse('disabled', 'submission_validator', false, summary: 'Submission validator disabled.');
        }

        $text = $this->currentVersionText($submission);

        $context = [
            'submission' => $submission,
            'course' => $submission->course,
            'type' => $submission->type,
            'text' => $text,
        ];

        return $this->manager->analyze(
            feature: 'submission_validator',
            payload: $context,
            user: $user,
            scope: $submission->uuid,
        );
    }

    protected function currentVersionText(Submission $submission): string
    {
        $version = $submission->versions()->where('is_current', true)->first()
            ?? $submission->versions()->latest()->first();

        if (! $version) {
            return $submission->description ?? '';
        }

        return $this->extractor->fromVersion($version);
    }
}
