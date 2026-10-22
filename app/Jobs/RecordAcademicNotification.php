<?php

namespace App\Jobs;

use App\Models\ContentComment;
use App\Models\CommercialAccount;
use App\Models\AiContentDraft;
use App\Models\DepartmentAdoptionRequest;
use App\Models\InstitutionAccessRequest;
use App\Models\InstitutionOnboardingRequest;
use App\Models\ResearchCorrection;
use App\Models\ResearchProject;
use App\Models\ResearchValidationReport;
use App\Models\StudyPlan;
use App\Models\SubmissionGrade;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RecordAcademicNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    public int $tries = 3;
    public array $backoff = [60, 300, 900];

    public function __construct(public string $modelClass, public int $modelId, public string $revision)
    {
        $this->onQueue('notifications');
        $this->onConnection(config('notification_delivery.connection', 'database'));
    }

    public function handle(NotificationService $notifications): void
    {
        $allowed = [ContentComment::class, SubmissionGrade::class, ResearchCorrection::class, ResearchProject::class, ResearchValidationReport::class, StudyPlan::class, AiContentDraft::class, CommercialAccount::class, DepartmentAdoptionRequest::class, InstitutionOnboardingRequest::class, InstitutionAccessRequest::class];
        if (! in_array($this->modelClass, $allowed, true)) return;
        $model = $this->modelClass::withoutGlobalScope(\App\Models\Scopes\AcademicOrganisationBoundary::class)->find($this->modelId);
        if (! $model) return;
        $recipientIds = [];
        $type = 'academic_update';
        $title = 'Academic workspace update';
        $message = 'There is an update to review in your AcadSyntra workspace.';
        $url = route('notifications.index');
        if ($model instanceof SubmissionGrade) {
            if (! $model->is_final || ! $model->submission) return;
            $recipientIds = [$model->submission->user_id];
            $type = 'grade_posted'; $title = 'Feedback and grading available'; $url = route('submissions.show', $model->submission);
        } elseif ($model instanceof ContentComment) {
            $owner = $model->document?->owner_id;
            if (! $owner || (int) $owner === (int) $model->user_id) return;
            $section = $model->document->researchSections()->with('project')->first();
            if (! $section?->project) return;
            $recipientIds = [$owner]; $type = 'research_supervisor_comment'; $title = 'New research feedback'; $url = route('research.show', $section->project);
        } elseif ($model instanceof ResearchCorrection) {
            if (! $model->project) return;
            $recipientIds = [$model->assigned_to ?: $model->project->owner_id];
            $type = 'research_correction'; $title = 'Research correction update'; $url = route('research.show', $model->project);
        } elseif ($model instanceof ResearchProject) {
            if (! in_array($model->status, ['submitted', 'under_review', 'corrections_requested', 'approved', 'rejected'], true)) return;
            $recipientIds = [$model->owner_id];
            if (in_array($model->status, ['submitted', 'under_review'], true)) $recipientIds[] = $model->supervisor_id;
            $type = 'research_status_updated'; $title = 'Research review updated'; $message = 'Research status: '.str_replace('_', ' ', $model->status).'.'; $url = route('research.show', $model);
        } elseif ($model instanceof ResearchValidationReport || $model instanceof StudyPlan || $model instanceof AiContentDraft) {
            if (! in_array($model->status, ['completed', 'ready'], true)) return;
            $recipientIds = [$model instanceof ResearchValidationReport ? $model->requested_by : $model->user_id];
            $type = 'research_background_task_ready'; $title = 'Your background academic task is ready';
            if ($model instanceof ResearchValidationReport) {
                $project = ResearchProject::withoutGlobalScopes()->find($model->research_project_id);
                if ($project) $url = route('research.show', $project);
            } else $url = route('dashboard');
        } elseif ($model instanceof InstitutionAccessRequest) {
            if ($model->status !== 'pending') return;
            $type = 'academic_membership_requested'; $title = 'Academic membership request awaiting review'; $url = route('admin.institution-access.index');
            $institutionMayReview = ! $model->department_id || \App\Models\Department::find($model->department_id)?->isInstitutionManaged();
            $adminIds = UserInstitutionAffiliation::approved()->where('university_id', $model->university_id)
                ->where(fn ($q) => $q->where(fn ($u) => $u->where('affiliation_type', 'university_admin')->whereNull('department_id')->when(! $institutionMayReview, fn ($p) => $p->whereRaw('1 = 0')))
                    ->orWhere(fn ($d) => $d->where('affiliation_type', 'department_admin')->where('department_id', $model->department_id)))->select('user_id');
            User::withoutGlobalScopes()->where('is_active', true)->where(fn ($q) => $q->where('role', 'super_admin')->orWhereIn('id', $adminIds))->chunkById(100, function ($users) use ($notifications, $type, $title, $message, $url, $model): void {
                foreach ($users as $user) {
                    $membership = $user->institutionAffiliations()->approved()->where('university_id', $model->university_id)->whereIn('affiliation_type', ['university_admin', 'department_admin'])->first();
                    $notifications->send($user, $type, $title, $message, ['url' => $url, 'university_id' => $model->university_id, 'department_id' => $model->department_id, 'affiliation_id' => $membership?->id, 'event_key' => $this->modelClass.':'.$this->modelId.':'.$this->revision]);
                }
            });
            return;
        } elseif ($model instanceof CommercialAccount) {
            UserInstitutionAffiliation::approved()->when($model->department_id, fn ($q) => $q->where('department_id', $model->department_id), fn ($q) => $q->where('university_id', $model->university_id))
                ->with(['user', 'university', 'department'])->chunkById(100, function ($memberships) use ($model, $notifications): void {
                    foreach ($memberships->unique('user_id') as $membership) {
                        $user = $membership->user;
                        if (! $user) continue;
                        $user->useAcademicAffiliation($membership);
                        if (! $model->department_id && ! app(\App\Services\AcademicOrganisationService::class)->hasInstitutionAccess($user, (int) $model->university_id)) continue;
                        $notifications->send($user, 'academic_access_changed', 'Academic sponsorship update', 'Your organisation sponsorship status changed. Personal purchases, prepaid balances and academic records remain available according to their terms.', ['url' => route('commerce.features'), 'event_key' => 'commercial:'.$model->id.':'.$this->revision]);
                    }
                });
            return;
        } else {
            $recipientIds = [$model->requester_user_id];
            $type = $model instanceof DepartmentAdoptionRequest ? 'department_verification_updated' : 'institution_verification_updated';
            $title = 'Organisation verification update'; $message = 'Application status: '.str_replace('_', ' ', $model->status).'.';
            $url = $model instanceof DepartmentAdoptionRequest ? route('department.onboarding') : route('institution.onboarding.request');
        }
        foreach (User::withoutGlobalScopes()->whereIn('id', array_filter(array_unique($recipientIds)))->where('is_active', true)->get() as $user) {
            $notifications->send($user, $type, $title, $message, ['url' => $url, 'event_key' => $this->modelClass.':'.$this->modelId.':'.$this->revision]);
        }
    }
}
