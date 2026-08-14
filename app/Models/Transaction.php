<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Transaction extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'payment_gateway_id',
        'transactionable_type',
        'transactionable_id',
        'amount',
        'currency',
        'type',
        'status',
        'gateway_transaction_id',
        'gateway_status',
        'metadata',
        'notes',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $transaction) => $transaction->uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function subscriptions(): BelongsToMany
    {
        return $this->belongsToMany(UserSubscription::class, 'subscription_transactions')
            ->withTimestamps()
            ->withPivot('description');
    }
}
