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

    /**
     * Resolve several ordinary settings in one cache/database pass.
     *
     * This is intended for hot request paths (layouts, rate-limit policy,
     * runtime configuration groups) that previously called get() repeatedly.
     * Existing per-key cache keys are retained so this remains compatible with
     * the rest of the settings API and its invalidation behaviour.
     *
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public static function getMany(array $defaults, ?int $universityId = null): array
    {
        $scope = self::scope($universityId);
        $result = [];
        $unresolved = [];

        foreach ($defaults as $rawKey => $default) {
            $key = self::canonicalKey((string) $rawKey);

            if (in_array($key, self::RUNTIME_FEATURE_KEYS, true)) {
                $result[$rawKey] = self::get($key, $default, $scope);
                continue;
            }

            $localKey = ($scope ?: 'global').':'.$key;
            if (array_key_exists($localKey, self::$requestCache)) {
                $result[$rawKey] = self::$requestCache[$localKey];
                continue;
            }

            $unresolved[$key] = ['raw' => (string) $rawKey, 'default' => $default, 'local' => $localKey];
        }

        if ($unresolved === []) {
            return $result;
        }

        try {
            $cacheKeys = [];
            foreach ($unresolved as $key => $meta) {
                $cacheKeys[$key] = self::cacheKey($key, $scope);
            }

            $cached = Cache::many(array_values($cacheKeys));
            foreach ($unresolved as $key => $meta) {
                $cacheKey = $cacheKeys[$key];
                if (array_key_exists($cacheKey, $cached) && $cached[$cacheKey] !== null) {
                    self::$requestCache[$meta['local']] = $cached[$cacheKey];
                    $result[$meta['raw']] = $cached[$cacheKey];
                    unset($unresolved[$key]);
                }
            }

            if ($unresolved !== []) {
                $query = Setting::query()->whereIn('key', array_keys($unresolved));
                if ($scope) {
                    $query->with(['overrides' => fn ($override) => $override->where('university_id', $scope)]);
                }

                $settings = $query->get()->keyBy('key');
                foreach ($unresolved as $key => $meta) {
                    /** @var Setting|null $setting */
                    $setting = $settings->get($key);
                    $value = $meta['default'];

                    if ($setting) {
                        $override = $scope ? $setting->overrides->first() : null;
                        $value = self::cast(
                            $override?->value ?? $setting->value,
                            $override?->type ?? $setting->type,
                        );
                    }

                    self::$requestCache[$meta['local']] = $value;
                    $result[$meta['raw']] = $value;
                    Cache::put(self::cacheKey($key, $scope), $value, now()->addHour());
                }
            }
        } catch (\Throwable) {
            foreach ($unresolved as $meta) {
                self::$requestCache[$meta['local']] = $meta['default'];
                $result[$meta['raw']] = $meta['default'];
            }
        }

        return $result;
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
        if ($settings === []) return;

        $canonicalValues = [];
        foreach ($settings as $key => $value) {
            $canonicalValues[self::canonicalKey((string) $key)] = $value;
        }

        $existing = Setting::query()
            ->whereIn('key', array_keys($canonicalValues))
            ->get()
            ->keyBy('key');

        $scope = self::scope($universityId, false);

        foreach ($canonicalValues as $canonical => $value) {
            if (in_array($canonical, self::RUNTIME_FEATURE_KEYS, true)) {
                self::set($canonical, $value, 'boolean', $universityId, $actorId);
                continue;
            }

            /** @var Setting|null $setting */
            $setting = $existing->get($canonical);
            if (! $setting) continue;

            $type = $setting->type ?: 'string';
            if ($scope) {
                SettingOverride::query()->updateOrCreate(
                    ['setting_id' => $setting->id, 'university_id' => $scope],
                    ['value' => self::serialize($value, $type), 'type' => $type, 'updated_by' => $actorId],
                );
            } else {
                $setting->update(['value' => self::serialize($value, $type), 'type' => $type]);
            }

            self::forget($canonical, $scope);
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
    public static function emailVerificationEnabled(): bool { return (bool) self::getGlobal('email_verification_enabled', true); }
    public static function isPwaEnabled(): bool { return FeatureAccessService::effectiveStatus('pwa_enabled') === FeatureAccessService::STATUS_ENABLED; }

    public static function getPasswordPolicy(?int $universityId = null): array
    {
        $settings = self::getMany([
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => false,
        ], $universityId);

        return [
            'min_length' => max(1, min(128, (int) $settings['password_min_length'])),
            'require_uppercase' => (bool) $settings['password_require_uppercase'],
            'require_numbers' => (bool) $settings['password_require_numbers'],
            'require_special' => (bool) $settings['password_require_special'],
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
        $settings = self::getMany([
            'login_requests_per_minute' => 10,
            'registration_requests_per_hour' => 5,
            'password_reset_requests_per_minute' => 5,
            'verification_requests_per_minute' => 6,
            'two_factor_attempts_per_minute' => 5,
            'payment_requests_per_minute' => 10,
        ], $universityId);

        return [
            'login_requests_per_minute' => max(1, min(120, (int) $settings['login_requests_per_minute'])),
            'registration_requests_per_hour' => max(1, min(100, (int) $settings['registration_requests_per_hour'])),
            'password_reset_requests_per_minute' => max(1, min(30, (int) $settings['password_reset_requests_per_minute'])),
            'verification_requests_per_minute' => max(1, min(30, (int) $settings['verification_requests_per_minute'])),
            'two_factor_attempts_per_minute' => max(1, min(30, (int) $settings['two_factor_attempts_per_minute'])),
            'payment_requests_per_minute' => max(1, min(60, (int) $settings['payment_requests_per_minute'])),
        ];
    }

    public static function getPlatformSettings(): array
    {
        return self::getMany([
            'site_name' => 'AcadSyntra',
            'site_tagline' => 'Academic research, publishing and collaboration platform',
            'support_email' => '',
            'timezone' => 'Africa/Lagos',
            'maintenance_mode' => false,
            'site_logo' => null,
            'site_favicon' => null,
            'primary_color' => '#4f46e5',
        ]);
    }

    public static function getAcademicSettings(): array
    {
        return self::getMany([
            'default_submission_late_penalty' => 10,
            'allow_late_submissions' => true,
            'max_attempts_per_assignment' => 3,
            'auto_grade_assignments' => false,
            'enable_group_submissions' => true,
            'lecturer_self_assignment_enabled' => true,
            'restrict_course_membership_to_department' => true,
            'course_invitation_expiry_days' => 7,
        ]);
    }

    public static function getNotificationSettings(): array
    {
        return self::getMany([
            'email_notifications_enabled' => true,
            'push_notifications_enabled' => false,
            'in_app_notifications_enabled' => true,
            'digest_frequency' => 'daily',
            'reminder_before_deadline_hours' => 24,
        ]);
    }

    public static function getStorageSettings(): array
    {
        return self::getMany([
            'max_file_upload_size_mb' => 50,
            'allowed_file_extensions' => 'pdf,doc,docx,ppt,pptx,xls,xlsx,zip,rar,jpg,png,mp4,mp3',
            'file_retention_days' => 180,
            'enable_file_retention' => true,
            'enable_archive' => false,
            'archive_after_days' => 365,
        ]);
    }

    public static function getSecuritySettings(): array
    {
        return self::getMany([
            'session_timeout_minutes' => 120,
            'enable_two_factor' => false,
            'max_login_attempts' => 5,
            'lockout_duration_minutes' => 15,
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => false,
            'login_requests_per_minute' => 10,
            'registration_requests_per_hour' => 5,
            'password_reset_requests_per_minute' => 5,
            'verification_requests_per_minute' => 6,
            'two_factor_attempts_per_minute' => 5,
            'payment_requests_per_minute' => 10,
            'enable_audit_logs' => true,
        ]);
    }

    public static function getPwaSettings(): array
    {
        return ['pwa_enabled' => self::isPwaEnabled()] + self::getMany([
            'pwa_cache_enabled' => true,
            'pwa_offline_sync' => true,
            'pwa_theme_color' => '#4f46e5',
            'pwa_background_color' => '#ffffff',
            'pwa_display' => 'standalone',
            'pwa_orientation' => 'portrait-primary',
        ]);
    }

    public static function getBillingSettings(): array
    {
        return self::getMany([
            'currency' => 'NGN',
            'tax_rate' => 0,
            'grace_period_days' => 7,
        ]);
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

    public static function flushRequestCache(): void
    {
        self::$requestCache = [];
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
