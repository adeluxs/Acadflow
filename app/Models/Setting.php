<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return SettingService::get($key, $default);
    }

    /**
     * Set a setting value by key
     */
    public static function set(string $key, $value, string $type = 'string'): void
    {
        SettingService::set($key, $value, $type);
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped(): array
    {
        return SettingService::allGrouped();
    }
    public function overrides(): HasMany
    {
        return $this->hasMany(SettingOverride::class);
    }

}
