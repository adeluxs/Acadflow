<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'endpoint_hash',
        'user_id',
        'endpoint',
        'keys_p256dh',
        'keys_auth',
        'user_agent',
        'ip_address',
        'expires_at',
    ];

    protected $hidden = ['keys_p256dh', 'keys_auth', 'endpoint_hash'];

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            $subscription->endpoint_hash = hash('sha256', $subscription->endpoint);
        });
    }

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is still valid
     */
    public function isValid(): bool
    {
        return is_null($this->expires_at) || $this->expires_at->isFuture();
    }
}
