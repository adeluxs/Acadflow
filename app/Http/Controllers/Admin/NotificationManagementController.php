<?php

namespace App\Http\Controllers\Admin;

use App\Events\SystemAnnouncementBroadcast;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationManagementController extends Controller
{
    /**
     * Show notification admin dashboard
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        // Channel settings
        $channels = [
            'notifications_email_enabled' => Setting::where('key', 'notifications_email_enabled')->first()?->value ?? '1',
            'notifications_push_enabled' => Setting::where('key', 'notifications_push_enabled')->first()?->value ?? '1',
            'notifications_in_app_enabled' => Setting::where('key', 'notifications_in_app_enabled')->first()?->value ?? '1',
        ];

        // Delivery stats (last 30 days)
        $stats = [
            'total' => NotificationLog::where('created_at', '>=', now()->subDays(30))->count(),
            'success' => NotificationLog::where('created_at', '>=', now()->subDays(30))->where('status', 'success')->count(),
            'failed' => NotificationLog::where('created_at', '>=', now()->subDays(30))->where('status', 'failed')->count(),
            'by_channel' => NotificationLog::where('created_at', '>=', now()->subDays(30))
                ->selectRaw('channel, COUNT(*) as count')
                ->groupBy('channel')
                ->pluck('count', 'channel')
                ->toArray(),
        ];

        $recentFailures = NotificationLog::where('status', 'failed')
            ->with(['notification', 'user'])
            ->latest()
            ->limit(20)
            ->get();

        return view('admin.notifications.index', compact('channels', 'stats', 'recentFailures'));
    }

    /**
     * Update channel settings (enable/disable channels globally)
     */
    public function updateChannels(Request $request)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'notifications_email_enabled' => 'boolean',
            'notifications_push_enabled' => 'boolean',
            'notifications_in_app_enabled' => 'boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value ? '1' : '0',
                    'type' => 'boolean',
                    'group' => 'notification',
                ]
            );
        }

        return back()->with('success', 'Notification channel settings updated.');
    }

    /**
     * Show system announcement form
     */
    public function announce()
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $departments = Department::where('is_active', true)->get();

        return view('admin.notifications.announce', compact('departments'));
    }

    /**
     * Send system announcement to users
     */
    public function sendAnnouncement(Request $request)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'target' => 'required|in:all,students,lecturers,department',
            'department_id' => 'required_if:target,department|exists:departments,id',
        ]);

        $query = User::query();

        switch ($validated['target']) {
            case 'students':
                $query->where('role', 'student');
                break;
            case 'lecturers':
                $query->where('role', 'lecturer');
                break;
            case 'department':
                $query->where('department_id', $validated['department_id']);
                break;
        }

        // Scope by admin role
        if ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $query->where('university_id', $user->university_id);
        }

        $recipients = $query->get();

        if ($recipients->count() === 0) {
            return back()->with('error', 'No recipients match the criteria.');
        }

        event(new SystemAnnouncementBroadcast($validated['title'], $validated['message'], $recipients, $user));

        return redirect()->route('admin.notifications.index')
            ->with('success', "System announcement sent to {$recipients->count()} users.");
    }

    /**
     * Retry failed push notifications
     */
    public function retryFailed()
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $failed = NotificationLog::where('status', 'failed')
            ->where('channel', 'push')
            ->where('attempt_count', '<', 3)
            ->with(['notification.user'])
            ->get();

        $pushService = app(PushNotificationService::class);
        $retried = 0;

        foreach ($failed as $log) {
            $notification = $log->notification;
            if (! $notification || ! $notification->user) {
                continue;
            }

            $success = $pushService->send(
                $notification->user,
                $notification->title,
                $notification->message,
                $notification->data ?? []
            );

            $log->increment('attempt_count');
            $log->update([
                'status' => $success > 0 ? 'success' : 'failed',
                'attempted_at' => now(),
            ]);

            if ($success > 0) {
                $retried++;
            }
        }

        return back()->with('success', "Retried {$failed->count()} failed notifications. {$retried} succeeded.");
    }
}
