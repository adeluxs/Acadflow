<?php

namespace App\Models;

use App\Services\FeatureAccessService;
use Illuminate\Database\Eloquent\Model;

class FeatureFlagOverride extends Model
{
    protected $fillable = [
        'feature_flag_id',
        'university_id',
        'is_enabled',
        'settings',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => FeatureAccessService::forget());
        static::deleted(fn () => FeatureAccessService::forget());
    }
}
