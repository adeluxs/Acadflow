<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverNotification;
use App\Models\NotificationEventTemplate;
use App\Models\NotificationLog;
use App\Services\NotificationAnalyticsService;
use App\Services\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationAnalyticsController extends Controller
{
    private function filters(Request $request): array
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'channel' => ['nullable', Rule::in(['email', 'push', 'database'])],
            'role' => ['nullable', Rule::in(['super_admin', 'university_admin', 'department_admin', 'student', 'lecturer', 'member'])],
            'university_id' => ['nullable', 'integer', 'exists:universities,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'type' => ['nullable', 'string', 'max:100'], 'category' => ['nullable', Rule::in(array_keys(config('notification_delivery.categories')))],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($data['to'] ?? now()->toDateString())->endOfDay();
        abort_if($to < $from || $from->diffInDays($to) > 366, 422, 'Choose a valid date range of up to 366 days.');

        return array_merge($data, ['from' => $from, 'to' => $to]);
    }

    public function index(Request $request, NotificationAnalyticsService $analytics)
    {
        $filters = $this->filters($request);
        $report = $analytics->snapshot($filters, $request->boolean('fresh'));
        $failures = $analytics->deliveries($filters)->whereIn('status', ['failed', 'retrying', 'unknown', 'bounced', 'complained'])
            ->select(['id', 'uuid', 'channel', 'status', 'attempt_count', 'error_message', 'attempted_at', 'created_at'])->latest('id')->simplePaginate(25)->withQueryString();
        $templates = NotificationEventTemplate::orderBy('event_type')->simplePaginate(20, ['*'], 'templates_page')->withQueryString();
        $diagnostics = [
            'mail_driver' => config('mail.default'), 'smtp_credentials' => (bool) config('mail.mailers.smtp.username') && (bool) config('mail.mailers.smtp.password'),
            'push_configured' => app(\App\Services\WebPushConfiguration::class)->isConfigured(), 'queue_driver' => config('notification_delivery.connection'),
            'notification_queue_failures' => \Illuminate\Support\Facades\DB::table('failed_jobs')->where('payload', 'like', '%DeliverNotification%')->count(),
        ];
        if ($request->expectsJson()) return response()->json(compact('report', 'diagnostics'));

        return view('admin.notifications.analytics', compact('report', 'filters', 'failures', 'templates', 'diagnostics'));
    }

    public function export(Request $request, NotificationAnalyticsService $analytics)
    {
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($analytics, $filters): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['delivery_id', 'channel', 'status', 'attempts', 'queued_at', 'accepted_at', 'confirmed_at', 'processing_ms', 'clicked_at'], escape: '');
            foreach ($analytics->deliveries($filters)->select(['id', 'uuid', 'channel', 'status', 'attempt_count', 'queued_at', 'accepted_at', 'delivered_at', 'latency_ms', 'clicked_at'])->lazyById(250) as $row) {
                fputcsv($output, [$row->uuid ?? $row->id, $row->channel, $row->status, $row->attempt_count, $row->queued_at, $row->accepted_at, $row->delivered_at, $row->latency_ms, $row->clicked_at], escape: '');
            }
            fclose($output);
        }, 'acadsyntra-notification-deliveries.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'private, no-store']);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate([
            'onboarding_interval_hours' => ['required', 'integer', 'min:12', 'max:720'], 'onboarding_max_reminders' => ['required', 'integer', 'min:1', 'max:12'],
            'daily_email_limit' => ['required', 'integer', 'min:1', 'max:100'], 'daily_push_limit' => ['required', 'integer', 'min:1', 'max:200'],
            'retention_days' => ['required', 'integer', 'min:30', 'max:3650'], 'failure_alert_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'failure_alert_minimum' => ['required', 'integer', 'min:1', 'max:1000'], 'summary_reports_enabled' => ['required', 'boolean'], 'engagement_enabled' => ['required', 'boolean'],
        ]);
        foreach ($data as $key => $value) SettingService::set('notification_'.$key, $value, is_bool($value) || in_array($key, ['summary_reports_enabled', 'engagement_enabled']) ? 'boolean' : 'integer', null, $request->user()->id);
        \App\Models\AuditLog::log('notification_campaign_settings_updated', $request->user()->id, newValue: $data);

        return back()->with('success', 'Notification intervals and delivery limits saved.');
    }

    public function template(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $request->merge(['channels' => $request->input('channels', [])]);
        $data = $request->validate(['event_type' => ['required', 'regex:/^[a-z][a-z0-9_]{1,99}$/'], 'title' => ['required', 'string', 'max:255'], 'message' => ['required', 'string', 'max:2000'], 'enabled' => ['required', 'boolean'], 'channels' => ['present', 'array'], 'channels.*' => [Rule::in(['email', 'push'])]]);
        NotificationEventTemplate::updateOrCreate(['event_type' => $data['event_type']], $data);
        \App\Models\AuditLog::log('notification_template_updated', $request->user()->id, newValue: ['event_type' => $data['event_type'], 'enabled' => $data['enabled']]);

        return back()->with('success', 'Notification event template saved.');
    }

    public function retry(Request $request, NotificationLog $delivery)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $updated = NotificationLog::whereKey($delivery->id)->whereIn('status', ['failed', 'retrying'])->whereNull('accepted_at')->where('attempt_count', '<', 5)
            ->update(['status' => 'queued', 'next_attempt_at' => now(), 'processing_token' => null]);
        abort_unless($updated, 422, 'Only unaccepted, retryable deliveries below the attempt limit may be retried.');
        DeliverNotification::dispatch($delivery->id)->afterCommit();

        return back()->with('success', 'Eligible delivery queued for retry. Accepted deliveries were not resent.');
    }
}
