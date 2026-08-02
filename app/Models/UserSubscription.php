<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'plan_id',
        'plan_name',
        'status',
        'started_at',
        'ends_at',
        'trial_ends_at',
        'payment_method',
        'amount',
        'currency',
        'payment_status',
        'payment_reference',
        'gateway',
        'billing_cycle',
        'billing_model',
        'price_per_student',
        'grace_days',
        'start_date',
        'end_date',
        'is_active',
        'auto_renew',
        'university_id',
        'department_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
        'price_per_student' => 'decimal:2',
        'auto_renew' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Check if subscription is currently active
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->status && $this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            $this->status = 'expired';
            $this->save();

            return false;
        }

        if ($this->end_date && now()->greaterThan($this->end_date)) {
            return false;
        }

        if ($this->start_date && now()->lessThan($this->start_date)) {
            return false;
        }

        return true;
    }

    /**
     * Check if trial is active
     */
    public function isTrial(): bool
    {
        if (! $this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    /**
     * Check if payment is completed
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}

