<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudyPlan;
use App\Services\Ai\StudyPlanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued generation of a student's personal study plan.
 */
class GenerateStudyPlan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(private readonly int $planId) {}

    public function handle(StudyPlanService $service): void
    {
        $plan = StudyPlan::query()->with(['user', 'course.materials'])->find($this->planId);

        if (! $plan) {
            return;
        }

        if ($plan->status === StudyPlan::STATUS_READY) {
            return;
        }

        if($plan->user && $plan->course){
            try{app(\App\Services\AcademicOrganisationService::class)->restoreCourseActor($plan->user,$plan->course,'student');}
            catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface){$plan->update(['status'=>StudyPlan::STATUS_FAILED,'error_code'=>'ACADEMIC_MEMBERSHIP_UNAVAILABLE']);return;}
        }

        $service->generateInto($plan);
    }
}
