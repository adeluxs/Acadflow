<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\Setting;
use App\Models\SubscriptionPlan;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Str;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    /**
     * Display system settings (admin only)
     */
    public function index()
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $settings = Setting::all()->groupBy('group');
        $featureFlags = FeatureFlag::orderBy('name')->get();
        $subscriptionPlans = SubscriptionPlan::orderBy('sort_order')->get();
        $paymentGateways = \App\Models\PaymentGateway::withCount('transactions')->get();

        // Define setting groups with their display names and icons
        $settingGroups = [
            'general' => ['name' => 'General Settings', 'icon' => 'cog', 'description' => 'Platform name, branding, timezone'],
            'academic' => ['name' => 'Academic Settings', 'icon' => 'academic-cap', 'description' => 'Semesters, submissions, grading rules'],
            'notification' => ['name' => 'Notification Settings', 'icon' => 'bell', 'description' => 'Channels, templates, reminders'],
            'subscription' => ['name' => 'Subscription Settings', 'icon' => 'credit-card', 'description' => 'Billing, trials, plan rules'],
            'security' => ['name' => 'Security Settings', 'icon' => 'shield-check', 'description' => 'Passwords, sessions, 2FA, audit logs'],
            'pwa' => ['name' => 'PWA Settings', 'icon' => 'device-mobile', 'description' => 'Offline mode, caching, sync'],
            'storage' => ['name' => 'Storage Settings', 'icon' => 'database', 'description' => 'File uploads, retention, archives'],
        ];

        return view('settings.index', compact('settings', 'featureFlags', 'subscriptionPlans', 'paymentGateways', 'settingGroups'));
    }

    /**
     * Update setting
     */
    public function update(Request $request, string $key)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $setting = Setting::where('key', $key)->firstOrFail();

        // Validate based on type
        $rules = ['value' => 'required'];
        switch ($setting->type) {
            case 'integer':
                $rules['value'] = 'required|integer';
                break;
            case 'boolean':
                $rules['value'] = 'required|boolean';
                break;
            case 'json':
                $rules['value'] = 'required|json';
                break;
            default:
                $rules['value'] = 'required|string';
        }

        $validator = Validator::make($request->all(), $rules);
        $validator->validate();

        $setting->update(['value' => $request->input('value')]);

        // Clear cache
        Cache::forget('settings.all');

        return back()->with('success', "Setting '{$setting->key}' updated successfully.");
    }

    /**
     * Update multiple settings at once
     */
    public function updateMultiple(Request $request)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $settings = $request->input('settings', []);

        foreach ($settings as $key => $value) {
            $setting = Setting::where('key', $key)->first();
            if ($setting) {
                $oldValue = $setting->value;
                $setting->update(['value' => $value]);
                
                // Log the change if audit logging is enabled
                if (config('settings.enable_audit_logs', true)) {
                    AuditLog::log(
                        'setting_updated',
                        $user->id,
                        Setting::class,
                        $setting->id,
                        $key,
                        $oldValue,
                        $value,
                        $request->ip(),
                        $request->userAgent()
                    );
                }
            }
        }

        Cache::forget('settings.all');

        return back()->with('success', 'Settings updated successfully.');
    }

    /**
     * Toggle feature flag
     */
    public function toggleFeatureFlag(Request $request, FeatureFlag $featureFlag)
    {
        $user = Auth::user();
        if (! $user->isAdmin()) {
            abort(403);
        }

        $featureFlag->update([
            'is_enabled' => ! $featureFlag->is_enabled,
            'enabled_at' => ! $featureFlag->is_enabled ? now() : null,
            'enabled_by' => ! $featureFlag->is_enabled ? $user->id : null,
        ]);

        return back()->with('success', "Feature flag '{$featureFlag->name}' updated.");
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

        $settings = Setting::all()->groupBy('group');

        return response()->json($settings);
    }

    /**
     * Return dynamic PWA manifest based on settings
     */
    public function pwaManifest()
    {
        $siteName = SettingService::get('site_name', 'UniFlow');
        $shortName = Str::limit($siteName, 12, '');
        $themeColor = SettingService::get('pwa_theme_color', '#4f46e5');
        $backgroundColor = SettingService::get('pwa_background_color', '#ffffff');
        
        $manifest = [
            'name' => $siteName,
            'short_name' => $shortName,
            'description' => SettingService::get('site_tagline', 'University Academic Workflow Platform'),
            'start_url' => '/dashboard',
            'display' => SettingService::get('pwa_display', 'standalone'),
            'background_color' => $backgroundColor,
            'theme_color' => $themeColor,
            'orientation' => SettingService::get('pwa_orientation', 'portrait-primary'),
            'icons' => [
                [
                    'src' => '/icons/icon-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any maskable'
                ],
                [
                    'src' => '/icons/icon-512x512.png',
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
