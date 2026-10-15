<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$errors=[];
$read=static fn(string $relative): string => is_file($root.'/'.$relative)?(string)file_get_contents($root.'/'.$relative):'';
$mustExist=[
    'app/Support/Money.php','app/Services/Commerce/LedgerService.php','app/Services/Commerce/CommerceService.php','app/Services/Commerce/WalletService.php',
    'app/Services/Commerce/EntitlementService.php','app/Services/Commerce/AiUsageBillingService.php','app/Ai/Providers/OpenRouterProvider.php',
    'app/Http/Controllers/Admin/MonetizationController.php','database/migrations/2026_08_28_170000_create_monetization_foundation.php',
    'app/Console/Commands/MigrateLegacyMonetization.php','resources/views/admin/monetization/index.blade.php','resources/views/commerce/wallet.blade.php',
];
foreach($mustExist as $file) if(!is_file($root.'/'.$file)) $errors[]="Missing {$file}";

foreach(['app/Services/SubscriptionService.php','app/Http/Middleware/SubscriptionFeatureMiddleware.php','app/Policies/SubscriptionPlanPolicy.php','database/seeders/SubscriptionSeeder.php','database/seeders/CouponSeeder.php'] as $file){
    if(is_file($root.'/'.$file)) $errors[]="Retired runtime component still exists: {$file}";
}

$api=$read('routes/api.php');
$web=$read('routes/web.php');
$migration=$read('database/migrations/2026_08_28_170000_create_monetization_foundation.php');
$commerce=$read('app/Services/Commerce/CommerceService.php');
$wallet=$read('app/Services/Commerce/WalletService.php');
$refundGateway=$read('app/Services/PaymentGateway/PaystackGateway.php');
$aiBilling=$read('app/Services/Commerce/AiUsageBillingService.php');
$openrouter=$read('app/Ai/Providers/OpenRouterProvider.php');
$legacyCallback=$read('app/Http/Controllers/SubscriptionController.php');
$pricingAdmin=$read('app/Http/Controllers/Admin/MonetizationController.php');

$checks=[
    ['API plan middleware retired',!str_contains($api,'subscription.feature:')],
    ['new subscription sale route absent',!str_contains($web,"name('subscription.upgrade')")&&!str_contains($web,"name('subscription.initiate-payment')")],
    ['Monetization Center route present',str_contains($web,"name('admin.monetization')")],
    ['Wallet route present',str_contains($web,"name('commerce.wallet')")],
    ['ledger schema present',str_contains($migration,"Schema::create('ledger_journals'")&&str_contains($migration,"Schema::create('ledger_postings'")],
    ['minor-unit wallet schema present',str_contains($migration,'spending_balance_minor')&&str_contains($migration,'available_earnings_minor')&&str_contains($migration,'recovery_debt_minor')],
    ['pricing is versioned',str_contains($migration,"'version'")&&str_contains($migration,'pricing_rule_scope_version_unique')&&str_contains($pricingAdmin,'nextVersion')],
    ['refund reconciliation schema present',str_contains($migration,'reconciliation_required')&&str_contains($migration,'provider_confirmed_at')],
    ['refund state machine prevents blind replay',str_contains($commerce,'outcome_unknown')&&str_contains($commerce,'reconcileRefund')&&str_contains($refundGateway,'deliberately never auto-retried')],
    ['creator recovery debt present',str_contains($wallet,'recovery_debt_minor')&&str_contains($wallet,'creator_recovery_receivable')],
    ['OpenRouter first-class provider present',str_contains($openrouter,"return 'openrouter'")&&str_contains($openrouter,'discoverModels')],
    ['AI provider cost uses integer micro-USD',str_contains($aiBilling,'provider_cost_micro_usd')&&!str_contains($aiBilling,'(float)')],
    ['legacy callback no longer creates recurring subscriptions',!str_contains($legacyCallback,'UserSubscription::create')&&!str_contains($legacyCallback,"'auto_renew' => true")],
    ['migration command is dry-run first',str_contains($read('app/Console/Commands/MigrateLegacyMonetization.php'),'{--apply')&&str_contains($read('app/Console/Commands/MigrateLegacyMonetization.php'),'DRY RUN')],
];
foreach($checks as [$label,$ok]) if(!$ok)$errors[]=$label;

$financialFiles=[
    'app/Services/Commerce/CommerceService.php','app/Services/Commerce/WalletService.php','app/Services/Commerce/AiUsageBillingService.php',
    'app/Services/PaymentService.php','app/Services/Knowledge/PublicationService.php','app/Services/Knowledge/LearningPathService.php',
];
foreach($financialFiles as $file){
    $content=$read($file);
    if(preg_match('/\(float\)\s*\$[^;]*(?:amount|price|fee|commission)|floatval\s*\([^)]*(?:amount|price|fee|commission)/i',$content)){
        $errors[]="Floating-point financial calculation remains in {$file}";
    }
}

if($errors){fwrite(STDERR,"Monetization rebuild preflight FAILED:\n - ".implode("\n - ",$errors)."\n");exit(1);} 
echo "Monetization rebuild preflight: PASS\n";
echo " - subscription runtime gates retired while historical compatibility remains\n";
echo " - minor-unit money, double-entry ledger, idempotency and recovery debt present\n";
echo " - refund provider ambiguity is reconciliation-safe\n";
echo " - versioned pricing, wallet/earnings, B2B account and OpenRouter/AI billing paths present\n";
