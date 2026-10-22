<?php

namespace App\Services;

use App\Models\DepartmentOnboardingDraft;
use App\Models\InstitutionOnboardingRequest;
use App\Models\Notification;
use App\Models\NotificationReminderState;
use App\Models\NotificationSetting;
use App\Models\ResearchMilestone;
use App\Models\ResearchProject;
use App\Models\Scopes\AcademicOrganisationBoundary;
use App\Models\Submission;
use App\Models\SubmissionTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationReminderService
{
    public function onboardingStage(User $user): ?array
    {
        if (! $user->onboarding_completed_at) {
            $progress = app(OnboardingService::class)->progress($user);
            if ($progress['mandatory_complete']) {
                return null;
            }
            $key = $progress['missing_required_steps'][0];
            $step = array_search($key, OnboardingService::STEPS, true);

            return ['stage' => 'Personal setup · step '.$step, 'url' => route('onboarding.show', ['step' => $step]), 'cycle' => 'personal:'.$key];
        }
        if ($draft = DepartmentOnboardingDraft::where('user_id', $user->id)->first()) {
            return ['stage' => 'Department registration · step '.$draft->step, 'url' => route('department.onboarding'), 'cycle' => 'department:'.$draft->id];
        }
        if ($user->account_type === 'university_representative' && ! InstitutionOnboardingRequest::where('requester_user_id', $user->id)->whereIn('status', ['pending', 'in_review', 'approved'])->exists()) {
            return ['stage' => 'Institution adoption request', 'url' => route('institution.onboarding.request'), 'cycle' => 'institution'];
        }
        $membership = $user->institutionAffiliations()->approved()->where('affiliation_type', 'university_admin')->with('university')->first();
        if ($membership && in_array($membership->university->adoption_status, ['claim_verified', 'setup_in_progress'], true)) {
            return ['stage' => 'Institution administrator setup', 'url' => route('institution.onboarding.request'), 'cycle' => 'institution:'.$membership->university_id];
        }

        return null;
    }

    public function unfinishedWork(User $user): ?array
    {
        $draft = Submission::where('user_id', $user->id)->where('status', 'draft')->latest('id')->first(['id', 'uuid']);
        if ($draft) {
            return ['stage' => 'An unfinished academic submission', 'url' => route('submissions.show', $draft)];
        }
        $project = ResearchProject::withoutGlobalScope(AcademicOrganisationBoundary::class)->where('owner_id', $user->id)->whereNotIn('status', ['completed', 'approved', 'archived', 'rejected'])->whereNull('archived_at')->latest('id')->first(['id', 'uuid']);
        if ($project) {
            return ['stage' => 'Your ongoing research', 'url' => route('research.show', $project)];
        }

        return null;
    }

    public function scanOnboarding(): void
    {
        User::withoutGlobalScopes()->where('is_active', true)->where('created_at', '<=', now()->subHours(12))
            ->where(fn ($q) => $q->whereNull('onboarding_completed_at')
                ->orWhereIn('id', DepartmentOnboardingDraft::select('user_id'))
                ->orWhereIn('account_type', ['university_representative'])
                ->orWhereHas('institutionAffiliations', fn ($memberships) => $memberships->approved()
                    ->where('affiliation_type', 'university_admin')
                    ->whereHas('university', fn ($institutions) => $institutions->whereIn('adoption_status', ['claim_verified', 'setup_in_progress']))))
            ->where(fn ($q) => $q->whereNull('last_onboarding_reminder_at')->orWhere('last_onboarding_reminder_at', '<=', now()->subHours(12)))
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->remindOnboarding($user);
                }
            });
        $this->scanProfileSuggestions();
    }

    public function profileSuggestion(User $user): ?array
    {
        if (! $user->onboarding_completed_at || ($user->notification_preferences['personalized_recommendations'] ?? true) === false) {
            return null;
        }
        $progress = app(OnboardingService::class)->progress($user);
        if ($progress['banner_dismissed']) {
            return null;
        }
        foreach ($progress['suggestions'] as $key => $details) {
            if (in_array($key, ['path', 'review'], true) || in_array($key, $progress['missing_required_steps'], true)) {
                continue;
            }

            return ['step' => $key, 'url' => $details['url']];
        }

        return null;
    }

    public function scanProfileSuggestions(): void
    {
        if (! app(NotificationPreferences::class)->option('engagement_enabled')) {
            return;
        }
        User::withoutGlobalScopes()->where('is_active', true)->whereNotNull('onboarding_completed_at')
            ->where('onboarding_completed_at', '<=', now()->subDays(14))
            ->whereIn('id', NotificationSetting::where('engagement_reminders', true)->select('user_id'))
            ->chunkById(100, function ($users): void {
                foreach ($users as $candidate) {
                    $this->remindProfile($candidate);
                }
            });
    }

    public function remindProfile(User $candidate): void
    {
        DB::transaction(function () use ($candidate): void {
            $user = User::withoutGlobalScopes()->lockForUpdate()->find($candidate->id);
            if (! $user || ! $user->is_active || ! $user->onboarding_completed_at || $user->onboarding_completed_at->gt(now()->subDays(14))) {
                return;
            }
            $preferences = app(NotificationPreferences::class);
            $settings = $preferences->settings($user);
            if (! $preferences->option('engagement_enabled') || ! $preferences->allows($settings, 'profile_completion_suggestion', 'engagement') || $preferences->quietUntil($settings)) {
                return;
            }
            if (! ($suggestion = $this->profileSuggestion($user))) {
                return;
            }
            // One gentle suggestion per section, and never more often than every two weeks
            // across sections. Delivery continues to use normal channel and daily limits.
            if (NotificationReminderState::where('user_id', $user->id)->where('kind', 'profile')
                ->where('last_sent_at', '>', now()->subDays(14))->exists()) {
                return;
            }
            $cycle = hash('sha256', $suggestion['step']);
            $state = NotificationReminderState::firstOrCreate(['user_id' => $user->id, 'kind' => 'profile', 'cycle_key' => $cycle]);
            if ($state->sent_count >= 1) {
                return;
            }
            $notice = app(NotificationService::class)->publish($user, 'profile_completion_suggestion', 'Make your profile more useful',
                'You can add optional academic details whenever it suits you.', [
                    'url' => $suggestion['url'], 'profile_step' => $suggestion['step'], 'priority' => 'low',
                    'event_key' => 'profile:'.$cycle.':1',
                ]);
            if ($notice) {
                $state->update(['sent_count' => 1, 'last_sent_at' => now(), 'stage' => $suggestion['step']]);
            }
        }, 3);
    }

    public function remindOnboarding(User $candidate): void
    {
        DB::transaction(function () use ($candidate): void {
            $user = User::withoutGlobalScopes()->lockForUpdate()->find($candidate->id);
            if (! $user || ! $user->is_active || ! ($stage = $this->onboardingStage($user))) {
                return;
            }
            if ($user->created_at->gt(now()->subHours(12)) || ($user->last_onboarding_reminder_at && Carbon::parse($user->last_onboarding_reminder_at)->gt(now()->subHours(12)))) {
                return;
            }
            $preferences = app(NotificationPreferences::class);
            $settings = $preferences->settings($user);
            if (! $preferences->allows($settings, 'onboarding_reminder', 'onboarding') || $preferences->quietUntil($settings)) {
                return;
            }
            $cycle = hash('sha256', $stage['cycle']);
            $state = NotificationReminderState::firstOrCreate(['user_id' => $user->id, 'kind' => 'onboarding', 'cycle_key' => $cycle]);
            if ($state->sent_count >= max(1, (int) $preferences->option('onboarding_max_reminders')) || $state->next_due_at?->isFuture()) {
                return;
            }
            $notice = app(NotificationService::class)->publish($user, 'onboarding_reminder', "You're almost there", 'Complete '.$stage['stage'].' to unlock your personalised academic workspace.', [
                'url' => $stage['url'], 'stage' => $stage['stage'], 'onboarding_cycle' => $stage['cycle'],
                'event_key' => 'onboarding:'.$cycle.':'.($state->sent_count + 1),
            ]);
            if (! $notice) {
                return;
            }
            $count = $state->sent_count + 1;
            $hours = max(12, (int) $preferences->option('onboarding_interval_hours')) * ($count >= 3 ? 4 : 1);
            $state->update(['sent_count' => $count, 'last_sent_at' => now(), 'stage' => $stage['stage'], 'next_due_at' => now()->addHours($hours)]);
            $user->forceFill(['last_onboarding_reminder_at' => now()])->saveQuietly();
        }, 3);
    }

    public function scanInactivity(): void
    {
        if (! app(NotificationPreferences::class)->option('engagement_enabled')) {
            return;
        }
        User::withoutGlobalScopes()->where('is_active', true)->whereNotNull('onboarding_completed_at')
            ->where(fn ($q) => $q->where('last_meaningful_activity_at', '<=', now()->subDay())->orWhere(fn ($n) => $n->whereNull('last_meaningful_activity_at')->where('created_at', '<=', now()->subDay())))
            ->whereIn('id', NotificationSetting::where('engagement_reminders', true)->select('user_id'))
            ->chunkById(100, function ($users): void {
                foreach ($users as $candidate) {
                    DB::transaction(function () use ($candidate): void {
                        $user = User::withoutGlobalScopes()->lockForUpdate()->findOrFail($candidate->id);
                        $last = $user->last_meaningful_activity_at ? Carbon::parse($user->last_meaningful_activity_at) : $user->created_at;
                        $hours = $last->diffInHours(now());
                        if ($hours < 24) {
                            return;
                        }
                        $settings = app(NotificationPreferences::class)->settings($user);
                        if (! app(NotificationPreferences::class)->allows($settings, 'inactivity_reminder', 'engagement') || app(NotificationPreferences::class)->quietUntil($settings)) {
                            return;
                        }
                        $work = $this->unfinishedWork($user);
                        if (! $work) {
                            return;
                        }
                        $cycle = hash('sha256', $last->toISOString());
                        $state = NotificationReminderState::firstOrCreate(['user_id' => $user->id, 'kind' => 'inactivity', 'cycle_key' => $cycle]);
                        $thresholds = [24, 48, 168, 336, 672];
                        if ($state->sent_count >= count($thresholds) || $hours < $thresholds[$state->sent_count] || $state->next_due_at?->isFuture()) {
                            return;
                        }
                        $sent = app(NotificationService::class)->publish($user, 'inactivity_reminder', 'Your academic workspace is ready', 'Continue '.$work['stage'].' when it suits you.', [
                            'url' => $work['url'], 'stage' => $work['stage'], 'activity_reference' => $last->toISOString(),
                            'event_key' => 'inactivity:'.$cycle.':'.$state->sent_count,
                        ]);
                        if ($sent) {
                            $next = $thresholds[$state->sent_count + 1] ?? 1440;
                            $state->update(['sent_count' => $state->sent_count + 1, 'last_sent_at' => now(), 'stage' => $work['stage'], 'next_due_at' => $last->copy()->addHours($next)]);
                        }
                    }, 3);
                }
            });
    }

    public function scanDeadlines(): void
    {
        $lead = max(1, min(168, (int) SettingService::getGlobal('assignment_reminder_hours', 24)));
        SubmissionTask::where('status', 'published')->where('is_visible_to_students', true)
            ->whereRaw('COALESCE(close_at, due_date) BETWEEN ? AND ?', [now()->subDays(7), now()->addHours($lead)])
            ->with('course:id,uuid')->chunkById(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    if (! $task->course) {
                        continue;
                    }
                    $due = $task->close_at ?? $task->due_date;
                    $type = $due->isPast() ? 'overdue_submission' : 'deadline_approaching';
                    User::withoutGlobalScopes()->where('is_active', true)->whereHas('enrollments', fn ($q) => $q->where('course_id', $task->course_id)->where('semester_id', $task->semester_id)->where('status', 'enrolled'))
                        ->whereDoesntHave('submissions', fn ($q) => $q->where('submission_task_id', $task->id))
                        ->chunkById(100, function ($users) use ($task, $due, $type): void {
                            foreach ($users as $user) {
                                app(NotificationService::class)->send($user, $type, 'Academic deadline update', $type === 'overdue_submission' ? 'An assignment deadline has passed. Review the available next steps.' : 'An academic assignment deadline is approaching.', [
                                    'task_id' => $task->id, 'deadline_reference' => $due->toISOString(), 'url' => route('submission-tasks.student.show', ['course' => $task->course, 'task' => $task]),
                                    'event_key' => 'deadline:'.$task->id.':'.$due->toISOString().':'.$type,
                                ]);
                            }
                        });
                }
            });
        ResearchMilestone::whereNull('completed_at')->whereNotIn('status', ['completed', 'cancelled'])->whereBetween('due_at', [now(), now()->addDay()])->chunkById(100, function ($milestones): void {
            $projects = ResearchProject::withoutGlobalScope(AcademicOrganisationBoundary::class)->whereIn('id', $milestones->pluck('research_project_id'))->get()->keyBy('id');
            $owners = User::withoutGlobalScopes()->whereIn('id', $projects->pluck('owner_id'))->get()->keyBy('id');
            foreach ($milestones as $milestone) {
                $project = $projects->get($milestone->research_project_id);
                $user = $project ? $owners->get($project->owner_id) : null;
                if ($user && ! $project->archived_at) {
                    app(NotificationService::class)->send($user, 'research_milestone_due', 'Research milestone approaching', 'Review your next research milestone in Research Studio.', ['milestone_id' => $milestone->id, 'url' => route('research.show', $project), 'event_key' => 'milestone:'.$milestone->id.':'.$milestone->due_at->toISOString()]);
                }
            }
        });
    }

    public function stillRelevant(Notification $notice, User $user): bool
    {
        if ($notice->category === 'onboarding') {
            $stage = $this->onboardingStage($user);

            return $stage && ($notice->data['onboarding_cycle'] ?? '') === $stage['cycle'] && ($notice->data['stage'] ?? '') === $stage['stage'];
        }
        if ($notice->type === 'profile_completion_suggestion') {
            $preferences = app(NotificationPreferences::class);
            if (! $preferences->allows($preferences->settings($user), $notice->type, 'engagement')) {
                return false;
            }
            $suggestion = $this->profileSuggestion($user);

            return $suggestion && $suggestion['step'] === ($notice->data['profile_step'] ?? null);
        }
        if ($notice->category === 'engagement') {
            $reference = $notice->data['activity_reference'] ?? null;

            return $reference && (! $user->last_meaningful_activity_at || Carbon::parse($user->last_meaningful_activity_at)->lte(Carbon::parse($reference))) && $this->unfinishedWork($user) !== null;
        }
        if (in_array($notice->type, ['overdue_submission', 'deadline_approaching'], true) && isset($notice->data['deadline_reference'])) {
            $task = SubmissionTask::find($notice->data['task_id'] ?? 0);

            return $task && $task->status === 'published' && $task->is_visible_to_students && ($task->close_at ?? $task->due_date)?->toISOString() === $notice->data['deadline_reference']
                && ! Submission::where('user_id', $user->id)->where('submission_task_id', $task->id)->exists()
                && $user->enrollments()->where('course_id', $task->course_id)->where('semester_id', $task->semester_id)->where('status', 'enrolled')->exists();
        }
        if ($notice->type === 'research_milestone_due') {
            $milestone = ResearchMilestone::find($notice->data['milestone_id'] ?? 0);

            return $milestone && ! $milestone->completed_at && ! in_array($milestone->status, ['completed', 'cancelled'], true);
        }

        return true;
    }
}
