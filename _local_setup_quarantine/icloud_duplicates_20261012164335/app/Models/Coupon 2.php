<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'is_active',
        'max_uses',
        'used_count',
        'start_date',
        'expiry_date',
        'applicable_plans',
    ];

    protected $casts = [
        'type' => 'string',
        'value' => 'decimal:2',
        'is_active' => 'boolean',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'applicable_plans' => 'array',
    ];

    /**
     * Check if coupon is valid and active
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->expiry_date && $this->expiry_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount for a given amount
     */
    public function calculateDiscount(float $amount): float
    {
        if ($this->type === 'percentage') {
            return $amount * ($this->value / 100);
        }

        return min($this->value, $amount);
    }

    /**
     * Check if coupon is applicable to a specific plan
     */
    public function isApplicableToPlan($plan): bool
    {
        if (empty($this->applicable_plans)) {
            return true; // Applies to all plans
        }

        if (is_numeric($plan)) {
            return in_array($plan, $this->applicable_plans);
        }

        return in_array($plan->id, $this->applicable_plans);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
