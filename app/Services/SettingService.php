<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingOverride;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for platform and institution settings.
 *
 * Global settings live in `settings`; institution customisations live in
 * `setting_overrides`. Legacy key aliases are resolved here so older modules
 * continue to work without creating duplicate settings.
 */
class SettingService
{
    private static array $requestCache = [];

    /** Legacy setting rows retained for safe upgrades but no longer authoritative. */
    private const RUNTIME_FEATURE_KEYS = ['pwa_enabled', 'knowledge_hub_premium_enabled'];

    private const ALIASES = [
        // Branding
        'logo_url' => 'site_logo',
        'favicon_url' => 'site_favicon',
        // Notifications
        'notifications_email_enabled' => 'email_notifications_enabled',
        'enable_email_notifications' => 'email_notifications_enabled',
        'notifications_push_enabled' => 'push_notifications_enabled',
        'enable_push_notifications' => 'push_notifications_enabled',
        'notifications_in_app_enabled' => 'in_app_notifications_enabled',
        'enable_in_app_notifications' => 'in_app_notifications_enabled',
        'deadline_reminder_hours' => 'reminder_before_deadline_hours',
        // Security
        'require_2fa' => 'enable_two_factor',
        'require_password_uppercase' => 'password_require_uppercase',
        'require_password_number' => 'password_require_numbers',
        'require_password_special' => 'password_require_special',
        // Storage
        'retention_days' => 'file_retention_days',
        // PWA legacy aliases
        'enable_offline_mode' => 'pwa_cache_enabled',
        'offline_page_cache' => 'pwa_cache_enabled',
        'background_sync_enabled' => 'pwa_offline_sync',
    ];

