<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * Get a setting value by key with caching
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
        
        Cache::forget("setting.{$key}");
        Cache::forget('settings.all');
    }

    /**
     * Get all settings as grouped array
     */
    public static function allGrouped(): array
    {
        return Cache::remember('settings.all', 3600, function () {
            $settings = Setting::all()->groupBy('group');
            $result = [];
            
            foreach ($settings as $group => $items) {
                $result[$group] = $items->pluck('value', 'key')->toArray();
            }
            
            return $result;
        });
    }

    /**
     * Get max file upload size in bytes
     */
    public static function getMaxUploadSize(): int
    {
        $sizeInMb = (int) self::get('max_file_upload_size_mb', 50);
        return $sizeInMb * 1024 * 1024; // Convert to bytes
    }

    /**
     * Get allowed file extensions as array
     */
    public static function getAllowedExtensions(): array
    {
        $extensions = self::get('allowed_file_extensions', 'pdf,doc,docx');
        return array_map('trim', explode(',', $extensions));
    }

    /**
     * Get password validation rules based on settings
     */
    public static function getPasswordRules(): array
    {
        $rules = ['required', 'string'];
        
        $minLength = (int) self::get('password_min_length', 8);
        $rules[] = "min:{$minLength}";
        
        if (self::get('password_require_uppercase', true)) {
            $rules[] = 'regex:/[A-Z]/';
        }
        
        if (self::get('password_require_numbers', true)) {
            $rules[] = 'regex:/[0-9]/';
        }
        
        if (self::get('password_require_special', false)) {
            $rules[] = 'regex:/[@$!%*#?&]/';
        }
        
        return $rules;
    }

    /**
     * Get session timeout in minutes
     */
    public static function getSessionTimeout(): int
    {
        return (int) self::get('session_timeout_minutes', 120);
    }

    /**
     * Check if maintenance mode is enabled
     */
    public static function isMaintenanceMode(): bool
    {
        return (bool) self::get('maintenance_mode', false);
    }

    /**
     * Check if PWA is enabled
     */
    public static function isPwaEnabled(): bool
    {
        return (bool) self::get('pwa_enabled', true);
    }

    /**
     * Get current academic year
     */
    public static function getCurrentAcademicYear(): string
    {
        return self::get('default_academic_year', date('Y').'/'.(date('Y')+1));
    }

    /**
     * Get current semester
     */
    public static function getCurrentSemester(): string
    {
        return self::get('current_semester', 'first');
    }
}
