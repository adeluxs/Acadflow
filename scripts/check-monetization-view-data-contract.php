<?php
$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/Admin/MonetizationController.php');
$view = file_get_contents($root.'/resources/views/admin/monetization/index.blade.php');
$failures = [];

$required = [
    'pricingFeatureGroups',
    'pricingRuleTitles',
    'setupChanges',
    'setupMetadata',
    'requestedAddMinor',
    'suggestedTargetMinor',
    'selectedSponsoredFeatures',
];

foreach ($required as $name) {
    if (!str_contains($controller, '$'.$name.'=')) {
        $failures[] = "Controller does not initialize \${$name}.";
    }
    if (!preg_match("/compact\\([^;]*['\"]".preg_quote($name, '/')."['\"]/s", $controller)) {
        $failures[] = "Controller does not pass \${$name} to the monetization view.";
    }
}

if (str_contains($view, '@php')) {
    $failures[] = 'Monetization view must not rely on Blade-local @php state; derive form/display state in the controller.';
}

foreach (['$suggestedTargetMinor', '$requestedAddMinor', '$setupChanges', '$setupMetadata', '$selectedSponsoredFeatures', '$pricingFeatureGroups', '$pricingRuleTitles'] as $needle) {
    if (!str_contains($view, $needle)) {
        $failures[] = "Expected view dependency {$needle} is missing; update this regression check if the UI contract intentionally changes.";
    }
}

if (!str_contains($controller, '$setupCurrency=$setupAccount?->currency ?:')) {
    $failures[] = 'Existing commercial account currency must remain authoritative when rendering setup.';
}
if (!str_contains($controller, '$existingAccount?->currency ?: SettingService::get')) {
    $failures[] = 'Existing commercial account currency must remain authoritative when saving setup.';
}

if ($failures) {
    fwrite(STDERR, "Monetization view-data contract: FAIL\n - ".implode("\n - ", $failures)."\n");
    exit(1);
}

echo "Monetization view-data contract: PASS\n";
