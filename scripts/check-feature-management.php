<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$definitions = (array) (require $root.'/config/features.php')['definitions'];
$errors = [];
$allowedStatuses = ['enabled', 'maintenance', 'disabled'];

if ($definitions === []) {
    $errors[] = 'config/features.php has no definitions.';
}

$routeOwners = [];
$pathOwners = [];
foreach ($definitions as $key => $definition) {
    if (! preg_match('/^[a-z][a-z0-9_]*$/', (string) $key)) {
        $errors[] = "Invalid stable feature key: {$key}";
    }

    foreach (['title', 'category', 'description', 'default_status', 'routes', 'paths', 'depends_on'] as $required) {
        if (! array_key_exists($required, $definition)) {
            $errors[] = "{$key}: missing {$required}.";
        }
    }

    if (! in_array((string) ($definition['default_status'] ?? ''), $allowedStatuses, true)) {
        $errors[] = "{$key}: invalid default_status.";
    }

    foreach ((array) ($definition['depends_on'] ?? []) as $dependency) {
        if (! isset($definitions[$dependency])) {
            $errors[] = "{$key}: unknown dependency {$dependency}.";
        }
        if ($dependency === $key) {
            $errors[] = "{$key}: cannot depend on itself.";
        }
    }

    foreach ((array) ($definition['routes'] ?? []) as $pattern) {
        if (isset($routeOwners[$pattern]) && $routeOwners[$pattern] !== $key) {
            $errors[] = "Exact route pattern {$pattern} is owned by both {$routeOwners[$pattern]} and {$key}.";
        }
        $routeOwners[$pattern] = $key;
    }

    foreach ((array) ($definition['paths'] ?? []) as $pattern) {
        if (isset($pathOwners[$pattern]) && $pathOwners[$pattern] !== $key) {
            $errors[] = "Exact API/path pattern {$pattern} is owned by both {$pathOwners[$pattern]} and {$key}.";
        }
        $pathOwners[$pattern] = $key;
    }
}

// Dependency-cycle validation.
$visiting = [];
$visited = [];
$walk = function (string $key, array $trail = []) use (&$walk, &$visiting, &$visited, &$errors, $definitions): void {
    if (isset($visited[$key])) return;
    if (isset($visiting[$key])) {
        $errors[] = 'Dependency cycle: '.implode(' -> ', array_merge($trail, [$key]));
        return;
    }
    $visiting[$key] = true;
    foreach ((array) ($definitions[$key]['depends_on'] ?? []) as $dependency) {
        if (isset($definitions[$dependency])) {
            $walk((string) $dependency, array_merge($trail, [$key]));
        }
    }
    unset($visiting[$key]);
    $visited[$key] = true;
};
foreach (array_keys($definitions) as $key) $walk((string) $key);

$expected = [
    'dashboard', 'courses', 'course_materials', 'assignments', 'submissions', 'attendance',
    'course_discussions', 'group_collaboration', 'research_studio', 'knowledge_hub',
    'knowledge_communities', 'ai_assistant', 'notifications', 'push_notifications',
    'billing_subscriptions', 'documents_exports', 'advanced_analytics', 'pwa_enabled',
];
foreach ($expected as $key) {
    if (! isset($definitions[$key])) $errors[] = "Expected existing module {$key} is not registered.";
}

// Core recovery infrastructure must never be registered as disableable runtime features.
foreach (['authentication', 'authorization', 'admin_settings', 'feature_management', 'onboarding', 'account_security'] as $forbidden) {
    if (isset($definitions[$forbidden])) {
        $errors[] = "Core recovery feature {$forbidden} must not be disableable.";
    }
}

$bootstrap = (string) file_get_contents($root.'/bootstrap/app.php');
if (! str_contains($bootstrap, "'feature.access' => FeatureAccessMiddleware::class")) {
    $errors[] = 'feature.access middleware alias is missing.';
}
if (! str_contains($bootstrap, '$middleware->web(append: [FeatureAccessMiddleware::class])')) {
    $errors[] = 'FeatureAccessMiddleware is not appended after the web session middleware group.';
}

$apiRoutes = (string) file_get_contents($root.'/routes/api.php');
if (! str_contains($apiRoutes, "['auth:sanctum', 'feature.access', 'api.account.ready'")) {
    $errors[] = 'Protected API routes do not enforce feature.access after auth:sanctum.';
}

