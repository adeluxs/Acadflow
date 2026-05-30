<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Show all notifications for the user
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = $user->notifications()->with('user');

        // Filter by read/unread
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'unread':
                    $query->whereNull('read_at');
                    break;
                case 'read':
                    $query->whereNotNull('read_at');
                    break;
            }
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();

        // Group by type for sidebar
        $typeCounts = $user->notifications()
            ->selectRaw('type, COUNT(*) as count')
            ->selectRaw('SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return view('notifications.index', compact('notifications', 'unreadCount', 'typeCounts'));
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(Notification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->update(['read_at' => now()]);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Marked as read']);
        }

        return back();
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'All marked as read']);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Get unread count (AJAX)
     */
    public function unreadCount()
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Delete notification
     */
    public function destroy(Notification $notification)
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }

        $notification->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return back()->with('success', 'Notification deleted.');
    }

    /**
     * Clear all notifications
     */
    public function clearAll()
    {
        $user = Auth::user();
        $user->notifications()->delete();

        if (request()->wantsJson()) {
            return response()->json(['message' => 'All cleared']);
        }

        return back()->with('success', 'All notifications cleared.');
    }

    /**
     * Show notification settings
     */
    public function settings()
    {
        $user = Auth::user();
        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'email_enabled' => true,
                'push_enabled' => true,
                'submission_notifications' => true,
                'grade_notifications' => true,
                'attendance_notifications' => true,
                'billing_notifications' => true,
            ]
        );

        return view('notifications.settings', compact('settings'));
    }

    /**
     * Update notification settings
     */
    public function updateSettings(Request $request)
    {
        $user = Auth::user();
        $settings = NotificationSetting::firstOrCreate(['user_id' => $user->id]);

        $validated = $request->validate([
            'email_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'submission_notifications' => 'boolean',
            'grade_notifications' => 'boolean',
            'attendance_notifications' => 'boolean',
            'billing_notifications' => 'boolean',
        ]);

        $settings->update($validated);

        return back()->with('success', 'Notification settings updated.');
    }
}
