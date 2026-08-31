<?php

namespace Tests\Feature;

use App\Ai\AiManager;
use App\Services\Ai\AiPromptService;
use App\Services\Ai\AiRuntimeConfigService;
use App\Models\University;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCentralProviderRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.openai.api_key' => 'test-openai-key',
            'ai.providers.openai.model' => 'gpt-4o-mini',
            'ai.providers.gemini.api_key' => 'test-gemini-key',
            'ai.providers.gemini.model' => 'gemini-1.5-flash',
        ]);

        $this->setAi('ai_mode', 'provider');
        $this->setAi('ai_default_provider', 'openai');
        $this->setAi('ai_default_model', 'gpt-4o-mini');
        $this->setAi('ai_fallback_provider', 'none');
        $this->setAi('ai_secondary_fallback_provider', 'none');
        $this->setAi('ai_automatic_failover', true, 'boolean');
        $this->setAi('ai_retry_count', 0, 'integer');
        $this->setAi('ai_enable_cache', false, 'boolean');
        $this->setAi('ai_provider_openai_enabled', true, 'boolean');
        $this->setAi('ai_provider_openai_model', 'gpt-4o-mini');
        $this->setAi('ai_provider_openai_models', ['gpt-4o-mini'], 'json');
        $this->setAi('ai_provider_gemini_enabled', true, 'boolean');
        $this->setAi('ai_provider_gemini_model', 'gemini-1.5-flash');
        $this->setAi('ai_provider_gemini_models', ['gemini-1.5-flash'], 'json');
        $this->setAi('ai_feature_writing_assistant', true, 'boolean');
        $this->setAi('ai_feature_writing_assistant_provider', 'global');
        $this->setAi('ai_feature_writing_assistant_model', 'global');
        $this->refreshAiRuntime();
    }

    public function test_default_provider_switch_changes_the_actual_provider_used(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response($this->openAiPayload('openai response'), 200),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->geminiPayload('gemini response'), 200),
        ]);

        $first = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Improve this academic paragraph.']);
        $this->assertTrue($first->success);
        $this->assertSame('openai', $first->provider);
        $this->assertSame('gpt-4o-mini', $first->model);

        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');
        $this->refreshAiRuntime();

        $second = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Improve this different academic paragraph.']);
        $this->assertTrue($second->success);
        $this->assertSame('gemini', $second->provider);
        $this->assertSame('gemini-1.5-flash', $second->model);
    }

    public function test_feature_override_takes_priority_over_global_default(): void
    {
        $this->setAi('ai_feature_writing_assistant_provider', 'gemini');
        $this->setAi('ai_feature_writing_assistant_model', 'gemini-1.5-flash');
        $this->refreshAiRuntime();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($this->geminiPayload('feature override'), 200),
            'https://api.openai.com/*' => Http::response($this->openAiPayload('should not be primary'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Feature-specific routing test.']);

        $this->assertTrue($response->success);
        $this->assertSame('gemini', $response->provider);
        $this->assertFalse($response->fallbackUsed);
    }

    public function test_fallback_provider_is_used_when_primary_provider_fails(): void
    {
        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');
        $this->setAi('ai_fallback_provider', 'openai');
        $this->setAi('ai_fallback_model', 'gpt-4o-mini');
        $this->refreshAiRuntime();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'temporary']], 503),
            'https://api.openai.com/*' => Http::response($this->openAiPayload('fallback response'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Fallback routing test.']);

        $this->assertTrue($response->success);
        $this->assertSame('openai', $response->provider);
        $this->assertTrue($response->fallbackUsed);
        $this->assertSame('openai', $response->fallbackProvider);
    }


    public function test_secondary_fallback_is_used_when_primary_and_first_fallback_fail(): void
    {
        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');
        $this->setAi('ai_fallback_provider', 'openai');
        $this->setAi('ai_fallback_model', 'gpt-4o-mini');
        $this->setAi('ai_secondary_fallback_provider', 'deepseek');
        $this->setAi('ai_secondary_fallback_model', 'deepseek-chat');
        $this->setAi('ai_provider_deepseek_enabled', true, 'boolean');
        $this->setAi('ai_provider_deepseek_model', 'deepseek-chat');
        $this->setAi('ai_provider_deepseek_models', ['deepseek-chat'], 'json');
        config([
            'ai.providers.deepseek.api_key' => 'test-deepseek-key',
            'ai.providers.deepseek.model' => 'deepseek-chat',
        ]);
        $this->refreshAiRuntime();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'temporary']], 503),
            'https://api.openai.com/*' => Http::response(['error' => ['message' => 'temporary']], 503),
            'https://api.deepseek.com/*' => Http::response($this->openAiPayload('secondary fallback response'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Secondary fallback routing test.']);

        $this->assertTrue($response->success);
        $this->assertSame('deepseek', $response->provider);
        $this->assertSame('deepseek-chat', $response->model);
        $this->assertTrue($response->fallbackUsed);
        $this->assertSame('deepseek', $response->fallbackProvider);
    }

    public function test_disabled_primary_provider_is_not_used_and_fallback_can_handle_request(): void
    {
        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');
        $this->setAi('ai_provider_gemini_enabled', false, 'boolean');
        $this->setAi('ai_fallback_provider', 'openai');
        $this->setAi('ai_fallback_model', 'gpt-4o-mini');
        $this->refreshAiRuntime();

        Http::fake([
            'https://api.openai.com/*' => Http::response($this->openAiPayload('fallback after disabled primary'), 200),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->geminiPayload('must not be sent'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Disabled provider routing test.']);

        $this->assertTrue($response->success);
        $this->assertSame('openai', $response->provider);
        $this->assertTrue($response->fallbackUsed);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'generativelanguage.googleapis.com'));
    }

    public function test_runtime_setting_change_is_visible_without_rebuilding_config_cache(): void
    {
        $runtime = app(AiRuntimeConfigService::class);
        $this->assertSame('openai', $runtime->defaultProvider());

        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');

        $this->assertSame('gemini', $runtime->defaultProvider());
        $this->assertSame('gemini-1.5-flash', $runtime->defaultModel());
    }

    public function test_institution_can_override_runtime_route_but_not_platform_provider_protocol_configuration(): void
    {
        $university = University::factory()->create();
        $runtime = app(AiRuntimeConfigService::class);

        SettingService::set('ai_provider_openai_model', 'platform-openai-model', 'string', null);
        SettingService::set('ai_provider_openai_models', ['platform-openai-model'], 'json', null);
        SettingService::set('ai_provider_openai_model', 'tenant-must-not-win', 'string', $university->id);
        SettingService::set('ai_default_provider', 'gemini', 'string', $university->id);
        SettingService::set('ai_default_model', 'gemini-1.5-flash', 'string', $university->id);
        $this->refreshAiRuntime();

        $this->assertSame('platform-openai-model', $runtime->providerModel('openai', $university->id));
        $this->assertSame('gemini', $runtime->defaultProvider($university->id));
        $this->assertSame('gemini-1.5-flash', $runtime->defaultModel($university->id));
    }

    public function test_grok_can_be_selected_through_the_same_central_router(): void
    {
        config([
            'ai.providers.grok.api_key' => 'test-xai-key',
            'ai.providers.grok.base_url' => 'https://api.x.ai/v1',
            'ai.providers.grok.model' => 'grok-4.5',
        ]);
        $this->setAi('ai_provider_grok_enabled', true, 'boolean');
        $this->setAi('ai_provider_grok_model', 'grok-4.5');
        $this->setAi('ai_provider_grok_models', ['grok-4.5'], 'json');
        $this->setAi('ai_default_provider', 'grok');
        $this->setAi('ai_default_model', 'grok-4.5');
        $this->refreshAiRuntime();

        Http::fake([
            'https://api.x.ai/*' => Http::response($this->openAiPayload('grok routed response'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Route this request through Grok.']);

        $this->assertTrue($response->success);
        $this->assertSame('grok', $response->provider);
        $this->assertSame('grok-4.5', $response->model);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.x.ai/v1/chat/completions'));
    }

    public function test_rule_based_only_mode_never_calls_external_provider(): void
    {
        $this->setAi('ai_mode', 'rule_based');
        $this->refreshAiRuntime();
        Http::fake();

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'The thing is really bad and stuff.']);

        $this->assertTrue($response->success);
        $this->assertSame('rule_engine', $response->source);
        $this->assertNull($response->provider);
        Http::assertNothingSent();
    }

    public function test_disabled_mode_does_no_provider_processing(): void
    {
        $this->setAi('ai_mode', 'disabled');
        $this->refreshAiRuntime();
        Http::fake();

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Do not process this externally.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_DISABLED', $response->errorCode);
        Http::assertNothingSent();
    }

    public function test_feature_disabled_prevents_provider_request(): void
    {
        $this->setAi('ai_feature_writing_assistant', false, 'boolean');
        $this->refreshAiRuntime();
        Http::fake();

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'This feature is disabled.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_FEATURE_DISABLED', $response->errorCode);
        Http::assertNothingSent();
    }

    public function test_configured_context_limit_bounds_large_provider_context_without_malformed_json(): void
    {
        $this->setAi('ai_context_limit', 1000, 'integer');
        $this->refreshAiRuntime();

        $payload = app(AiPromptService::class)->enrich('writing_assistant', [
            'text' => str_repeat('Academic evidence and analysis. ', 1200),
            'notes' => array_fill(0, 80, str_repeat('Additional source context. ', 30)),
        ], null);

        $meta = $payload['_prompt']['context'];
        $this->assertTrue($meta['truncated']);
        $this->assertSame(1000, $meta['limit_tokens']);
        $this->assertLessThan($meta['chars_before'], $meta['chars_after']);
        $this->assertLessThanOrEqual(4500, strlen($payload['_prompt']['user_prompt']));
        $this->assertStringContainsString('context shortened by AcadFlow', $payload['_prompt']['user_prompt']);
    }

    public function test_provider_mode_does_not_silently_fall_back_to_rule_engine(): void
    {
        $this->setAi('ai_fallback_provider', 'none');
        $this->refreshAiRuntime();
        Http::fake(['https://api.openai.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Provider failure test.']);

        $this->assertFalse($response->success);
        $this->assertNotSame('rule_engine', $response->source);
        $this->assertNotSame('rule_engine_fallback', $response->source);
        $this->assertContains($response->errorCode, ['AI_PROVIDER_UNAVAILABLE', 'AI_ALL_PROVIDERS_FAILED']);
    }

    public function test_provider_authentication_failure_is_normalized_without_rule_fallback(): void
    {
        $this->setAi('ai_fallback_provider', 'none');
        $this->refreshAiRuntime();
        Http::fake(['https://api.openai.com/*' => Http::response(['error' => ['message' => 'invalid key']], 401)]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Authentication failure normalization test.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_PROVIDER_AUTH_FAILED', $response->errorCode);
        $this->assertNotSame('rule_engine_fallback', $response->source);
    }

    public function test_rate_limited_primary_can_fail_over_to_configured_fallback(): void
    {
        $this->setAi('ai_default_provider', 'gemini');
        $this->setAi('ai_default_model', 'gemini-1.5-flash');
        $this->setAi('ai_fallback_provider', 'openai');
        $this->setAi('ai_fallback_model', 'gpt-4o-mini');
        $this->refreshAiRuntime();

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429),
            'https://api.openai.com/*' => Http::response($this->openAiPayload('fallback after rate limit'), 200),
        ]);

        $response = app(AiManager::class)->analyze('writing_assistant', ['text' => 'Rate-limit failover test.']);

        $this->assertTrue($response->success);
        $this->assertSame('openai', $response->provider);
        $this->assertTrue($response->fallbackUsed);
    }

    private function setAi(string $key, mixed $value, string $type = 'string'): void
    {
        SettingService::set($key, $value, $type, null);
    }

    private function refreshAiRuntime(): void
    {
        app(AiRuntimeConfigService::class)->invalidate();
        app(AiManager::class)->invalidateAll();
    }

    private function openAiPayload(string $answer): array
    {
        return [
            'choices' => [['message' => ['content' => json_encode(['data' => ['answer' => $answer], 'summary' => $answer])]]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ];
    }

    private function geminiPayload(string $answer): array
    {
        return [
            'candidates' => [['content' => ['parts' => [['text' => json_encode(['data' => ['answer' => $answer], 'summary' => $answer])]]]]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
        ];
    }
}
