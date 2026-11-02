<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class SubscriptionRetirementArchitectureTest extends TestCase
{
    public function test_runtime_routes_do_not_expose_new_subscription_sales_or_plan_gates(): void
    {
        $web = file_get_contents(base_path('routes/web.php'));
        $api = file_get_contents(base_path('routes/api.php'));
        $seed = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('subscription.feature:', $api);
        $this->assertStringNotContainsString("name('subscription.upgrade')", $web);
        $this->assertStringNotContainsString("name('subscription.initiate-payment')", $web);
        $this->assertStringNotContainsString('SubscriptionSeeder::class', $seed);
        $this->assertStringNotContainsString('CouponSeeder::class', $seed);
        $this->assertStringContainsString("name('admin.monetization')", $web);
        $this->assertStringContainsString("name('commerce.wallet')", $web);
    }

    public function test_legacy_renewal_command_is_a_no_op_tombstone(): void
    {
        $command = file_get_contents(base_path('app/Console/Commands/ProcessSubscriptionRenewals.php'));
        $this->assertStringContainsString('No action taken', $command);
        $this->assertStringNotContainsString('renewSubscription(', $command);
    }
    public function test_retired_subscription_runtime_components_are_removed_but_compatibility_callback_remains(): void
    {
        $this->assertFileDoesNotExist(base_path('app/Services/SubscriptionService.php'));
        $this->assertFileDoesNotExist(base_path('app/Http/Middleware/SubscriptionFeatureMiddleware.php'));
        $this->assertFileDoesNotExist(base_path('app/Policies/SubscriptionPlanPolicy.php'));
        $this->assertFileDoesNotExist(base_path('database/seeders/SubscriptionSeeder.php'));
        $this->assertFileDoesNotExist(base_path('database/seeders/CouponSeeder.php'));

        $controller=file_get_contents(base_path('app/Http/Controllers/SubscriptionController.php'));
        $this->assertStringContainsString('Compatibility', $controller);
        $this->assertStringNotContainsString('UserSubscription::create', $controller);
        $this->assertStringNotContainsString("'auto_renew' => true", $controller);
    }

    public function test_new_financial_foundation_is_minor_unit_versioned_and_reconciliation_safe(): void
    {
        $migration=file_get_contents(base_path('database/migrations/2026_08_28_170000_create_monetization_foundation.php'));
        $commerce=file_get_contents(base_path('app/Services/Commerce/CommerceService.php'));
        $pricing=file_get_contents(base_path('app/Http/Controllers/Admin/MonetizationController.php'));

        $this->assertStringContainsString("'version'", $migration);
        $this->assertStringContainsString('reconciliation_required', $migration);
        $this->assertStringContainsString('provider_confirmed_at', $migration);
        $this->assertStringContainsString('outcome_unknown', $commerce);
        $this->assertStringContainsString('reconcileRefund', $commerce);
        $this->assertStringContainsString('nextVersion', $pricing);
    }

}