    public static function get(string $key, mixed $default = null, ?int $universityId = null): mixed
    {
        $key = self::canonicalKey($key);

        if ($key === 'pwa_enabled') {
            return FeatureAccessService::effectiveStatus('pwa_enabled', $universityId) === FeatureAccessService::STATUS_ENABLED;
        }
        if ($key === 'knowledge_hub_premium_enabled') {
            return FeatureAccessService::effectiveStatus('knowledge_hub_premium', $universityId) === FeatureAccessService::STATUS_ENABLED;
        }

        $scope = self::scope($universityId);
        $localKey = ($scope ?: 'global').':'.$key;

        if (array_key_exists($localKey, self::$requestCache)) {
            return self::$requestCache[$localKey];
        }

        try {
            return self::$requestCache[$localKey] = Cache::remember(
                self::cacheKey($key, $scope),
                now()->addHour(),
                function () use ($key, $default, $scope) {
                    $setting = Setting::query()->where('key', $key)->first();
                    if (! $setting) {
                        return $default;
                    }

                    if ($scope) {
                        $override = SettingOverride::query()
                            ->where('setting_id', $setting->id)
                            ->where('university_id', $scope)
                            ->first();

                        if ($override) {
                            return self::cast($override->value, $override->type ?: $setting->type);
                        }
                    }

                    return self::cast($setting->value, $setting->type);
                }
            );
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function getGlobal(string $key, mixed $default = null): mixed
    {
        $key = self::canonicalKey($key);

        // Runtime release state must never fall back to a stale legacy Settings row.
        if ($key === 'pwa_enabled') {
            return FeatureAccessService::effectiveStatus('pwa_enabled') === FeatureAccessService::STATUS_ENABLED;
        }
        if ($key === 'knowledge_hub_premium_enabled') {
            return FeatureAccessService::effectiveStatus('knowledge_hub_premium') === FeatureAccessService::STATUS_ENABLED;
        }

        $localKey = 'global:'.$key;
        if (array_key_exists($localKey, self::$requestCache)) {
            return self::$requestCache[$localKey];
        }

        try {
            return self::$requestCache[$localKey] = Cache::remember(
                self::cacheKey($key, null),
                now()->addHour(),
                function () use ($key, $default) {
                    $setting = Setting::query()->where('key', $key)->first();
                    return $setting ? self::cast($setting->value, $setting->type) : $default;
                }
            );
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value, string $type = 'string', ?int $universityId = null, ?int $actorId = null): void
    {
        $key = self::canonicalKey($key);

        if ($key === 'pwa_enabled' || $key === 'knowledge_hub_premium_enabled') {
            $feature = $key === 'pwa_enabled' ? 'pwa_enabled' : 'knowledge_hub_premium';
            FeatureAccessService::setStatus(
                $feature,
                filter_var($value, FILTER_VALIDATE_BOOL) ? FeatureAccessService::STATUS_ENABLED : FeatureAccessService::STATUS_DISABLED,
                $actorId,
                $universityId,
            );
            return;
        }

        $scope = self::scope($universityId, false);

        $setting = Setting::query()->firstOrCreate(
            ['key' => $key],
            ['value' => self::serialize($value, $type), 'type' => $type, 'group' => 'general']
        );

        $type = $setting->type ?: $type;
        if ($scope) {
            SettingOverride::query()->updateOrCreate(
                ['setting_id' => $setting->id, 'university_id' => $scope],
                ['value' => self::serialize($value, $type), 'type' => $type, 'updated_by' => $actorId]
            );
        } else {
            $setting->update(['value' => self::serialize($value, $type), 'type' => $type]);
        }

        self::forget($key, $scope);
    }

    public static function updateMultiple(array $settings, ?int $universityId = null, ?int $actorId = null): void
    {
        foreach ($settings as $key => $value) {
            $canonical = self::canonicalKey((string) $key);
            $existing = Setting::query()->where('key', $canonical)->first();
            if ($existing) {
                self::set($canonical, $value, $existing->type ?: 'string', $universityId, $actorId);
            }
        }
    }

    public static function allGrouped(?int $universityId = null): array
    {
        $scope = self::scope($universityId);
        try {
            return Cache::remember('settings.all.'.($scope ?: 'global'), now()->addHour(), function () use ($scope) {
                $settings = Setting::query()
                    ->with(['overrides' => fn ($query) => $scope
                        ? $query->where('university_id', $scope)
                        : $query->whereRaw('1 = 0')])
                    ->orderBy('group')
                    ->orderBy('key')
                    ->get();

                $result = [];
                foreach ($settings as $setting) {
                    // Ignore obsolete alias rows if an older deployment created any.
                    if (self::canonicalKey($setting->key) !== $setting->key
                        || in_array($setting->key, self::RUNTIME_FEATURE_KEYS, true)) {
                        continue;
                    }
                    $override = $setting->overrides->first();
                    $result[$setting->group][$setting->key] = self::cast(
                        $override?->value ?? $setting->value,
                        $override?->type ?? $setting->type
                    );
                }
                return $result;
            });
        } catch (\Throwable) {
            return [];
        }
    }

    public static function getMaxUploadSize(?int $universityId = null): int
    {
        return (int) self::get('max_file_upload_size_mb', 50, $universityId) * 1024 * 1024;
    }

    public static function getAllowedExtensions(?int $universityId = null): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) self::get('allowed_file_extensions', 'pdf,doc,docx', $universityId)))));
    }

    public static function getSessionTimeout(): int { return (int) self::get('session_timeout_minutes', 120); }
    public static function isMaintenanceMode(): bool { return (bool) self::getGlobal('maintenance_mode', false); }
    public static function isPwaEnabled(): bool { return FeatureAccessService::effectiveStatus('pwa_enabled') === FeatureAccessService::STATUS_ENABLED; }

    public static function getPasswordPolicy(?int $universityId = null): array
    {
        return [
            'min_length' => max(1, min(128, (int) self::get('password_min_length', 8, $universityId))),
            'require_uppercase' => (bool) self::get('password_require_uppercase', true, $universityId),
            'require_numbers' => (bool) self::get('password_require_numbers', true, $universityId),
            'require_special' => (bool) self::get('password_require_special', false, $universityId),
            'special_characters' => '@$!%*#?&',
        ];
    }

    public static function getPasswordRules(?int $universityId = null): array
    {
        $policy = self::getPasswordPolicy($universityId);
        $rules = ['required', 'string', 'min:'.$policy['min_length']];

        if ($policy['require_uppercase']) $rules[] = 'regex:/[A-Z]/';
        if ($policy['require_numbers']) $rules[] = 'regex:/[0-9]/';
        if ($policy['require_special']) $rules[] = 'regex:/[@$!%*#?&]/';

        return $rules;
    }

    public static function getSecurityRateLimits(?int $universityId = null): array
    {
        return [
            'login_requests_per_minute' => max(1, min(120, (int) self::get('login_requests_per_minute', 10, $universityId))),
            'registration_requests_per_hour' => max(1, min(100, (int) self::get('registration_requests_per_hour', 5, $universityId))),
            'password_reset_requests_per_minute' => max(1, min(30, (int) self::get('password_reset_requests_per_minute', 5, $universityId))),
            'verification_requests_per_minute' => max(1, min(30, (int) self::get('verification_requests_per_minute', 6, $universityId))),
            'two_factor_attempts_per_minute' => max(1, min(30, (int) self::get('two_factor_attempts_per_minute', 5, $universityId))),
            'payment_requests_per_minute' => max(1, min(60, (int) self::get('payment_requests_per_minute', 10, $universityId))),
        ];
    }

    public static function getPlatformSettings(): array
    {
        return [
            'site_name' => self::get('site_name', 'AcadFlow'),
            'site_tagline' => self::get('site_tagline', 'Academic research, publishing and collaboration platform'),
            'support_email' => self::get('support_email', 'support@example.com'),
            'timezone' => self::get('timezone', 'Africa/Lagos'),
            'maintenance_mode' => self::get('maintenance_mode', false),
            'site_logo' => self::get('site_logo'),
            'site_favicon' => self::get('site_favicon'),
            'primary_color' => self::get('primary_color', '#4f46e5'),
        ];
    }

    public static function getAcademicSettings(): array
    {
        return [
            'default_submission_late_penalty' => self::get('default_submission_late_penalty', 10),
            'allow_late_submissions' => self::get('allow_late_submissions', true),
            'max_attempts_per_assignment' => self::get('max_attempts_per_assignment', 3),
            'auto_grade_assignments' => self::get('auto_grade_assignments', false),
            'enable_group_submissions' => self::get('enable_group_submissions', true),
            'lecturer_self_assignment_enabled' => self::get('lecturer_self_assignment_enabled', true),
            'restrict_course_membership_to_department' => self::get('restrict_course_membership_to_department', true),
            'course_invitation_expiry_days' => self::get('course_invitation_expiry_days', 7),
        ];
    }

    public static function getNotificationSettings(): array
    {
        return [
            'email_notifications_enabled' => self::get('email_notifications_enabled', true),
            'push_notifications_enabled' => self::get('push_notifications_enabled', false),
            'in_app_notifications_enabled' => self::get('in_app_notifications_enabled', true),
            'digest_frequency' => self::get('digest_frequency', 'daily'),
            'reminder_before_deadline_hours' => self::get('reminder_before_deadline_hours', 24),
        ];
    }

    public static function getStorageSettings(): array
    {
        return [
            'max_file_upload_size_mb' => self::get('max_file_upload_size_mb', 50),
            'allowed_file_extensions' => self::get('allowed_file_extensions', 'pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png,mp4,mp3'),
            'file_retention_days' => self::get('file_retention_days', 180),
            'enable_file_retention' => self::get('enable_file_retention', true),
            'enable_archive' => self::get('enable_archive', false),
            'archive_after_days' => self::get('archive_after_days', 365),
        ];
    }

    public static function getSecuritySettings(): array
    {
        return [
            'session_timeout_minutes' => self::get('session_timeout_minutes', 120),
            'enable_two_factor' => self::get('enable_two_factor', false),
            'max_login_attempts' => self::get('max_login_attempts', 5),
            'lockout_duration_minutes' => self::get('lockout_duration_minutes', 15),
            'password_min_length' => self::get('password_min_length', 8),
            'password_require_uppercase' => self::get('password_require_uppercase', true),
            'password_require_numbers' => self::get('password_require_numbers', true),
            'password_require_special' => self::get('password_require_special', false),
            'login_requests_per_minute' => self::get('login_requests_per_minute', 10),
            'registration_requests_per_hour' => self::get('registration_requests_per_hour', 5),
            'password_reset_requests_per_minute' => self::get('password_reset_requests_per_minute', 5),
            'verification_requests_per_minute' => self::get('verification_requests_per_minute', 6),
            'two_factor_attempts_per_minute' => self::get('two_factor_attempts_per_minute', 5),
            'payment_requests_per_minute' => self::get('payment_requests_per_minute', 10),
            'enable_audit_logs' => self::get('enable_audit_logs', true),
        ];
    }

    public static function getPwaSettings(): array
    {
        return [
            'pwa_enabled' => self::isPwaEnabled(),
            'pwa_cache_enabled' => self::get('pwa_cache_enabled', true),
            'pwa_offline_sync' => self::get('pwa_offline_sync', true),
            'pwa_theme_color' => self::get('pwa_theme_color', '#4f46e5'),
            'pwa_background_color' => self::get('pwa_background_color', '#ffffff'),
            'pwa_display' => self::get('pwa_display', 'standalone'),
            'pwa_orientation' => self::get('pwa_orientation', 'portrait-primary'),
        ];
    }

    public static function getBillingSettings(): array
    {
        return [
            'currency' => self::get('currency', 'NGN'),
            'tax_rate' => self::get('tax_rate', 0),
            'grace_period_days' => self::get('grace_period_days', 7),
        ];
    }

    public static function isFeatureEnabled(string $feature): bool
    {
        return FeatureAccessService::effectiveStatus($feature, auth()->user()?->university_id) === FeatureAccessService::STATUS_ENABLED;
    }

    public static function setFeatureEnabled(string $feature, bool $enabled, ?int $universityId = null, ?int $actorId = null): void
    {
        FeatureAccessService::setStatus(
            $feature,
            $enabled ? FeatureAccessService::STATUS_ENABLED : FeatureAccessService::STATUS_DISABLED,
            $actorId,
            $universityId,
        );
    }

    public static function canonicalKey(string $key): string
    {
        return self::ALIASES[$key] ?? $key;
    }

    private static function scope(?int $universityId, bool $useAuthenticatedUser = true): ?int
    {
        if ($universityId !== null) return $universityId;
        return $useAuthenticatedUser ? auth()->user()?->university_id : null;
    }

    private static function cacheKey(string $key, ?int $scope): string
    {
        return 'setting.'.($scope ?: 'global').'.'.$key;
    }

    private static function forget(string $key, ?int $scope): void
    {
        unset(self::$requestCache[($scope ?: 'global').':'.$key]);
        Cache::forget(self::cacheKey($key, $scope));
        Cache::forget('settings.all.'.($scope ?: 'global'));

        // Global changes affect institutions that have no override.
        if ($scope === null) {
            self::$requestCache = [];
        }
    }

    private static function serialize(mixed $value, string $type): mixed
    {
        if ($type === 'boolean' || $type === 'bool') return $value ? '1' : '0';
        if (in_array($type, ['array', 'json'], true) && ! is_string($value)) return json_encode($value);
        if (is_array($value) || is_object($value)) return json_encode($value);
        return $value;
    }

    private static function cast(mixed $value, ?string $type): mixed
    {
        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'decimal', 'float', 'double' => (float) $value,
            'array', 'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?: []),
            default => $value,
        };
    }
}
