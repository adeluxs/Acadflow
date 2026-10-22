<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;
use Carbon\CarbonImmutable;

class NotificationPreferences
{
    public function category(string $type): string
    {
        return match (true) {
            str_contains($type, 'onboarding_reminder') => 'onboarding',
            str_starts_with($type, 'inactivity_'), $type === 'weekly_summary' => 'engagement',
            str_contains($type, 'department'), str_contains($type, 'institution'), str_starts_with($type, 'academic_membership'), str_contains($type, 'commercial_account'), str_starts_with($type, 'notification_delivery_') => 'organisation',
            str_contains($type, 'research'), str_contains($type, 'supervis'), str_contains($type, 'milestone') => 'research',
            str_contains($type, 'payment'), str_contains($type, 'billing'), str_contains($type, 'access_changed') => 'billing',
            default => 'academic',
        };
    }

    public function settings(User $user): ?NotificationSetting
    {
        return NotificationSetting::where('user_id', $user->id)->first() ?? new NotificationSetting($this->defaults($user));
    }

    public function defaults(User $user): array
    {
        $profile = $user->notification_preferences ?? [];

        return ['email_enabled' => $profile['email'] ?? true, 'in_app_enabled' => $profile['in_app'] ?? true,
            'research_updates' => $profile['research_updates'] ?? true, 'academic_alerts' => $profile['event_reminders'] ?? true,
            'push_enabled' => true, 'engagement_reminders' => false, 'email_open_tracking' => false];
    }

    public function allows(?NotificationSetting $settings, string $type, string $category): bool
    {
        $field = match ($category) {
            'onboarding' => 'onboarding_reminders', 'engagement' => 'engagement_reminders',
            'organisation' => 'organisation_updates', 'research' => 'research_updates',
            'billing' => 'billing_notifications', default => 'academic_alerts',
        };
        if (! ($settings?->$field ?? ($category !== 'engagement'))) return false;
        $specific = match ($type) {
            'grade_posted', 'feedback_posted' => 'grade_notifications',
            'attendance_started', 'attendance_closed' => 'attendance_notifications',
            'submission_received', 'submission_confirmation', 'correction_requested', 'approval_granted', 'overdue_submission' => 'submission_notifications',
            default => null,
        };

        return ! $specific || ($settings?->$specific ?? true);
    }

    public function quietUntil(?NotificationSetting $settings): ?CarbonImmutable
    {
        if (! $settings?->quiet_hours_start || ! $settings->quiet_hours_end || $settings->quiet_hours_start === $settings->quiet_hours_end) return null;
        $timezone = in_array($settings->timezone, timezone_identifiers_list(), true) ? $settings->timezone : 'UTC';
        $local = CarbonImmutable::now($timezone);
        $start = $local->setTimeFromTimeString($settings->quiet_hours_start);
        $end = $local->setTimeFromTimeString($settings->quiet_hours_end);
        if ($end <= $start) {
            if ($local < $end) $start = $start->subDay();
            else $end = $end->addDay();
        }

        return $local >= $start && $local < $end ? $end->setTimezone(config('app.timezone', 'UTC')) : null;
    }

    public function option(string $name): mixed
    {
        return SettingService::getGlobal('notification_'.$name, config('notification_delivery.'.$name));
    }

    public function safeUrl(?string $url): string
    {
        if (! $url) return route('notifications.index');
        $origin = parse_url(config('app.url'));
        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || str_contains($url, '\\') || preg_match('/[\x00-\x20]/', $url)) return route('notifications.index');
        if (isset($parts['host']) && (strtolower($parts['host']) !== strtolower($origin['host'] ?? '') || ($parts['port'] ?? null) !== ($origin['port'] ?? null))) return route('notifications.index');
        if (isset($parts['scheme']) && ! in_array($parts['scheme'], ['http', 'https'], true)) return route('notifications.index');
        if (! isset($parts['host']) && (! str_starts_with($url, '/') || str_starts_with($url, '//'))) return route('notifications.index');

        return isset($parts['host']) ? $url : rtrim(config('app.url'), '/').$url;
    }
}
