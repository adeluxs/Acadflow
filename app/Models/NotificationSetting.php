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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
