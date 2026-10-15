<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn (string $path): string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : '';
$errors = [];

$mustExist = [
    'app/Models/CommercialAccountRequest.php',
    'app/Http/Controllers/InstitutionCommercialAccountController.php',
    'database/migrations/2026_08_31_150000_create_commercial_account_requests_table.php',
    'resources/views/institution/commercial-account.blade.php',
];
foreach ($mustExist as $file) {
    if (! is_file($root.'/'.$file)) $errors[] = "Missing {$file}";
}

$web = $read('routes/web.php');
$admin = $read('app/Http/Controllers/Admin/MonetizationController.php');
$institution = $read('app/Http/Controllers/InstitutionCommercialAccountController.php');
$view = $read('resources/views/institution/commercial-account.blade.php');
$adminView = $read('resources/views/admin/monetization/index.blade.php');
$entitlements = $read('app/Services/Commerce/EntitlementService.php');
$sidebar = $read('resources/views/partials/sidebar.blade.php');

$checks = [
    ['institution commercial dashboard route', str_contains($web, "name('institution.commercial-account')")],
    ['institution request route is throttled', str_contains($web, "throttle:commercial-account-requests")],
    ['platform-only commercial account mutation', str_contains($admin, 'abort_unless($request->user()->isSuperAdmin(),403)')],
    ['institution page is university-admin-only', str_contains($institution, 'isUniversityAdmin()')],
    ['request types are allowlisted', str_contains($institution, "Rule::in(['account_setup', 'add_funds', 'change_terms', 'sponsorship'])")],
    ['duplicate pending requests are blocked', str_contains($institution, "whereIn('status', ['pending', 'in_review'])")],
    ['admin guided setup present', str_contains($adminView, 'B2B / B2B2C onboarding') && str_contains($adminView, 'Review & apply terms')],
    ['institution request history present', str_contains($view, 'Commercial request history')],
    ['institution funding request UX present', str_contains($view, 'Add prepaid budget')],
    ['institution sponsorship request UX present', str_contains($view, 'Change sponsored AI/features')],
    ['sponsored features affect entitlement engine', str_contains($entitlements, 'sponsorsFeature($feature)')],
    ['university admin sidebar uses read-only commercial page', str_contains($sidebar, "['Commercial Account','institution.commercial-account','wallet']")],
];

// The institution controller may read the account but must not contain account
// balance mutation calls. Financial application belongs to Platform Admin.
foreach (['increment(\'prepaid_balance_minor\'', 'decrement(\'prepaid_balance_minor\'', "->update(['prepaid_balance_minor'", "prepaid_balance_minor' =>"] as $forbidden) {
    if (str_contains($institution, $forbidden)) $errors[] = "Institution request controller mutates financial account state: {$forbidden}";
}

foreach ($checks as [$label, $ok]) {
    if (! $ok) $errors[] = $label;
}

if ($errors !== []) {
    fwrite(STDERR, "Institution commercial account UX preflight: FAIL\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Institution commercial account UX preflight: PASS\n";
echo " - institution admins have a dedicated view/request workflow\n";
echo " - platform admin remains authoritative for financial/commercial mutations\n";
echo " - approved sponsored features participate in runtime entitlements\n";
