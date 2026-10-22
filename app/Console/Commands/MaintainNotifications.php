<?php

namespace App\Console\Commands;

use App\Jobs\DeliverNotification;
use App\Models\NotificationEvent;
use App\Models\NotificationLog;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationPreferences;
use App\Services\NotificationService;
use App\Services\NotificationTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaintainNotifications extends Command
{
    protected $signature = 'notifications:maintain {--summary : Send the opted-in daily operational summary}';
    protected $description = 'Recover notification outbox dispatch, monitor failures and prune retained analytics in bounded batches';

    public function handle(): int
    {
        // An expired processing lease has an uncertain provider outcome. Never
        // automatically resend it: an administrator must reconcile it first.
        NotificationLog::where('status', 'processing')->where('processing_started_at', '<', now()->subMinutes(3))->update(['status' => 'unknown', 'error_message' => 'provider_outcome_requires_reconciliation', 'processing_token' => null]);
        NotificationLog::whereIn('status', ['queued', 'deferred', 'retrying'])->where('attempt_count', '<', 5)
            ->where('next_attempt_at', '<=', now())->select('id')->limit(1000)->chunkById(100, function ($logs): void {
                foreach ($logs as $log) DeliverNotification::dispatch($log->id);
            });
        PushSubscription::whereNotNull('expires_at')->where('expires_at', '<=', now())->with('user')->chunkById(100, function ($subscriptions): void {
            foreach ($subscriptions as $subscription) {
                app(NotificationTrackingService::class)->record('push_expired', $subscription->user, key: 'expired:'.$subscription->endpoint_hash);
                $subscription->delete();
            }
        });
        $preferences = app(NotificationPreferences::class);
        $stats = NotificationLog::where('created_at', '>=', now()->subHour())->whereIn('channel', ['email', 'push'])
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN accepted_at IS NOT NULL THEN 1 ELSE 0 END) AS accepted')
            ->selectRaw("SUM(CASE WHEN status IN ('failed','bounced','complained','retrying','unknown') THEN 1 ELSE 0 END) AS failed")->first();
        $alert = $stats->total >= (int) $preferences->option('failure_alert_minimum') && 100 * $stats->failed / max(1, $stats->total) >= (int) $preferences->option('failure_alert_percent');
        if ($alert) Log::warning('Notification failure threshold exceeded', ['window_minutes' => 60, 'total' => (int) $stats->total, 'failed' => (int) $stats->failed]);
        if ($alert || ($this->option('summary') && $preferences->option('summary_reports_enabled'))) {
            User::withoutGlobalScopes()->where('role', 'super_admin')->where('is_active', true)->chunkById(100, function ($admins) use ($alert, $stats): void {
                foreach ($admins as $admin) app(NotificationService::class)->send($admin, $alert ? 'notification_delivery_alert' : 'notification_delivery_summary', $alert ? 'Notification delivery needs attention' : 'Notification operations summary',
                    $stats->total.' recent external deliveries; '.$stats->accepted.' accepted; '.$stats->failed.' failures or retries. Review the live dashboard.',
                    ['url' => route('admin.notifications.analytics'), 'event_key' => ($alert ? 'delivery-alert:'.now()->format('Y-m-d-H') : 'delivery-summary:'.now()->toDateString())]);
            });
        }
        $cutoff = now()->subDays(max(30, (int) $preferences->option('retention_days')));
        foreach (['notification_events', 'notification_logs'] as $table) {
            $ids = DB::table($table)->where('created_at', '<', $cutoff)->orderBy('id')->limit(1000)->pluck('id');
            foreach ($ids->chunk(250) as $batch) DB::table($table)->whereIn('id', $batch)->delete();
        }
        $ids = DB::table('user_activity_days')->where('activity_date', '<', $cutoff->toDateString())->orderBy('id')->limit(1000)->pluck('id');
        foreach ($ids->chunk(250) as $batch) DB::table('user_activity_days')->whereIn('id', $batch)->delete();
        $this->info('Notification maintenance completed. Provider secrets and message bodies were excluded from monitoring.');

        return self::SUCCESS;
    }
}
