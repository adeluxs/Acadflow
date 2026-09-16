<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$mustContain = static function (string $file, array $needles, string $label) use (&$errors, $root): void {
    $path = $root.'/'.$file;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    if ($contents === '') {
        $errors[] = "{$label}: missing or empty {$file}.";
        return;
    }
    foreach ($needles as $needle) {
        if (! str_contains($contents, $needle)) {
            $errors[] = "{$label}: expected {$needle} in {$file}.";
        }
    }
};

$onboarding = (string) file_get_contents($root.'/app/Services/OnboardingService.php');
foreach (["'student' => 'Student'", "'lecturer' => 'Lecturer or supervisor'", "'researcher' => 'Researcher'"] as $needle) {
    if (! str_contains($onboarding, $needle)) $errors[] = "Public onboarding path missing {$needle}.";
}
$institutionalBlock = preg_match('/public const INSTITUTIONAL_PATHS\s*=\s*\[(.*?)\];/s', $onboarding, $m) ? $m[1] : '';
foreach (['student', 'lecturer', 'researcher'] as $path) {
    if (str_contains($institutionalBlock, "'{$path}'")) $errors[] = "{$path} must not be an institution-required onboarding path.";
}
if (! str_contains($onboarding, "'lecturer' => 'lecturer'")) $errors[] = 'Onboarding does not promote lecturer account_type to lecturer role.';

$mustContain('app/Http/Controllers/WebAuthController.php', [
    "array_keys(OnboardingService::ACCOUNT_PATHS)",
    "'lecturer' => 'lecturer'",
], 'Web registration');
$mustContain('app/Http/Controllers/Api/AuthController.php', [
    "array_keys(OnboardingService::ACCOUNT_PATHS)",
    "'lecturer' => 'lecturer'",
], 'API registration');

$migration = (string) file_get_contents($root.'/database/migrations/2026_09_15_220000_enable_independent_first_launch.php');
foreach (['access_mode', 'owner_user_id', 'workspace_mode', "where('account_type', 'lecturer')->where('role', 'member')->update(['role' => 'lecturer'", 'feature.allow_group_submissions', 'feature.allow_rubrics', 'feature.attendance_tracking'] as $needle) {
    if (! str_contains($migration, $needle)) $errors[] = "Launch migration missing {$needle}.";
}

$features = (array) (require $root.'/config/features.php')['definitions'];
$enabled = ['dashboard','courses','course_materials','assignments','submissions','research_studio','knowledge_hub','ai_assistant','notifications','monetization_commerce','wallet_billing','documents_exports','pwa_enabled'];
$disabled = ['attendance','course_discussions','group_collaboration','research_to_knowledge_hub','siwes_module','seminar_module','final_year_project','knowledge_communities','learning_paths','reading_lists','academic_events','academic_challenges','course_certificates','knowledge_ai_companion','knowledge_hub_premium','commerce_marketplace','creator_payouts','advanced_analytics','push_notifications','institution_self_service'];
foreach ($enabled as $key) {
    if (($features[$key]['default_status'] ?? null) !== 'enabled') $errors[] = "{$key} should default to enabled for independent-first launch.";
}
foreach ($disabled as $key) {
    if (($features[$key]['default_status'] ?? null) !== 'disabled') $errors[] = "{$key} should default to disabled and remain Admin-controllable.";
}
foreach (['wallet_billing','commerce_marketplace','creator_payouts'] as $key) {
    if (! isset($features[$key])) $errors[] = "Commerce split feature {$key} is not registered.";
}

$mustContain('app/Services/IndependentAcademicContextService.php', [
    "public const UNIVERSITY_CODE = 'ACADFIND'",
    'hidden_from_affiliation',
    'ensureResearchType',
], 'Independent academic context');
$mustContain('app/Http/Controllers/CourseController.php', [
    'function storeIndependent',
    "'access_mode' => 'independent'",
    '\'owner_user_id\' => $user->id',
    'LecturerCourseAssignment::query()->create',
], 'Web independent classroom');
$mustContain('app/Http/Controllers/Api/CourseController.php', [
    'IndependentAcademicContextService $independent',
    "'access_mode' => 'independent'",
    '\'owner_user_id\' => $user->id',
    'LecturerCourseAssignment::query()->create',
], 'API independent classroom');
$mustContain('app/Http/Controllers/Concerns/ScopesTenantData.php', [
    "->where('access_mode', 'independent')",
    '$user->canAccessCourse($course)',
], 'Independent course tenant scope');

$mustContain('app/Http/Controllers/ResearchProjectController.php', [
    'IndependentAcademicContextService $independent',
    '$data[\'workspace_mode\'] = $workspaceMode',
    '$data[\'university_id\'] = $context[\'university\']->id',
], 'Web independent research');
$mustContain('app/Http/Controllers/Api/ResearchController.php', [
    'IndependentAcademicContextService $independent',
    '$data[\'workspace_mode\'] = $workspaceMode',
    '$data[\'university_id\'] = $context[\'university\']->id',
], 'API independent research');
$mustContain('app/Policies/ResearchProjectPolicy.php', [
    'if ($project->isIndependent())',
    '$project->owner_id === $user->id',
    '->where(\'user_id\', $user->id)->exists()',
], 'Independent research policy');


$mustContain('app/Policies/ResearchProjectPolicy.php', [
    '$user->account_type === \'researcher\'',
], 'Independent researcher creation');
$mustContain('app/Http/Controllers/DashboardController.php', [
    "->with('course:id,uuid,code,name,access_mode')",
    'FeatureAccessService::canAccessFeature(\'attendance\', $user)',
    'FeatureAccessService::canAccessFeature(\'academic_events\', $user)',
], 'Independent-first dashboards');
$mustContain('app/Services/InstitutionAdoptionService.php', [
    "where('code', '!=', IndependentAcademicContextService::UNIVERSITY_CODE)",
], 'Hidden synthetic institution');
$mustContain('app/Http/Controllers/OnboardingController.php', [
    "where('code', '!=', 'ACADFIND')",
], 'Web affiliation picker');
$mustContain('app/Http/Controllers/Api/OnboardingController.php', [
    "where('code', '!=', 'ACADFIND')",
], 'API affiliation picker');

$featureService = (string) file_get_contents($root.'/app/Services/FeatureAccessService.php');
if (! str_contains($featureService, 'function setStatus') || ! str_contains($featureService, "config('features.definitions'")) {
    $errors[] = 'Central Admin Feature & Module Management is not still the source of truth.';
}

$registerView = (string) file_get_contents($root.'/resources/views/auth/register.blade.php');
foreach (['Student', 'Lecturer / Supervisor', 'Researcher'] as $label) {
    if (! str_contains($registerView, $label)) $errors[] = "Registration UI missing {$label}.";
}

if ($errors !== []) {
    fwrite(STDERR, "Independent-first launch preflight FAILED:\n - ".implode("\n - ", array_unique($errors))."\n");
    exit(1);
}

echo 'Independent-first launch preflight passed (public personas, Admin-controlled launch flags, independent classroom/research, web/API parity).'.PHP_EOL;
