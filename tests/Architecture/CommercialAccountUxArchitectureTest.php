<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

class CommercialAccountUxArchitectureTest extends TestCase
{
    public function test_institution_admin_has_separate_request_workflow_from_platform_monetization(): void
    {
        $web = file_get_contents(base_path('routes/web.php'));
        $sidebar = file_get_contents(base_path('resources/views/partials/sidebar.blade.php'));
        $institutionController = file_get_contents(base_path('app/Http/Controllers/InstitutionCommercialAccountController.php'));

        $this->assertStringContainsString("name('institution.commercial-account')", $web);
        $this->assertStringContainsString('throttle:commercial-account-requests', $web);
        $this->assertStringContainsString("['Commercial Account','institution.commercial-account','wallet']", $sidebar);
        $this->assertStringContainsString('isUniversityAdmin()', $institutionController);
        $this->assertStringNotContainsString("increment('prepaid_balance_minor'", $institutionController);
        $this->assertStringNotContainsString("decrement('prepaid_balance_minor'", $institutionController);
    }

    public function test_platform_admin_applies_requests_and_sponsored_features_are_runtime_entitlements(): void
    {
        $admin = file_get_contents(base_path('app/Http/Controllers/Admin/MonetizationController.php'));
        $entitlement = file_get_contents(base_path('app/Services/Commerce/EntitlementService.php'));
        $view = file_get_contents(base_path('resources/views/admin/monetization/index.blade.php'));

        $this->assertStringContainsString('isSuperAdmin()', $admin);
        $this->assertStringContainsString("'status'=>'approved'", $admin);
        $this->assertStringContainsString('sponsorsFeature($feature)', $entitlement);
        $this->assertStringContainsString('Review & apply terms', $view);
    }
}
