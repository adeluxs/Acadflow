<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FeatureAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class FeatureModuleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_feature_blocks_normal_user_but_admin_keeps_preview_access(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        FeatureAccessService::setStatus('ai_assistant', 'disabled', $admin->id);

        $this->assertFalse(FeatureAccessService::canAccessFeature('ai_assistant', $student));
        $this->assertTrue(FeatureAccessService::canAccessFeature('ai_assistant', $admin));
    }

    public function test_maintenance_feature_returns_predictable_api_contract(): void
    {
        FeatureAccessService::setStatus(
            'knowledge_hub',
            'maintenance',
            null,
            null,
            'Knowledge Hub is being upgraded. Please check back shortly.',
        );

        $request = Request::create('/api/v1/knowledge', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = FeatureAccessService::unavailableResponse($request, 'knowledge_hub');
        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('FEATURE_MAINTENANCE', $payload['status_code']);
        $this->assertSame('maintenance', $payload['feature_status']);
        $this->assertSame('Knowledge Hub is being upgraded. Please check back shortly.', $payload['message']);
    }

    public function test_dependency_restriction_is_central_and_effective(): void
    {
        FeatureAccessService::setStatus('knowledge_hub', 'disabled');
        FeatureAccessService::setStatus('knowledge_communities', 'enabled');

        $this->assertSame('enabled', FeatureAccessService::status('knowledge_communities'));
        $this->assertSame('disabled', FeatureAccessService::effectiveStatus('knowledge_communities'));
    }

    public function test_disabled_feature_is_hidden_from_normal_navigation_but_visible_to_admin(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'super_admin']);
        FeatureAccessService::setStatus('research_studio', 'disabled', $admin->id);

        $this->assertFalse(FeatureAccessService::shouldShowInNavigation('research_studio', $student));
        $this->assertTrue(FeatureAccessService::shouldShowInNavigation('research_studio', $admin));
    }
}
