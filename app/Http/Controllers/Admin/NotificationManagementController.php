<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Events\SystemAnnouncementBroadcast;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NotificationManagementController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);

        $channels = [
            'email_notifications_enabled' => SettingService::get('email_notifications_enabled', true, $user->university_id),
            'push_notifications_enabled' => SettingService::get('push_notifications_enabled', true, $user->university_id),
            'in_app_notifications_enabled' => SettingService::get('in_app_notifications_enabled', true, $user->university_id),
        ];

        $base = $this->scopeLogs(NotificationLog::query(), $user)->where('created_at', '>=', now()->subDays(30));
        $stats = [
            'total' => (clone $base)->count(),
            'success' => (clone $base)->where('status', 'success')->count(),
            'failed' => (clone $base)->where('status', 'failed')->count(),
            'by_channel' => (clone $base)->selectRaw('channel, COUNT(*) as count')->groupBy('channel')->pluck('count', 'channel')->toArray(),
        ];

        $recentFailures = $this->scopeLogs(NotificationLog::query(), $user)
            ->where('status', 'failed')
            ->with(['notification', 'user'])
            ->latest()
            ->limit(20)
            ->get();

        $pushConfigured = app(\App\Services\WebPushConfiguration::class)->isConfigured();
        return view('admin.notifications.index', compact('channels', 'stats', 'recentFailures', 'pushConfigured'));
    }

    public function generatePushKeys(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255']]);
        app(\App\Services\WebPushConfiguration::class)->generate($data['subject'], $request->user()->id);
        return back()->with('success', 'Browser delivery keys created. Enable the Push Notifications module and delivery channel, then enroll a browser in Notification Preferences.');
    }

    public function updateChannels(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->isUniversityAdmin(), 403);

        $validated = $request->validate([
            'email_notifications_enabled' => 'required|boolean',
            'push_notifications_enabled' => 'required|boolean',
            'in_app_notifications_enabled' => 'required|boolean',
        ]);

        $scope = $user->isSuperAdmin() ? null : $user->university_id;
        foreach ($validated as $key => $value) {
            SettingService::set($key, (bool) $value, 'boolean', $scope, $user->id);
        }

        return back()->with('success', 'Notification channel settings updated.');
    }

    public function announce()
    {
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);

        $departments = Department::query()
            ->where('is_active', true)
            ->when(! $user->isSuperAdmin(), fn (Builder $query) => $query->whereHas('faculty', fn (Builder $faculty) => $faculty->where('university_id', $user->university_id)))
            ->when($user->isDepartmentAdmin(), fn (Builder $query) => $query->whereKey($user->department_id))
            ->orderBy('name')
            ->get();

        return view('admin.notifications.announce', compact('departments'));
    }

    public function sendAnnouncement(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'target' => 'required|in:all,students,lecturers,department',
            'department_id' => [
                'required_if:target,department',
                'nullable',
                Rule::exists('departments', 'id')->where(function ($query) use ($user) {
                    if (! $user->isSuperAdmin()) {
                        $query->whereIn('faculty_id', function ($subquery) use ($user) {
                            $subquery->select('id')->from('faculties')->where('university_id', $user->university_id);
                        });
                    }
                    if ($user->isDepartmentAdmin()) $query->where('id', $user->department_id);
                }),
            ],
        ]);

        $query = $this->scopeUsers(User::query(), $user)->where('is_active', true);
        match ($validated['target']) {
            'students' => $query->where('role', 'student'),
            'lecturers' => $query->where('role', 'lecturer'),
            'department' => $query->where('department_id', $validated['department_id']),
            default => $query,
        };

        $recipientCount = (clone $query)->count();
        if ($recipientCount === 0) return back()->with('error', 'No recipients match the criteria.');

        // Queue bounded batches instead of serializing every recipient into one giant event/job.
        $query->select(User::notificationRecipientColumns())
            ->chunkById(250, function ($recipients) use ($validated, $user): void {
                event(new SystemAnnouncementBroadcast($validated['title'], $validated['message'], $recipients, $user));
            });

        return redirect()->route('admin.notifications.index')
            ->with('success', "System announcement queued for {$recipientCount} users.");
    }

    public function retryFailed()
    {
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);

        $query = $this->scopeLogs(NotificationLog::query(), $user)
            ->where('status', 'failed')
            ->whereIn('channel', ['push','email'])->whereNotNull('delivery_key')->whereNull('accepted_at')
            ->where('attempt_count', '<', 3)
            ->with(['notification.user']);

        $attempted = 0;
        $retried = 0;
        $query->chunkById(100, function ($failed) use (&$attempted, &$retried): void {
            foreach ($failed as $log) {
                $notification = $log->notification;
                if (! $notification?->user) continue;

                $attempted++;
                $queued = NotificationLog::whereKey($log->id)->where('status','failed')->whereNull('accepted_at')->update(['status'=>'queued','next_attempt_at'=>now()]);
                if ($queued) { \App\Jobs\DeliverNotification::dispatch($log->id)->afterCommit(); $retried++; }
            }
        });

        return back()->with('success', "Reviewed {$attempted} failed notifications. {$retried} eligible deliveries queued for retry.");
    }

    private function scopeUsers(Builder $query, User $user): Builder
    {
        if ($user->isDepartmentAdmin()) return $query->where('university_id', $user->university_id)->where('department_id', $user->department_id);
        if ($user->isUniversityAdmin()) return $query->where('university_id', $user->university_id);
        return $query;
    }

    private function scopeLogs(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) return $query;

        return $query->whereHas('user', function (Builder $users) use ($user): void {
            $users->where('university_id', $user->university_id);
            if ($user->isDepartmentAdmin()) $users->where('department_id', $user->department_id);
        });
    }
}
