<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NotificationAnalyticsService
{
    public function snapshot(array $filters, bool $refresh = false): array
    {
        $key = 'notification.analytics.'.hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR));
        if ($refresh) \Illuminate\Support\Facades\Cache::forget($key);

        return \Illuminate\Support\Facades\Cache::remember($key, 60, fn () => array_merge($this->report($filters), ['as_of' => now()->toISOString()]));
    }

    public function users(array $filters): Builder
    {
        return User::withoutGlobalScopes()
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('role', $value))
            ->when($filters['university_id'] ?? null, fn ($q, $value) => $q->where('university_id', $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value));
    }

    public function notifications(array $filters): Builder
    {
        return Notification::whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('type', $value))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value))
            ->when($filters['university_id'] ?? null, fn ($q, $value) => $q->where('university_id', $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->whereIn('user_id', User::withoutGlobalScopes()->where('role', $value)->select('id')));
    }

    public function deliveries(array $filters): Builder
    {
        return NotificationLog::whereIn('notification_id', $this->notifications($filters)->select('id'))
            ->when($filters['channel'] ?? null, fn ($q, $value) => $q->where('channel', $value));
    }

    public function events(array $filters): Builder
    {
        return NotificationEvent::whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['channel'] ?? null, fn ($q, $value) => $q->where('channel', $value))
            ->when($filters['role'] ?? null, fn ($q, $value) => $q->where('role', $value))
            ->when($filters['university_id'] ?? null, fn ($q, $value) => $q->where('university_id', $value))
            ->when($filters['department_id'] ?? null, fn ($q, $value) => $q->where('department_id', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('notification_type', $value))
            ->when($filters['category'] ?? null, fn ($q, $value) => $q->where('category', $value));
    }

    public function report(array $filters): array
    {
        $deliveries = $this->deliveries($filters);
        $deliveryLatency = match (DB::getDriverName()) { 'sqlite' => '(julianday(accepted_at) - julianday(queued_at)) * 86400000.0', 'pgsql' => 'EXTRACT(EPOCH FROM (accepted_at - queued_at)) * 1000.0', default => 'TIMESTAMPDIFF(MICROSECOND, queued_at, accepted_at) / 1000.0' };
        $channels = (clone $deliveries)->select('channel')->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS confirmed")
            ->selectRaw("SUM(CASE WHEN status IN ('failed','bounced','complained') THEN 1 ELSE 0 END) AS failed")
            ->selectRaw("SUM(CASE WHEN status IN ('pending','queued','deferred') THEN 1 ELSE 0 END) AS queued")
            ->selectRaw("SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) AS retrying")
            ->selectRaw("SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing")
            ->selectRaw("SUM(CASE WHEN status = 'unknown' THEN 1 ELSE 0 END) AS unknown")
            ->selectRaw("SUM(CASE WHEN status = 'simulated' THEN 1 ELSE 0 END) AS simulated")
            ->selectRaw('SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicks')
            ->selectRaw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opens')
            ->selectRaw('AVG(latency_ms) AS processing_ms')->selectRaw('AVG('.$deliveryLatency.') AS delivery_ms')->groupBy('channel')->get()->keyBy('channel');
        $generated = $this->notifications($filters)->count();
        $eventCounts = $this->events($filters)->select('kind')->selectRaw('COUNT(*) AS total')->groupBy('kind')->pluck('total', 'kind');
        $deliveryTrend = (clone $deliveries)->selectRaw('DATE(created_at) AS day, channel, COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN accepted_at IS NOT NULL OR delivered_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted")
            ->selectRaw("SUM(CASE WHEN status IN ('failed','bounced','complained') THEN 1 ELSE 0 END) AS failed")->groupByRaw('DATE(created_at), channel')->orderBy('day')->get();
        $categoryTrend = $this->notifications($filters)->select('category')->selectRaw('COUNT(*) AS total, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked')->groupBy('category')->get();
        $subscriptionTrend = $this->events(array_diff_key($filters, ['channel' => true, 'type' => true, 'category' => true]))->where('kind', 'push_subscribed')->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $users = $this->users($filters);
        $subscriptions = PushSubscription::whereIn('user_id', (clone $users)->select('id'))->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $push = [
            'users' => (clone $subscriptions)->distinct()->count('user_id'), 'active' => (clone $subscriptions)->count(),
            'eligible_accounts' => (clone $users)->where('is_active', true)->count(),
            'declined' => \App\Models\NotificationSetting::whereIn('user_id', (clone $users)->select('id'))->where('push_permission', 'denied')->count(),
            'disabled' => \App\Models\NotificationSetting::whereIn('user_id', (clone $users)->select('id'))->where('push_enabled', false)->count(),
            'invalid_expired' => (int) ($eventCounts['push_expired'] ?? 0),
        ];
        $devices = (clone $subscriptions)->selectRaw("CASE WHEN LOWER(user_agent) LIKE '%edg%' THEN 'Edge' WHEN LOWER(user_agent) LIKE '%firefox%' THEN 'Firefox' WHEN LOWER(user_agent) LIKE '%chrome%' THEN 'Chrome' WHEN LOWER(user_agent) LIKE '%safari%' THEN 'Safari' ELSE 'Other' END AS browser")
            ->selectRaw("CASE WHEN LOWER(user_agent) LIKE '%mobile%' OR LOWER(user_agent) LIKE '%iphone%' OR LOWER(user_agent) LIKE '%ipad%' OR LOWER(user_agent) LIKE '%android%' THEN 'mobile' ELSE 'desktop' END AS device, COUNT(*) AS total")
            ->groupBy('browser', 'device')->get();
        $cohort = (clone $users)->whereBetween('created_at', [$filters['from'], $filters['to']]);
        $signupCohort = (clone $cohort)->select(['id', 'account_type', 'created_at', 'onboarding_completed_at'])->withExists(['notifications as reminded' => fn ($q) => $q->where('category', 'onboarding')->where('created_at', '<=', $filters['to'])->whereIn('id', NotificationLog::where(fn ($d) => $d->whereNotNull('accepted_at')->orWhere('status', 'delivered'))->select('notification_id'))]);
        $cohortRows = DB::query()->fromSub($signupCohort->toBase(), 'signup_cohort');
        $cohortStats = (clone $cohortRows)->selectRaw('COUNT(*) AS cohort_total, SUM(CASE WHEN onboarding_completed_at IS NOT NULL AND onboarding_completed_at <= ? THEN 1 ELSE 0 END) AS cohort_completed', [$filters['to']])
            ->selectRaw('SUM(CASE WHEN reminded THEN 1 ELSE 0 END) AS reminded, SUM(CASE WHEN NOT reminded THEN 1 ELSE 0 END) AS unreminded')
            ->selectRaw('SUM(CASE WHEN reminded AND onboarding_completed_at IS NOT NULL AND onboarding_completed_at <= ? THEN 1 ELSE 0 END) AS reminded_completed', [$filters['to']])
            ->selectRaw('SUM(CASE WHEN NOT reminded AND onboarding_completed_at IS NOT NULL AND onboarding_completed_at <= ? THEN 1 ELSE 0 END) AS unreminded_completed', [$filters['to']])->first();
        $onboarding = array_map(fn ($value) => (int) $value, (array) $cohortStats);
        $onboarding['incomplete'] = (clone $users)->whereNull('onboarding_completed_at')->count();
        $onboarding['completed_after_reminder'] = $this->events(array_diff_key($filters, ['channel' => true]))->where('kind', 'onboarding_completed')->whereNotNull('notification_id')->distinct()->count('user_id');
        $difference = match (DB::getDriverName()) { 'sqlite' => '(julianday(onboarding_completed_at) - julianday(created_at)) * 24.0', 'pgsql' => 'EXTRACT(EPOCH FROM (onboarding_completed_at - created_at)) / 3600.0', default => 'TIMESTAMPDIFF(SECOND, created_at, onboarding_completed_at) / 3600.0' };
        $onboarding['average_completion_hours'] = (clone $cohort)->whereNotNull('onboarding_completed_at')->where('onboarding_completed_at', '<=', $filters['to'])->avg(DB::raw($difference));
        $onboarding['by_account_type'] = (clone $cohortRows)->select('account_type')->selectRaw('COUNT(*) AS total, SUM(CASE WHEN onboarding_completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN reminded THEN 1 ELSE 0 END) AS reminded')->groupBy('account_type')->get();
        $onboarding['drop_off'] = \App\Models\UserOnboardingState::whereIn('user_id', (clone $users)->whereNull('onboarding_completed_at')->select('id'))->select('current_step')->selectRaw('COUNT(*) AS total')->groupBy('current_step')->get();
        $onboarding['department_drafts'] = \App\Models\DepartmentOnboardingDraft::whereIn('user_id', (clone $users)->select('id'))->select('step')->selectRaw('COUNT(*) AS total')->groupBy('step')->get();
        $completionTrend = $this->events(array_diff_key($filters, ['channel' => true, 'type' => true, 'category' => true]))->where('kind', 'onboarding_completed')->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $end = CarbonImmutable::parse($filters['to']);
        $activity = DB::table('user_activity_days')->whereIn('user_id', (clone $users)->select('id'));
        $active = [
            'daily' => (clone $activity)->where('activity_date', $end->toDateString())->distinct()->count('user_id'),
            'weekly' => (clone $activity)->whereBetween('activity_date', [$end->subDays(6)->toDateString(), $end->toDateString()])->distinct()->count('user_id'),
            'monthly' => (clone $activity)->whereBetween('activity_date', [$end->subDays(29)->toDateString(), $end->toDateString()])->distinct()->count('user_id'),
            'trend' => (clone $activity)->whereBetween('activity_date', [CarbonImmutable::parse($filters['from'])->toDateString(), $end->toDateString()])->select('activity_date')->selectRaw('COUNT(*) AS total')->groupBy('activity_date')->orderBy('activity_date')->get(),
        ];
        $inactivity = (clone $users)->where('is_active', true)->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN COALESCE(last_meaningful_activity_at, created_at) <= ? THEN 1 ELSE 0 END) AS hours24', [now()->subDay()])
            ->selectRaw('SUM(CASE WHEN COALESCE(last_meaningful_activity_at, created_at) <= ? THEN 1 ELSE 0 END) AS hours48', [now()->subDays(2)])
            ->selectRaw('SUM(CASE WHEN COALESCE(last_meaningful_activity_at, created_at) <= ? THEN 1 ELSE 0 END) AS days7', [now()->subDays(7)])
            ->selectRaw('SUM(CASE WHEN COALESCE(last_meaningful_activity_at, created_at) <= ? THEN 1 ELSE 0 END) AS days14', [now()->subDays(14)])->first();
        $returns = $this->events(array_diff_key($filters, ['channel' => true, 'type' => true, 'category' => true]))->where('kind', 'returned')->selectRaw('DATE(created_at) AS day, COUNT(DISTINCT user_id) AS total')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $returnChannels = NotificationLog::whereIn('notification_id', $this->events(array_diff_key($filters, ['channel' => true]))->where('kind', 'returned')->select('notification_id'))
            ->where(fn ($q) => $q->whereNotNull('accepted_at')->orWhereNotNull('delivered_at'))->select('channel')->selectRaw('COUNT(DISTINCT user_id) AS users')->groupBy('channel')->get();

        return compact('generated', 'channels', 'eventCounts', 'deliveryTrend', 'categoryTrend', 'subscriptionTrend', 'push', 'devices', 'onboarding', 'completionTrend', 'active', 'inactivity', 'returns', 'returnChannels');
    }
}
