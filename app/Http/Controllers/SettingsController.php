<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Services\SettingService;
use App\Services\FeatureAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    /** Platform-only settings cannot be overridden by institution admins. */
    private const GLOBAL_ONLY_GROUPS = ['subscription', 'pwa'];
    private const GLOBAL_ONLY_KEYS = ['maintenance_mode', 'maintenance_mode_bypass_routes'];
    private const RUNTIME_AVAILABILITY_SETTING_KEYS = ['pwa_enabled', 'knowledge_hub_premium_enabled'];

    /**
     * Display system settings (admin only)
     */
    public function index()
    {
        $user = Auth::user();
        if (! ($user->isSuperAdmin() || $user->isUniversityAdmin())) {
            abort(403);
        }

        $scopeUniversityId = $user->isSuperAdmin() ? null : $user->university_id;
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()
            ->reject(fn (Setting $setting) => SettingService::canonicalKey($setting->key) !== $setting->key)
            ->reject(fn (Setting $setting) => in_array($setting->key, self::RUNTIME_AVAILABILITY_SETTING_KEYS, true))
            // AI runtime/provider settings have one authoritative management UI:
            // Admin -> AI Settings. They must not appear again in System Settings.
            ->reject(fn (Setting $setting) => in_array($setting->group, ['ai', 'ai_legacy'], true))
            ->reject(fn (Setting $setting) => ! $user->isSuperAdmin() && $this->isGlobalOnlySetting($setting))
            ->each(function (Setting $setting) use ($scopeUniversityId, $user): void {
                $setting->value = $user->isSuperAdmin()
                    ? SettingService::getGlobal($setting->key, $setting->value)
                    : SettingService::get($setting->key, $setting->value, $scopeUniversityId);
            })
            ->groupBy('group');
        // Global commercial controls are only relevant to the platform owner.
        // Runtime feature availability is managed on the dedicated child page
        // under Settings so there is only one switch per feature.
        $subscriptionPlans = $user->isSuperAdmin() ? SubscriptionPlan::orderBy('sort_order')->get() : collect();
        $paymentGateways = $user->isSuperAdmin()
            ? \App\Models\PaymentGateway::withCount('transactions')->get()
            : collect();

        // Define setting groups with their display names and icons
        $settingGroups = [
            'general' => ['name' => 'General Settings', 'icon' => 'cog', 'description' => 'Platform name, branding, timezone'],
            'academic' => ['name' => 'Academic Settings', 'icon' => 'academic-cap', 'description' => 'Submissions, grading, and course membership rules'],
            'notification' => ['name' => 'Notification Settings', 'icon' => 'bell', 'description' => 'Channels, templates, reminders'],
            'subscription' => ['name' => 'Subscription Settings', 'icon' => 'credit-card', 'description' => 'Billing, trials, plan rules'],
            'security' => ['name' => 'Security Settings', 'icon' => 'shield-check', 'description' => 'Passwords, sessions, 2FA, audit logs'],
            'pwa' => ['name' => 'PWA Settings', 'icon' => 'device-mobile', 'description' => 'Offline mode, caching, sync'],
            'storage' => ['name' => 'Storage Settings', 'icon' => 'database', 'description' => 'File uploads, retention, archives'],
        ];
        $settingGroups = array_intersect_key($settingGroups, array_fill_keys($settings->keys()->all(), true));

        return view('settings.index', compact('settings', 'subscriptionPlans', 'paymentGateways', 'settingGroups'));
    }

    /**
     * Update setting
     */
    public function update(Request $request, string $key)
    {
        $user = Auth::user();
        if (! ($user->isSuperAdmin() || $user->isUniversityAdmin())) {
            abort(403);
        }

        $key = SettingService::canonicalKey($key);
        $setting = Setting::where('key', $key)->firstOrFail();
        abort_if(! $user->isSuperAdmin() && $this->isGlobalOnlySetting($setting), 403, 'This is a platform-only setting.');

        $rules = ['value' => 'required'];
        $rules['value'] = match ($setting->type) {
            'integer' => 'required|integer',
            'boolean' => 'required|boolean',
            'json' => 'required|json',
            default => 'required|string',
        };
        $validated = Validator::make($request->all(), $rules)->validate();

        $scope = $user->isSuperAdmin() ? null : $user->university_id;
        $oldValue = $user->isSuperAdmin()
            ? SettingService::getGlobal($key, $setting->value)
            : SettingService::get($key, $setting->value, $scope);

        SettingService::set($key, $validated['value'], $setting->type ?: 'string', $scope, $user->id);
        $this->auditSettingChange($request, $setting, $oldValue, $validated['value']);

        return back()->with('success', "Setting '{$setting->key}' updated successfully.");
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple(Request $request)
    {
        $user = Auth::user();
        if (! ($user->isSuperAdmin() || $user->isUniversityAdmin())) {
            abort(403);
        }

        $incoming = $request->input('settings', []);
        abort_unless(is_array($incoming), 422, 'Invalid settings payload.');
        $scope = $user->isSuperAdmin() ? null : $user->university_id;

        foreach ($incoming as $rawKey => $value) {
            $key = SettingService::canonicalKey((string) $rawKey);
            $setting = Setting::where('key', $key)->first();
            if (! $setting) continue;
            abort_if(! $user->isSuperAdmin() && $this->isGlobalOnlySetting($setting), 403, 'A platform-only setting was included in the request.');

            $validator = Validator::make(['value' => $value], [
                'value' => match ($setting->type) {
                    'integer' => ['required', 'integer'],
                    'boolean' => ['required', 'boolean'],
                    'json' => ['required', 'json'],
                    default => ['nullable', 'string'],
                },
            ]);
            $validated = $validator->validate();
            $newValue = $validated['value'] ?? '';
            $oldValue = $user->isSuperAdmin()
                ? SettingService::getGlobal($key, $setting->value)
                : SettingService::get($key, $setting->value, $scope);

            SettingService::set($key, $newValue, $setting->type ?: 'string', $scope, $user->id);
            $this->auditSettingChange($request, $setting, $oldValue, $newValue);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Backwards-compatible legacy toggle route. The old button has been removed;
     * this action now writes through the centralized FeatureAccessService.
     */
    public function toggleFeatureFlag(Request $request, FeatureFlag $featureFlag)
    {
        $user = Auth::user();
        abort_unless($user?->isSuperAdmin(), 403);

        $previousStatus = FeatureAccessService::status($featureFlag->name);
        $nextStatus = $previousStatus === FeatureAccessService::STATUS_ENABLED
            ? FeatureAccessService::STATUS_DISABLED
            : FeatureAccessService::STATUS_ENABLED;

        FeatureAccessService::setStatus($featureFlag->name, $nextStatus, $user->id);
        $this->auditFeatureChange($request, $featureFlag, $previousStatus, $nextStatus, null);

        return redirect()->route('admin.settings.features')
            ->with('success', "Feature '{$featureFlag->name}' updated through centralized Feature & Module Management.");
    }

    /**
     * Centralized feature/module availability management (platform owner only).
     */
    public function features()
    {
        $user = Auth::user();
        abort_unless($user?->isSuperAdmin(), 403);

        $features = FeatureAccessService::managementRows();
        $categories = collect($features)->pluck('category')->filter()->unique()->sort()->values()->all();
        $statuses = FeatureAccessService::statuses();

        return view('settings.features', compact('features', 'categories', 'statuses'));
    }

    /**
     * Update one authoritative runtime availability state.
     */
    public function updateFeature(Request $request, string $feature)
    {
        $user = Auth::user();
        abort_unless($user?->isSuperAdmin(), 403);
        abort_unless(isset(FeatureAccessService::definitions()[$feature]), 404, 'Unknown feature identifier.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(FeatureAccessService::statuses())],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousStatus = FeatureAccessService::status($feature);
        $previousSettings = FeatureAccessService::settings($feature);
        $flag = FeatureAccessService::setStatus(
            $feature,
            $validated['status'],
            $user->id,
            null,
            $validated['maintenance_message'] ?? '',
            $validated['admin_note'] ?? '',
        );

        $this->auditFeatureChange(
            $request,
            $flag,
            $previousStatus,
            $validated['status'],
            [
                'previous_message' => $previousSettings['maintenance_message'] ?? null,
                'new_message' => $validated['maintenance_message'] ?? null,
                'admin_note' => $validated['admin_note'] ?? null,
            ],
        );

        $effectiveStatus = FeatureAccessService::effectiveStatus($feature);
        $message = "{$feature} is now {$validated['status']}.";
        if ($validated['status'] === FeatureAccessService::STATUS_ENABLED
            && $effectiveStatus !== FeatureAccessService::STATUS_ENABLED) {
            $message .= " Its effective status is {$effectiveStatus} because a required parent feature is unavailable.";
        }

        return back()->with('success', $message);
    }

    /**
     * Get public settings as JSON (no auth required)
     */
    public function publicSettings()
    {
        $settings = [
            'site_name' => SettingService::get('site_name', 'AcadFlow'),
            'site_tagline' => SettingService::get('site_tagline', 'Academic research, publishing and collaboration platform'),
            'support_email' => SettingService::get('support_email', 'support@example.com'),
            'timezone' => SettingService::get('timezone', 'UTC'),
            'site_logo' => SettingService::get('site_logo'),
            'site_favicon' => SettingService::get('site_favicon'),
            'default_language' => SettingService::get('default_language', 'en'),
            'pwa_enabled' => SettingService::isPwaEnabled(),
            'maintenance_mode' => SettingService::isMaintenanceMode(),
            'features' => FeatureAccessService::clientSnapshot(),
        ];

        return response()->json($settings);
    }

    /**
     * Get settings as JSON (for API)
     */
    public function apiSettings()
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $scope = $user->isSuperAdmin() ? null : $user->university_id;

        return response()->json([
            'settings' => SettingService::allGrouped($scope),
            'features' => FeatureAccessService::clientSnapshot($user),
        ]);
    }

    /**
     * Return dynamic PWA manifest based on settings
     */
    public function pwaManifest()
    {
        $siteName = SettingService::get('site_name', 'AcadFlow');
        $shortName = Str::limit($siteName, 12, '');
        $themeColor = SettingService::get('pwa_theme_color', '#4f46e5');
        $backgroundColor = SettingService::get('pwa_background_color', '#ffffff');
        
        $manifest = [
            'name' => $siteName,
            'short_name' => $shortName,
            'description' => SettingService::get('site_tagline', 'Academic research, publishing and collaboration platform'),
            'start_url' => '/dashboard',
            'display' => SettingService::get('pwa_display', 'standalone'),
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'orientation' => SettingService::get('pwa_orientation', 'portrait-primary'),
            'icons' => [
                [
                    'src' => SettingService::get('site_logo') ?: '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => SettingService::get('site_logo') ?: '/icons/icon-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ]
            ]
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Display permission management page
     */
    public function permissions()
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $roles = [
            'super_admin' => [
                'groups' => $this->groupPermissions(Permission::superAdminPermissions()),
                'assigned' => Permission::superAdminPermissions(),
            ],
            'university_admin' => [
                'groups' => $this->groupPermissions(Permission::universityAdminPermissions()),
                'assigned' => Permission::universityAdminPermissions(),
            ],
            'department_admin' => [
                'groups' => $this->groupPermissions(Permission::departmentAdminPermissions()),
                'assigned' => Permission::departmentAdminPermissions(),
            ],
            'lecturer' => [
                'groups' => $this->groupPermissions(Permission::lecturerPermissions()),
                'assigned' => Permission::lecturerPermissions(),
            ],
            'student' => [
                'groups' => $this->groupPermissions(Permission::studentPermissions()),
                'assigned' => Permission::studentPermissions(),
            ],
            'member' => [
                'groups' => $this->groupPermissions(Permission::memberPermissions()),
                'assigned' => Permission::memberPermissions(),
            ],
        ];

        $allPermissions = Permission::cases();

        return view('settings.permissions', compact('roles', 'allPermissions'));
    }

    /**
     * Display audit logs
     */
    public function auditLogs(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $query = AuditLog::with('user')->latest();

        // Filter by action
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model type
        if ($request->model_type) {
            $query->where('model_type', $request->model_type);
        }

        // Filter by date range
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(50);

        $actions = AuditLog::distinct()->pluck('action');
        $users = \App\Models\User::whereIn('id', AuditLog::distinct()->pluck('user_id')->filter())->get();

        return view('settings.audit-logs', compact('logs', 'actions', 'users'));
    }

    private function auditSettingChange(Request $request, Setting $setting, mixed $oldValue, mixed $newValue): void
    {
        if (! SettingService::get('enable_audit_logs', true, $request->user()?->university_id)) {
            return;
        }

        AuditLog::log(
            'setting_updated',
            $request->user()?->id,
            Setting::class,
            $setting->id,
            is_scalar($oldValue) || $oldValue === null ? $oldValue : json_encode($oldValue),
            is_scalar($newValue) || $newValue === null ? $newValue : json_encode($newValue),
            $request->ip(),
            $request->userAgent()
        );
    }

    private function auditFeatureChange(Request $request, FeatureFlag $featureFlag, string $oldStatus, string $newStatus, ?array $details): void
    {
        if (! SettingService::get('enable_audit_logs', true, $request->user()?->university_id)) {
            return;
        }

        AuditLog::log(
            'feature_status_changed',
            $request->user()?->id,
            FeatureFlag::class,
            $featureFlag->id,
            ['status' => $oldStatus],
            ['status' => $newStatus, 'details' => $details],
            $request->ip(),
            $request->userAgent(),
            'access_status',
        );
    }

    private function isGlobalOnlySetting(Setting $setting): bool
    {
        return in_array($setting->group, self::GLOBAL_ONLY_GROUPS, true)
            || in_array($setting->key, self::GLOBAL_ONLY_KEYS, true);
    }

    /**
     * Group permissions by category
     */
    private function groupPermissions(array $permissions): array
    {
        $groups = [
            'User Management' => [],
            'Department & Faculty' => [],
            'Course Management' => [],
            'Submissions' => [],
            'Groups' => [],
            'Attendance' => [],
            'Billing' => [],
            'Documents' => [],
            'Analytics' => [],
            'Notifications' => [],
            'Settings' => [],
        ];

        foreach ($permissions as $permission) {
            $perm = is_string($permission) ? Permission::from($permission) : $permission;
            
            if (str_contains($perm->value, 'user') || str_contains($perm->value, 'role')) {
                $groups['User Management'][] = $perm;
            } elseif (str_contains($perm->value, 'faculty') || str_contains($perm->value, 'department')) {
                $groups['Department & Faculty'][] = $perm;
            } elseif (str_contains($perm->value, 'course')) {
                $groups['Course Management'][] = $perm;
            } elseif (str_contains($perm->value, 'submission') || str_contains($perm->value, 'grade')) {
                $groups['Submissions'][] = $perm;
            } elseif (str_contains($perm->value, 'group')) {
                $groups['Groups'][] = $perm;
            } elseif (str_contains($perm->value, 'attendance')) {
                $groups['Attendance'][] = $perm;
            } elseif (str_contains($perm->value, 'invoice') || str_contains($perm->value, 'payment') || str_contains($perm->value, 'bill')) {
                $groups['Billing'][] = $perm;
            } elseif (str_contains($perm->value, 'document') || str_contains($perm->value, 'template')) {
                $groups['Documents'][] = $perm;
            } elseif (str_contains($perm->value, 'analytic') || str_contains($perm->value, 'export') || str_contains($perm->value, 'report')) {
                $groups['Analytics'][] = $perm;
            } elseif (str_contains($perm->value, 'notification')) {
                $groups['Notifications'][] = $perm;
            } elseif (str_contains($perm->value, 'setting') || str_contains($perm->value, 'config')) {
                $groups['Settings'][] = $perm;
            }
        }

        return array_filter($groups);
    }
}
