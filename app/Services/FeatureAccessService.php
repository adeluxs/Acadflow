<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\FeatureFlagOverride;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authoritative runtime availability service for AcadFlow user-facing modules.
 *
 * Runtime state lives in the existing feature_flags table. Metadata and route
 * mapping live in config/features.php. Other settings may configure how a
 * module behaves, but they must not independently decide whether it is released.
 */
class FeatureAccessService
{
    public const STATUS_ENABLED = 'enabled';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_DISABLED = 'disabled';

    private const CACHE_VERSION_KEY = 'feature_access.registry_version';
    private const CACHE_TTL_SECONDS = 3600;

    /** @var array<string,array<string,mixed>> */
    private static array $requestSnapshots = [];

    /** @var array<string,string> */
    private static array $effectiveStatusCache = [];

    public static function definitions(): array
    {
        return (array) config('features.definitions', []);
    }

    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function statuses(): array
    {
        return [self::STATUS_ENABLED, self::STATUS_MAINTENANCE, self::STATUS_DISABLED];
    }

    public static function metadata(string $feature): array
    {
        return self::definitions()[$feature] ?? [
            'title' => Str::headline($feature),
            'category' => 'Other',
            'description' => null,
            'default_status' => self::STATUS_ENABLED,
            'routes' => [],
            'paths' => [],
            'depends_on' => [],
            'impact' => null,
        ];
    }

    public static function normalizeStatus(mixed $status, bool $legacyEnabled = true): string
    {
        $value = Str::lower(trim((string) $status));

        return match ($value) {
            'enabled', 'enable', 'on', 'live', 'released', 'active' => self::STATUS_ENABLED,
            'maintenance', 'maintain', 'paused' => self::STATUS_MAINTENANCE,
            'disabled', 'disable', 'off', 'unreleased', 'admin_preview', 'preview' => self::STATUS_DISABLED,
            default => $legacyEnabled ? self::STATUS_ENABLED : self::STATUS_DISABLED,
        };
    }

