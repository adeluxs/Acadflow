<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'status',
        'error_message',
        'attempted_at',
        'attempt_count',
        'uuid', 'delivery_key', 'push_subscription_id', 'queued_at', 'accepted_at', 'delivered_at',
        'next_attempt_at', 'processing_started_at', 'processing_token', 'clicked_at', 'opened_at', 'latency_ms', 'provider_message_id',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
        'queued_at' => 'datetime', 'accepted_at' => 'datetime', 'delivered_at' => 'datetime',
        'next_attempt_at' => 'datetime', 'processing_started_at' => 'datetime', 'clicked_at' => 'datetime', 'opened_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
