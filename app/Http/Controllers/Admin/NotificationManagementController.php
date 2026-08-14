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

        return view('admin.notifications.index', compact('channels', 'stats', 'recentFailures'));
    }

    public function updateChannels(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

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

        $recipients = $query->get();
        if ($recipients->isEmpty()) return back()->with('error', 'No recipients match the criteria.');

        event(new SystemAnnouncementBroadcast($validated['title'], $validated['message'], $recipients, $user));

        return redirect()->route('admin.notifications.index')
            ->with('success', "System announcement sent to {$recipients->count()} users.");
    }

    public function retryFailed()
    {
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);

        $failed = $this->scopeLogs(NotificationLog::query(), $user)
            ->where('status', 'failed')
            ->where('channel', 'push')
            ->where('attempt_count', '<', 3)
            ->with(['notification.user'])
            ->get();

        $pushService = app(PushNotificationService::class);
        $retried = 0;
        foreach ($failed as $log) {
            $notification = $log->notification;
            if (! $notification?->user) continue;

            $success = $pushService->send($notification->user, $notification->title, $notification->message, $notification->data ?? []);
            $log->increment('attempt_count');
            $log->update(['status' => $success > 0 ? 'success' : 'failed', 'attempted_at' => now()]);
            if ($success > 0) $retried++;
        }

        return back()->with('success', "Retried {$failed->count()} failed notifications. {$retried} succeeded.");
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
