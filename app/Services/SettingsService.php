<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Get a setting value
     */
    public static function get(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, string $type = 'string'): void
    {
        Setting::set($key, $value, $type);
        Cache::forget('settings.' . $key);
    }

    /**
     * Get all settings grouped
     */
    public static function getAllGrouped(): array
    {
        return Cache::remember('settings.all', now()->addHours(24), function () {
            return Setting::getAllGrouped();
        });
    }

    /**
     * Get settings by group
     */
    public static function getByGroup(string $group): array
    {
        return Setting::where('group', $group)->pluck('value', 'key')->toArray();
    }

    /**
     * Update multiple settings at once
     */
    public static function updateMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $setting->update(['value' => $value]);
                Cache::forget('settings.' . $key);
            }
        }
        Cache::forget('settings.all');
    }

    /**
     * Get platform settings
     */
    public static function getPlatformSettings(): array
    {
        return [
            'site_name' => self::get('site_name', 'UniAcademic'),
            'site_tagline' => self::get('site_tagline', 'University Academic Management Platform'),
            'support_email' => self::get('support_email', 'support@example.com'),
            'timezone' => self::get('timezone', 'UTC'),
            'maintenance_mode' => self::get('maintenance_mode', false),
            'logo_url' => self::get('logo_url'),
            'favicon_url' => self::get('favicon_url'),
        ];
    }

    /**
     * Get academic settings
     */
    public static function getAcademicSettings(): array
    {
        return [
            'default_submission_late_penalty' => self::get('default_submission_late_penalty', 10),
            'allow_late_submissions' => self::get('allow_late_submissions', true),
            'max_attempts_per_assignment' => self::get('max_attempts_per_assignment', 3),
            'auto_grade_assignments' => self::get('auto_grade_assignments', false),
            'default_resubmission_deadline_days' => self::get('default_resubmission_deadline_days', 7),
            'require_approval_before_publish' => self::get('require_approval_before_publish', false),
            'enable_siwes_module' => self::get('enable_siwes_module', true),
            'enable_final_year_project' => self::get('enable_final_year_project', true),
            'enable_group_submissions' => self::get('enable_group_submissions', true),
        ];
    }

    /**
     * Get notification settings
     */
    public static function getNotificationSettings(): array
    {
        return [
            'email_notifications_enabled' => self::get('email_notifications_enabled', true),
            'push_notifications_enabled' => self::get('push_notifications_enabled', false),
            'digest_frequency' => self::get('digest_frequency', 'daily'),
            'deadline_reminder_hours' => self::get('deadline_reminder_hours', 24),
            'enable_in_app_notifications' => self::get('enable_in_app_notifications', true),
            'enable_email_notifications' => self::get('enable_email_notifications', true),
            'enable_push_notifications' => self::get('enable_push_notifications', false),
        ];
    }

    /**
     * Get storage settings
     */
    public static function getStorageSettings(): array
    {
        return [
            'max_file_upload_size_mb' => self::get('max_file_upload_size_mb', 50),
            'allowed_file_extensions' => self::get('allowed_file_extensions', 'pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png,mp4,mp3'),
            'storage_provider' => self::get('storage_provider', 'local'),
            'enable_cloud_storage' => self::get('enable_cloud_storage', false),
            'retention_days' => self::get('retention_days', 365),
            'enable_archive' => self::get('enable_archive', true),
        ];
    }

    /**
     * Get security settings
     */
    public static function getSecuritySettings(): array
    {
        return [
            'session_timeout_minutes' => self::get('session_timeout_minutes', 30),
            'require_2fa' => self::get('require_2fa', false),
            'max_login_attempts' => self::get('max_login_attempts', 5),
            'lockout_duration_minutes' => self::get('lockout_duration_minutes', 15),
            'password_min_length' => self::get('password_min_length', 8),
            'require_password_uppercase' => self::get('require_password_uppercase', true),
            'require_password_number' => self::get('require_password_number', true),
            'require_password_special' => self::get('require_password_special', false),
            'enable_audit_log' => self::get('enable_audit_log', true),
        ];
    }

    /**
     * Get PWA settings
     */
    public static function getPwaSettings(): array
    {
        return [
            'pwa_enabled' => self::get('pwa_enabled', true),
            'pwa_name' => self::get('pwa_name', 'UniAcademic'),
            'pwa_short_name' => self::get('pwa_short_name', 'UniAcademic'),
            'pwa_theme_color' => self::get('pwa_theme_color', '#4f46e5'),
            'pwa_background_color' => self::get('pwa_background_color', '#ffffff'),
            'enable_offline_mode' => self::get('enable_offline_mode', true),
            'offline_page_cache' => self::get('offline_page_cache', true),
            'background_sync_enabled' => self::get('background_sync_enabled', true),
        ];
    }

    /**
     * Get billing settings
     */
    public static function getBillingSettings(): array
    {
        return [
            'currency' => self::get('currency', 'USD'),
            'tax_rate' => self::get('tax_rate', 0),
            'grace_period_days' => self::get('grace_period_days', 7),
            'enable_online_payment' => self::get('enable_online_payment', true),
            'payment_gateway' => self::get('payment_gateway', 'stripe'),
            'invoice_prefix' => self::get('invoice_prefix', 'INV-'),
        ];
    }

    /**
     * Check if maintenance mode is enabled
     */
    public static function isMaintenanceMode(): bool
    {
        return (bool) self::get('maintenance_mode', false);
    }

    /**
     * Check if a feature is enabled
     */
    public static function isFeatureEnabled(string $feature): bool
    {
        return (bool) self::get('feature_' . $feature, false);
    }

    /**
     * Enable/disable a feature
     */
    public static function setFeatureEnabled(string $feature, bool $enabled): void
    {
        self::set('feature_' . $feature, $enabled, 'boolean');
    }
}