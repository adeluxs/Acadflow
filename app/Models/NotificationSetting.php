<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'email_enabled',
        'push_enabled',
        'submission_notifications',
        'grade_notifications',
        'attendance_notifications',
        'billing_notifications',
        'in_app_enabled', 'academic_alerts', 'organisation_updates', 'onboarding_reminders', 'research_updates',
        'engagement_reminders', 'email_open_tracking', 'timezone', 'quiet_hours_start', 'quiet_hours_end',
        'push_permission', 'push_prompt_snoozed_until', 'email_suppressed_at', 'email_suppression_reason',
    ];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'push_enabled' => 'boolean',
            'submission_notifications' => 'boolean',
            'grade_notifications' => 'boolean',
            'attendance_notifications' => 'boolean',
            'billing_notifications' => 'boolean',
            'in_app_enabled' => 'boolean', 'academic_alerts' => 'boolean', 'organisation_updates' => 'boolean',
            'onboarding_reminders' => 'boolean', 'research_updates' => 'boolean', 'engagement_reminders' => 'boolean',
            'email_open_tracking' => 'boolean', 'push_prompt_snoozed_until' => 'datetime', 'email_suppressed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
