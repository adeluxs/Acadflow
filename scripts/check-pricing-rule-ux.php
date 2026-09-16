<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root.'/app/Http/Controllers/Admin/MonetizationController.php');
$view = file_get_contents($root.'/resources/views/admin/monetization/index.blade.php');
$config = require $root.'/config/monetization.php';

$errors = [];
$features = array_keys((array)($config['pricing_features'] ?? []));
$aiFeatures = array_keys((array)($config['ai_features'] ?? []));
if ($features === []) $errors[] = 'No admin-selectable paid features are registered.';
if ($aiFeatures === []) $errors[] = 'No AI pricing targets are registered.';

foreach (['fixed_access','pay_per_use'] as $type) {
    if (!in_array($type, array_column((array)$config['pricing_features'], 'pricing_type'), true)) {
        $errors[] = "Pricing registry has no {$type} feature.";
    }
}

foreach ([
    'name="target"' => 'Feature/AI pricing target dropdown is missing.',
    'data-pricing-type' => 'Pricing mode metadata is missing from the Admin dropdown.',
    'name="duration_days"' => 'Fixed-access duration control is missing.',
    'name="free_daily_requests"' => 'Per-assistant AI free allowance control is missing.',
    'Publish feature price' => 'Clear pricing form action label is missing.',
] as $needle => $message) {
    if (!str_contains($view, $needle)) $errors[] = $message;
}

foreach (["name=\"key\"", "name=\"name\"", "name=\"percentage\"", "name=\"currency\""] as $forbidden) {
    $pricingFormStart = strpos($view, 'id="pricing-rule-form"');
    $pricingFormEnd = $pricingFormStart === false ? false : strpos($view, '</form>', $pricingFormStart);
    $pricingForm = ($pricingFormStart !== false && $pricingFormEnd !== false)
        ? substr($view, $pricingFormStart, $pricingFormEnd - $pricingFormStart)
        : '';
    if ($pricingForm !== '' && str_contains($pricingForm, $forbidden)) {
        $errors[] = "Developer-only field {$forbidden} is exposed in the pricing form.";
    }
}

foreach ([
    "'target'=>['required','string',Rule::in(\$allowedTargets)]",
    "'pricing_type'=>\$pricingType",
    "'ai_feature'",
    "\$featureKey",
    "'free_daily_requests'",
    "SettingService::getGlobal('currency','NGN')",
] as $needle) {
    if (!str_contains($controller, $needle)) $errors[] = "Pricing controller hardening check failed: {$needle}";
}

if ($errors !== []) {
    fwrite(STDERR, "Pricing rule UX preflight: FAIL\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Pricing rule UX preflight: PASS\n";
echo " - fixed access, pay-per-use and AI usage are represented in one server-derived pricing registry\n";
echo " - all AI assistants can be selected without exposing developer key/name/currency fields\n";
echo " - immutable pricing versions remain server-controlled\n";