    /**
     * Resolve a route name to the most specific registered feature. This avoids
     * broad patterns such as research.* swallowing research.siwes.*.
     */
    public static function featureForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }

        return self::bestPatternMatch($routeName, 'routes');
    }

    public static function featureForPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return self::bestPatternMatch(ltrim($path, '/'), 'paths');
    }

    public static function featureForRequest(Request $request): ?string
    {
        return self::featureForRoute($request->route()?->getName())
            ?? self::featureForPath($request->path());
    }

    private static function bestPatternMatch(string $value, string $field): ?string
    {
        $bestFeature = null;
        $bestScore = -1;

        foreach (self::definitions() as $feature => $definition) {
            foreach ((array) ($definition[$field] ?? []) as $pattern) {
                $pattern = (string) $pattern;
                if ($pattern === '' || ! Str::is($pattern, $value)) {
                    continue;
                }

                $score = strlen(str_replace('*', '', $pattern)) * 10;
                if (! str_contains($pattern, '*')) {
                    $score += 10000;
                }

                if ($score > $bestScore) {
                    $bestFeature = $feature;
                    $bestScore = $score;
                }
            }
        }

        return $bestFeature;
    }

    /**
     * Configured state before dependency inheritance.
     */
    public static function status(string $feature, ?int $universityId = null): string
    {
        $snapshot = self::snapshot($universityId);
        $default = self::normalizeStatus(self::metadata($feature)['default_status'] ?? self::STATUS_ENABLED, true);

        return (string) ($snapshot[$feature]['status'] ?? $default);
    }

    /**
     * Effective state also considers dependencies. A child configured as enabled
     * becomes unavailable when a required parent is in maintenance/disabled.
     */
    public static function effectiveStatus(string $feature, ?int $universityId = null, array $stack = []): string
    {
        $cacheKey = $feature.':'.($universityId ?? 'global');
        if ($stack === [] && isset(self::$effectiveStatusCache[$cacheKey])) {
            return self::$effectiveStatusCache[$cacheKey];
        }

        if (in_array($feature, $stack, true)) {
            // A registry cycle must never lock users out; fail open and surface it in admin UI.
            return self::status($feature, $universityId);
        }

        $status = self::status($feature, $universityId);
        $severity = self::severity($status);
        $stack[] = $feature;

        foreach ((array) (self::metadata($feature)['depends_on'] ?? []) as $dependency) {
            if (! isset(self::definitions()[$dependency])) {
                continue;
            }

            $dependencyStatus = self::effectiveStatus((string) $dependency, $universityId, $stack);
            if (self::severity($dependencyStatus) > $severity) {
                $status = $dependencyStatus;
                $severity = self::severity($dependencyStatus);
            }
        }

        if (count($stack) === 1) {
            self::$effectiveStatusCache[$cacheKey] = $status;
        }

        return $status;
    }

    private static function severity(string $status): int
    {
        return match ($status) {
            self::STATUS_DISABLED => 2,
            self::STATUS_MAINTENANCE => 1,
            default => 0,
        };
    }

    public static function canAccessFeature(string $feature, ?User $user = null): bool
    {
        return self::effectiveStatus($feature, $user?->university_id) === self::STATUS_ENABLED
            || (bool) $user?->isAdmin();
    }

    public static function canAccessRoute(?User $user, ?string $routeName): bool
    {
        $feature = self::featureForRoute($routeName);

        return $feature === null || self::canAccessFeature($feature, $user);
    }

    /**
     * Normal users see maintenance entries (with a badge) but disabled/unreleased
     * entries are hidden. Admins see every entry so they can preview and test it.
     */
    public static function shouldShowInNavigation(string $feature, ?User $user = null): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }

        return self::effectiveStatus($feature, $user?->university_id) !== self::STATUS_DISABLED;
    }

    public static function shouldShowRouteInNavigation(?string $routeName, ?User $user = null): bool
    {
        $feature = self::featureForRoute($routeName);

        return $feature === null || self::shouldShowInNavigation($feature, $user);
    }

    public static function navigationStatusForRoute(?string $routeName, ?User $user = null): ?string
    {
        $feature = self::featureForRoute($routeName);

        return $feature ? self::effectiveStatus($feature, $user?->university_id) : null;
    }

    public static function settings(string $feature, ?int $universityId = null): array
    {
        $snapshot = self::snapshot($universityId);
        return (array) ($snapshot[$feature]['settings'] ?? []);
    }

    public static function message(string $feature, ?int $universityId = null): string
    {
        $settings = self::settings($feature, $universityId);
        $custom = trim((string) ($settings['maintenance_message'] ?? ''));
        $status = self::effectiveStatus($feature, $universityId);

        if ($custom !== '' && $status === self::STATUS_MAINTENANCE) {
            return $custom;
        }

        return $status === self::STATUS_MAINTENANCE
            ? 'This feature is temporarily unavailable while maintenance is being performed. Please try again later.'
            : 'This feature is currently unavailable or has not yet been released.';
    }

    public static function unavailableResponse(Request $request, string $feature): Response
    {
        $status = self::effectiveStatus($feature, $request->user()?->university_id);
        $meta = self::metadata($feature);
        $message = self::message($feature, $request->user()?->university_id);
        $httpStatus = $status === self::STATUS_MAINTENANCE ? 503 : 403;
        $statusCode = $status === self::STATUS_MAINTENANCE ? 'FEATURE_MAINTENANCE' : 'FEATURE_DISABLED';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'status_code' => $statusCode,
                'message' => $message,
                'feature' => $feature,
                'feature_status' => $status,
            ], $httpStatus);
        }

        return response()->view('errors.feature-unavailable', [
            'feature' => $feature,
            'featureTitle' => $meta['title'] ?? Str::headline($feature),
            'featureStatus' => $status,
            'featureMessage' => $message,
        ], $httpStatus);
    }

    /**
     * Compact backend-authoritative state payload for web/mobile bootstrap data.
     */
    public static function clientSnapshot(?User $user = null): array
    {
        $universityId = $user?->university_id;
        $result = [];

        foreach (self::definitions() as $feature => $definition) {
            $result[$feature] = self::effectiveStatus($feature, $universityId);
        }

        return $result;
    }

    /**
     * Rows used by Admin > Settings > Feature & Module Management.
     */
    public static function managementRows(): array
    {
        $snapshot = self::snapshot(null);
        $rows = [];

        foreach (self::definitions() as $feature => $definition) {
            $configured = self::status($feature, null);
            $effective = self::effectiveStatus($feature, null);
            $settings = (array) ($snapshot[$feature]['settings'] ?? []);
            $blockedBy = [];

            foreach ((array) ($definition['depends_on'] ?? []) as $dependency) {
                $dependencyStatus = self::effectiveStatus((string) $dependency, null);
                if ($dependencyStatus !== self::STATUS_ENABLED) {
                    $blockedBy[] = [
                        'key' => $dependency,
                        'title' => self::metadata((string) $dependency)['title'] ?? Str::headline((string) $dependency),
                        'status' => $dependencyStatus,
                    ];
                }
            }

            $rows[] = [
                'key' => $feature,
                'title' => $definition['title'] ?? Str::headline($feature),
                'category' => $definition['category'] ?? 'Other',
                'description' => $definition['description'] ?? null,
                'impact' => $definition['impact'] ?? null,
                'configured_status' => $configured,
                'effective_status' => $effective,
                'maintenance_message' => (string) ($settings['maintenance_message'] ?? ''),
                'admin_note' => (string) ($settings['admin_note'] ?? ''),
                'depends_on' => (array) ($definition['depends_on'] ?? []),
                'blocked_by' => $blockedBy,
                'exists' => (bool) ($snapshot[$feature]['exists'] ?? false),
            ];
        }

        usort($rows, fn (array $a, array $b): int => [$a['category'], $a['title']] <=> [$b['category'], $b['title']]);

        return $rows;
    }

    /**
     * Persist a global or institution-specific runtime state using the existing
     * feature_flags / feature_flag_overrides schema.
     */
    public static function setStatus(
        string $feature,
        string $status,
        ?int $actorId = null,
        ?int $universityId = null,
        ?string $maintenanceMessage = null,
        ?string $adminNote = null,
    ): FeatureFlag {
        abort_unless(isset(self::definitions()[$feature]), 404, 'Unknown feature identifier.');

        $status = self::normalizeStatus($status, true);
        abort_unless(in_array($status, self::statuses(), true), 422, 'Invalid feature status.');

        $meta = self::metadata($feature);
        $flag = FeatureFlag::query()->firstOrCreate(
            ['name' => $feature],
            [
                'description' => $meta['description'] ?? $meta['title'] ?? Str::headline($feature),
                'is_enabled' => ($meta['default_status'] ?? self::STATUS_ENABLED) === self::STATUS_ENABLED,
            ]
        );

        if ($universityId !== null) {
            $override = FeatureFlagOverride::query()->firstOrNew([
                'feature_flag_id' => $flag->id,
                'university_id' => $universityId,
            ]);
            $settings = (array) ($override->settings ?? []);
            $settings['access_status'] = $status;
            $settings['maintenance_message'] = trim((string) $maintenanceMessage);
            $settings['admin_note'] = trim((string) $adminNote);

            $override->fill([
                'is_enabled' => $status === self::STATUS_ENABLED,
                'settings' => $settings,
                'updated_by' => $actorId,
            ])->save();
        } else {
            $settings = (array) ($flag->settings ?? []);
            $settings['access_status'] = $status;
            $settings['maintenance_message'] = trim((string) $maintenanceMessage);
            $settings['admin_note'] = trim((string) $adminNote);

            $flag->fill([
                'is_enabled' => $status === self::STATUS_ENABLED,
                'settings' => $settings,
                'enabled_at' => $status === self::STATUS_ENABLED ? now() : null,
                'enabled_by' => $actorId,
            ])->save();
        }

        self::forget();

        return $flag->fresh();
    }

    public static function forget(): void
    {
        self::$requestSnapshots = [];
        self::$effectiveStatusCache = [];

        try {
            $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
            Cache::forever(self::CACHE_VERSION_KEY, max(1, $current + 1));
        } catch (\Throwable) {
            // Request-local caches were already cleared. Do not make settings writes fail
            // merely because the configured cache backend is temporarily unavailable.
        }
    }

    /**
     * One cached query resolves every feature for a scope, avoiding N+1 database
     * calls from sidebars, dashboards and middleware.
     *
     * @return array<string,array{status:string,settings:array,exists:bool}>
     */
    private static function snapshot(?int $universityId): array
    {
        $scopeKey = $universityId === null ? 'global' : 'u'.$universityId;
        if (isset(self::$requestSnapshots[$scopeKey])) {
            return self::$requestSnapshots[$scopeKey];
        }

        try {
            $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
            $cacheKey = 'feature_access.snapshot.v'.$version.'.'.$scopeKey;

            $snapshot = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($universityId): array {
                $definitions = self::definitions();
                $query = FeatureFlag::query()->whereIn('name', array_keys($definitions));

                if ($universityId !== null) {
                    $query->with(['overrides' => fn ($q) => $q->where('university_id', $universityId)]);
                }

                $flags = $query->get()->keyBy('name');
                $result = [];

                foreach ($definitions as $feature => $definition) {
                    /** @var FeatureFlag|null $flag */
                    $flag = $flags->get($feature);
                    $default = self::normalizeStatus($definition['default_status'] ?? self::STATUS_ENABLED, true);
                    $settings = (array) ($flag?->settings ?? []);
                    $legacyEnabled = $flag ? (bool) $flag->is_enabled : $default === self::STATUS_ENABLED;

                    if ($universityId !== null && $flag) {
                        $override = $flag->overrides->first();
                        if ($override) {
                            $settings = array_replace_recursive($settings, (array) ($override->settings ?? []));
                            $legacyEnabled = (bool) $override->is_enabled;
                        }
                    }

                    $result[$feature] = [
                        'status' => self::normalizeStatus($settings['access_status'] ?? null, $legacyEnabled),
                        'settings' => $settings,
                        'exists' => (bool) $flag,
                    ];
                }

                return $result;
            });

            return self::$requestSnapshots[$scopeKey] = $snapshot;
        } catch (\Throwable) {
            // During install/migration, fail open to keep recovery routes usable.
            $fallback = [];
            foreach (self::definitions() as $feature => $definition) {
                $fallback[$feature] = [
                    'status' => self::normalizeStatus($definition['default_status'] ?? self::STATUS_ENABLED, true),
                    'settings' => [],
                    'exists' => false,
                ];
            }

            return self::$requestSnapshots[$scopeKey] = $fallback;
        }
    }
}
