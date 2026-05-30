<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'plan_type',
        'price_per_month',
        'price_per_semester',
        'price_per_year',
        'billing_cycle',
        'available_billing_cycles',
        'features',
        'limits',
        'max_courses',
        'max_students_per_course',
        'max_file_upload_size_mb',
        'max_storage_gb',
        'allow_group_submissions',
        'allow_rubrics',
        'allow_attendance_tracking',
        'allow_document_generation',
        'allow_api_access',
        'allow_white_label',
        'max_administrators',
        'is_active',
        'is_recommended',
        'trial_days',
        'has_trial',
        'min_seats',
        'max_seats',
        'priority_support',
        'dedicated_account_manager',
        'sso_enabled',
        'api_access',
        'custom_branding',
        'can_upgrade',
        'can_downgrade',
        'prorated_upgrades',
        'cancellation_minimum_days',
        'refundable',
        'refund_period_days',
        'custom_pricing',
        'pricing_note',
        'sort_order',
    ];

    protected $casts = [
        'plan_type' => 'string',
        'price_per_month' => 'decimal:2',
        'price_per_year' => 'decimal:2',
        'price_per_semester' => 'decimal:2',
        'billing_cycle' => 'string',
        'available_billing_cycles' => 'array',
        'features' => 'array',
        'limits' => 'array',
        'max_courses' => 'integer',
        'max_students_per_course' => 'integer',
        'max_file_upload_size_mb' => 'integer',
        'max_storage_gb' => 'integer',
        'allow_group_submissions' => 'boolean',
        'allow_rubrics' => 'boolean',
        'allow_attendance_tracking' => 'boolean',
        'allow_document_generation' => 'boolean',
        'allow_api_access' => 'boolean',
        'allow_white_label' => 'boolean',
        'max_administrators' => 'integer',
        'is_active' => 'boolean',
        'is_recommended' => 'boolean',
        'trial_days' => 'integer',
        'has_trial' => 'boolean',
        'min_seats' => 'integer',
        'max_seats' => 'integer',
        'priority_support' => 'boolean',
        'dedicated_account_manager' => 'boolean',
        'sso_enabled' => 'boolean',
        'api_access' => 'boolean',
        'custom_branding' => 'boolean',
        'can_upgrade' => 'boolean',
        'can_downgrade' => 'boolean',
        'prorated_upgrades' => 'boolean',
        'cancellation_minimum_days' => 'integer',
        'refundable' => 'boolean',
        'refund_period_days' => 'integer',
        'custom_pricing' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get users on this plan
     */
    public function subscribers()
    {
        return $this->hasMany(UserSubscription::class, 'plan_id');
    }

    /**
     * Check if plan has a feature
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->features ?? [];

        return in_array($feature, $features, true);
    }

    /**
     * Get default plan limits
     */
    public function getLimit(string $limit, $default = null)
    {
        $limits = $this->limits ?? [];

        return $limits[$limit] ?? $default;
    }

    /**
     * Check if plan is available for purchase
     */
    public function isAvailable(): bool
    {
        return $this->is_active && 
               (!$this->max_seats || $this->subscribers()->where('status', 'active')->count() < $this->max_seats);
    }

    /**
     * Get price for billing cycle
     */
    public function getPriceForCycle(string $cycle): ?float
    {
        return match ($cycle) {
            'monthly' => $this->price_per_month,
            'semester' => $this->price_per_semester ?? ($this->price_per_month * 4),
            'yearly' => $this->price_per_year ?? ($this->price_per_month * 10),
            default => $this->price_per_month,
        };
    }
}
