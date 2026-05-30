<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureFlag extends Model
{
    protected $fillable = [
        'name',
        'is_enabled',
        'description',
        'enabled_at',
        'enabled_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    /**
     * Check if a feature is enabled globally
     */
    public static function isEnabled(string $name): bool
    {
        $flag = static::where('name', $name)->first();

        return $flag ? $flag->is_enabled : false;
    }

    /**
     * Enable/disable a feature
     */
    public static function setEnabled(string $name, bool $enabled, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['name' => $name],
            [
                'is_enabled' => $enabled,
                'enabled_at' => $enabled ? now() : null,
                'enabled_by' => $userId,
            ]
        );
    }
}