$webRoutes = (string) file_get_contents($root.'/routes/web.php');
if (! str_contains($webRoutes, "admin/settings/features/{feature}")) {
    $errors[] = 'Central Feature & Module Management update route is missing.';
}
if (! str_contains($webRoutes, "feature.flag:pwa_enabled")) {
    $errors[] = 'Dynamic PWA manifest is not protected by the central PWA runtime state.';
}

$settingsView = (string) file_get_contents($root.'/resources/views/settings/index.blade.php');
if (preg_match('/Feature Flags\s*<\/h2>/i', $settingsView)) {
    $errors[] = 'Legacy duplicate Feature Flags switch UI is still present in Settings.';
}

$settingService = (string) file_get_contents($root.'/app/Services/SettingService.php');
if (substr_count($settingService, "effectiveStatus('pwa_enabled'") < 3) {
    $errors[] = 'PWA legacy setting access is not fully delegated to FeatureAccessService.';
}
if (substr_count($settingService, "effectiveStatus('knowledge_hub_premium'") < 2) {
    $errors[] = 'Knowledge Hub premium legacy setting access is not fully delegated to FeatureAccessService.';
}

$notificationView = (string) file_get_contents($root.'/resources/views/admin/notifications/index.blade.php');
if (! str_contains($notificationView, 'Availability and delivery configuration are separate.')) {
    $errors[] = 'Notification channel settings do not clearly separate delivery configuration from runtime availability.';
}

$featureView = (string) file_get_contents($root.'/resources/views/settings/features.blade.php');
if (! str_contains($featureView, "@method('PUT')") || ! str_contains($featureView, "admin.settings.features.update")) {
    $errors[] = 'Feature Management form does not use the protected PUT update route.';
}

$sidebar = (string) file_get_contents($root.'/resources/views/partials/sidebar.blade.php');
if (! str_contains($sidebar, 'FeatureAccessService::shouldShowInNavigation')) {
    $errors[] = 'Sidebar does not consume centralized feature navigation visibility.';
}

$authController = (string) file_get_contents($root.'/app/Http/Controllers/Api/AuthController.php');
if (substr_count($authController, "'features' => FeatureAccessService::clientSnapshot") < 4) {
    $errors[] = 'Mobile/auth bootstrap responses do not consistently include backend-authoritative feature states.';
}

$notificationService = (string) file_get_contents($root.'/app/Services/NotificationService.php');
if (! str_contains($notificationService, "effectiveStatus('notifications'")) {
    $errors[] = 'Background notification delivery does not respect Notifications runtime availability.';
}
if (! str_contains($notificationService, "effectiveStatus('push_notifications'")) {
    $errors[] = 'Push delivery does not respect Push Notifications runtime availability.';
}

$featureSeeder = (string) file_get_contents($root.'/database/seeders/FeatureFlagSeeder.php');
if (! str_contains($featureSeeder, "config('features.definitions'")) {
    $errors[] = 'FeatureFlagSeeder is not registry-driven.';
}

$seederFiles = glob($root.'/database/seeders/*.php') ?: [];
foreach ($seederFiles as $file) {
    $contents = (string) file_get_contents($file);
    if (str_contains($contents, 'FeatureFlag::updateOrCreate')) {
        $errors[] = basename($file).': re-seeding can overwrite administrator-selected feature states.';
    }
}

$settingsSeeder = (string) file_get_contents($root.'/database/seeders/SettingsSeeder.php');
if (preg_match("/'pwa_enabled'\\s*=>/", $settingsSeeder)) {
    $errors[] = 'SettingsSeeder still creates duplicate pwa_enabled runtime setting.';
}
$ecosystemSeeder = (string) file_get_contents($root.'/database/seeders/AcadFlowEcosystemSeeder.php');
if (preg_match("/'knowledge_hub_premium_enabled'\\s*=>/", $ecosystemSeeder)) {
    $errors[] = 'AcadFlowEcosystemSeeder still creates duplicate Knowledge Hub premium runtime setting.';
}

if ($errors !== []) {
    fwrite(STDERR, "Feature management preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo 'Feature management preflight passed ('.count($definitions).' registered features/modules).'.PHP_EOL;
