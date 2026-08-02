<?php

namespace App\Ai\Rules;

use App\Models\Submission;
use App\Services\SubmissionValidator;

/**
 * Deadline Rule Pack - validates submission timing against task deadlines.
 */
class DeadlineRulePack extends BaseRulePack
{
    public function key(): string
    {
        return 'deadline';
    }

    public function label(): string
    {
        return 'Deadline & Timing';
    }

    public function analyze(array $context): array
    {
        $issues = [];
        $submission = $context['submission'] ?? null;

        if (! $submission instanceof Submission || ! $submission->task) {
            return $issues;
        }

        $validator = app(SubmissionValidator::class);

        if ($validator->isLate($submission)) {
            $minutes = $validator->minutesLate($submission);
            $issues[] = $this->issue(
                'late_submission',
                'This submission was made '.round($minutes / 60, 1).' hours after the deadline.',
                'warning',
                3,
                'Submit before the deadline to avoid late penalties.',
                'deadline'
            );
        }

        return $this->result($issues);
    }
}
