<?php

namespace Tests\Feature;

use App\Ai\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterProviderTest extends TestCase
{
    public function test_openrouter_uses_the_standard_provider_adapter_and_captures_usage(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['data' => ['answer' => 'ok']])]]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 4, 'cost' => 0.00012],
            ], 200),
        ]);

        $provider = new OpenRouterProvider([
            'enabled' => true,
            'api_key' => 'test-key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'openai/gpt-4o-mini',
            'retry_count' => 0,
        ]);
        $response = $provider->handle('writing_assistant', ['text' => 'test']);

        $this->assertTrue($response->success);
        $this->assertSame('openrouter', $response->provider);
        $this->assertSame('openai/gpt-4o-mini', $response->model);
        $this->assertSame(12, $response->inputTokens);
        $this->assertSame(4, $response->outputTokens);
    }

    public function test_openrouter_model_catalog_can_be_discovered(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/models' => Http::response(['data' => [
                ['id' => 'anthropic/claude-sonnet-4', 'name' => 'Claude Sonnet 4', 'context_length' => 200000, 'pricing' => ['prompt' => '0.000003']],
            ]], 200),
        ]);
        $provider = new OpenRouterProvider(['api_key' => 'test-key', 'base_url' => 'https://openrouter.ai/api/v1']);
        $models = $provider->discoverModels();
        $this->assertSame('anthropic/claude-sonnet-4', $models[0]['id']);
        $this->assertSame(200000, $models[0]['context_length']);
    }
}
