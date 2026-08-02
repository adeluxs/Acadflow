<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'plan_name',
        'billing_model',
        'price_per_student',
        'grace_days',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_student' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if subscription is currently active
     */
    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    /**
     * Get status based on is_active and date range.
     * Maintains backward compatibility for code referencing ->status
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $now = now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return 'pending';
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return 'expired';
        }

        return 'active';
    }
}

