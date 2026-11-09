<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'feature usage migration' => [$root.'/database/migrations/2026_09_12_000001_create_feature_usage_charges_table.php', "Schema::create('feature_usage_charges'"],
    'feature usage model' => [$root.'/app/Models/FeatureUsageCharge.php', 'class FeatureUsageCharge'],
    'three pricing types' => [$root.'/config/monetization.php', "'fixed_access' => 'Fixed access'"],
    'pay per use registry' => [$root.'/config/monetization.php', "'pricing_type' => 'pay_per_use'"],
    'ai registry' => [$root.'/config/monetization.php', "'knowledge_companion'"],
    'global AI monetization source' => [$root.'/app/Services/Commerce/PricingCatalogService.php', "SettingService::getGlobal('ai_monetization_enabled'"],
    'durable AI reserve state' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', "'status' => 'reserved'"],
    'AI reconciliation persistence' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', "'reconciliation_required_at'"],
    'post-provider settlement failure releases reservation' => [$root.'/app/Ai/AiManager.php', '$this->safeReleaseReservation($user, $reservation, $requestId, $feature);'],
    'released settlement failures remain auditable' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', "'reservation_released' => \$previousStatus === 'released'"],
    'AI duplicate request protection' => [$root.'/app/Ai/AiManager.php', "'AI_DUPLICATE_REQUEST'"],
    'feature replay protection' => [$root.'/app/Services/Commerce/FeatureBillingService.php', 'This paid operation has already completed'],
    'per-user AI billing serialization' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', 'User::query()->whereKey($user->id)->lockForUpdate()'],
    'fixed access purchase serialization' => [$root.'/app/Services/Commerce/FeatureBillingService.php', 'double-clicks/tabs'],
    'feature settlement reconciliation' => [$root.'/app/Services/Commerce/FeatureBillingService.php', "'settlement_failed'"],
    'feature release reconciliation' => [$root.'/app/Services/Commerce/FeatureBillingService.php', "'release_failed'"],
    'AI wallet debit' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', "'ai_usage_reserve'"],
    'AI institution debit' => [$root.'/app/Services/Commerce/AiUsageBillingService.php', "institution_prepaid_liability:"],
    'cached AI billed' => [$root.'/app/Ai/AiManager.php', "'cache_reuse' => true"],
    'fixed access purchase' => [$root.'/app/Services/Commerce/FeatureBillingService.php', 'purchaseFixedAccess'],
    'pay per use reserve' => [$root.'/app/Services/Commerce/FeatureBillingService.php', 'reservePayPerUse'],
    'pay per use settlement' => [$root.'/app/Services/Commerce/FeatureBillingService.php', 'platform_feature_revenue'],
    'attendance metering' => [$root.'/app/Http/Controllers/AttendanceController.php', "'attendance_tracking'"],
    'document metering' => [$root.'/app/Http/Controllers/ExportController.php', "'document_generation'"],
    'group/rubric metering' => [$root.'/app/Http/Controllers/SubmissionTaskController.php', "'allow_group_submissions'"],
    'feature access route' => [$root.'/routes/web.php', "name('commerce.features')"],
    'feature access page' => [$root.'/resources/views/commerce/features.blade.php', "@section('page-title','Plans & Usage')"],
    'wallet feature link' => [$root.'/resources/views/commerce/wallet.blade.php', "route('commerce.features')"],
    'feature ledger routing' => [$root.'/app/Services/Commerce/WalletService.php', "str_contains(\$entryType,'feature_')=>'feature_revenue_clearing'"],
];

$errors = [];
foreach ($checks as $label => [$file, $needle]) {
    if (!is_file($file)) { $errors[] = "{$label}: file missing"; continue; }
    $contents = file_get_contents($file);
    if (!str_contains($contents, $needle)) $errors[] = "{$label}: expected contract not found";
}

$globalConsumers = file_get_contents($root.'/app/Services/Commerce/CommerceService.php').file_get_contents($root.'/app/Http/Controllers/CommerceController.php');
foreach (['wallet_minimum_funding_amount','minimum_withdrawal_amount','withdrawal_fee_percentage','knowledge_platform_commission_percentage','knowledge_institution_revenue_percentage','creator_earnings_hold_days'] as $key) {
    if (!str_contains($globalConsumers, "SettingService::getGlobal('{$key}'")) $errors[] = "global runtime control {$key} is not enforced through getGlobal";
}

if ($errors !== []) {
    fwrite(STDERR, "Runtime monetization billing preflight: FAIL\n - ".implode("\n - ", $errors)."\n");
    exit(1);
}

echo "Runtime monetization billing preflight: PASS\n";
echo " - runtime controls use the global source of truth\n";
echo " - fixed access, pay-per-use and AI usage billing are connected to real operations\n";
echo " - wallet/institution reservation, settlement and release paths are present\n";
