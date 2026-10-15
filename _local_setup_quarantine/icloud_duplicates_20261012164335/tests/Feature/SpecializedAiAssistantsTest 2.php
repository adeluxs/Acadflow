<?php

namespace Tests\Feature;

use App\Ai\AiRouter;
use App\Services\Ai\AcademicInputQualityService;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\Ai\ContextualAssistantService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecializedAiAssistantsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $features = [
        'research_assistant',
        'assignment_assistant',
        'siwes_assistant',
        'project_assistant',
        'material_assistant',
        'discussion_assistant',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        SettingService::set('ai_mode', 'provider');
        SettingService::set('ai_default_provider', 'openai');
        SettingService::set('ai_default_model', 'gpt-4o-mini');
        SettingService::set('ai_fallback_provider', 'none');
        SettingService::set('ai_secondary_fallback_provider', 'none');
        SettingService::set('ai_provider_openai_enabled', true, 'boolean');
        SettingService::set('ai_provider_openai_model', 'gpt-4o-mini');
        SettingService::set('ai_provider_openai_models', ['gpt-4o-mini'], 'json');
        SettingService::set('ai_provider_gemini_enabled', true, 'boolean');
        SettingService::set('ai_provider_gemini_model', 'gemini-1.5-flash');
        SettingService::set('ai_provider_gemini_models', ['gemini-1.5-flash'], 'json');

        foreach ($this->features as $feature) {
            SettingService::set('ai_feature_'.$feature, true, 'boolean');
            SettingService::set('ai_feature_'.$feature.'_provider', 'global');
            SettingService::set('ai_feature_'.$feature.'_model', 'global');
        }

        app(AiRuntimeConfigService::class)->invalidate();
    }

    public function test_all_specialized_assistants_are_registered_provider_first_features(): void
    {
        $declared = (array) config('ai.features', []);
        $providerFirst = (array) config('ai.provider_first_features', []);

        $this->assertSame($this->features, ContextualAssistantService::FEATURES);

        foreach ($this->features as $feature) {
            $this->assertContains($feature, $declared);
            $this->assertContains($feature, $providerFirst);
            $this->assertSame(['chat', 'structured_output'], config('ai.feature_capabilities.'.$feature));
            $this->assertNotEmpty(config('ai.assistant_profiles.'.$feature.'.label'));
        }
    }

    public function test_all_specialized_assistants_inherit_the_global_provider_route(): void
    {
        $router = app(AiRouter::class);

        foreach ($this->features as $feature) {
            $route = $router->route($feature);
            $this->assertTrue($route['feature_enabled']);
            $this->assertSame('global', $route['requested_configuration']);
            $this->assertSame('openai', $route['resolved_provider']);
            $this->assertSame('gpt-4o-mini', $route['resolved_model']);
        }
    }

    public function test_feature_override_takes_priority_for_a_specialized_assistant(): void
    {
        SettingService::set('ai_feature_research_assistant_provider', 'gemini');
        SettingService::set('ai_feature_research_assistant_model', 'gemini-1.5-flash');
        app(AiRuntimeConfigService::class)->invalidate();

        $route = app(AiRouter::class)->route('research_assistant');

        $this->assertSame('feature_override', $route['requested_configuration']);
        $this->assertSame('gemini', $route['resolved_provider']);
        $this->assertSame('gemini-1.5-flash', $route['resolved_model']);
    }

    public function test_specialized_input_guard_rejects_keyboard_smash_before_provider_dispatch(): void
    {
        $result = app(AcademicInputQualityService::class)->assess('gsgshhshsh');

        $this->assertFalse($result['accepted']);
        $this->assertNotEmpty($result['message']);
    }
}
