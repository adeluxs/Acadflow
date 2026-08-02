<?php

namespace App\Events;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a submission is submitted (or manually requested) so AI analysis
 * can run asynchronously without blocking the request lifecycle.
 */
class SubmissionAiAnalysisRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Submission $submission,
        public ?User $user = null,
        public array $features = ['submission_validator', 'plagiarism'],
    ) {}
}
