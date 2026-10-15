<?php

namespace App\Jobs;

use App\Models\ResearchValidationReport;
use App\Services\ResearchValidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ValidateResearchProject implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(public ResearchValidationReport $report) {}

    public function handle(ResearchValidationService $validation): void
    {
        $validation->process($this->report->fresh());
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
        $this->report->update([
            'status' => 'failed',
            'summary' => 'Validation failed after retries. The research content remains safely available.',
            'findings' => ['error' => class_basename($exception)],
            'completed_at' => now(),
        ]);
    }
}
