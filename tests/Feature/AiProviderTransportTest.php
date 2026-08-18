<?php

namespace Tests\Feature;

use App\Ai\Providers\AzureOpenAiProvider;
use App\Ai\Providers\ClaudeProvider;
use App\Ai\Providers\DeepSeekProvider;
use App\Ai\Providers\GeminiProvider;
use App\Ai\Providers\OllamaProvider;
use App\Ai\Providers\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTransportTest extends TestCase
{
    public function test_openai_health_uses_chat_completions_and_bearer_auth(): void
    {
        Http::fake(['https://api.openai.com/*' => Http::response(['choices' => []], 200, ['x-request-id' => 'req-openai'])]);

        $provider = new OpenAiProvider($this->config(['base_url' => 'https://api.openai.com/v1', 'api_key' => 'openai-test', 'model' => 'gpt-4o-mini']));
        $result = $provider->healthCheck();

        $this->assertSame('healthy', $result['status']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer openai-test')
                && $request->hasHeader('Content-Type', 'application/json')
                && ($data['model'] ?? null) === 'gpt-4o-mini'
                && is_array($data['messages'] ?? null)
                && count($data['messages']) >= 2;
        });
    }

    public function test_claude_health_uses_messages_headers_and_omits_temperature_for_sonnet_5(): void
    {
        Http::fake(['https://api.anthropic.com/*' => Http::response(['content' => []], 200)]);

        $provider = new ClaudeProvider($this->config(['base_url' => 'https://api.anthropic.com/v1', 'api_key' => 'claude-test', 'model' => 'claude-sonnet-5', 'api_version' => '2023-06-01']));
        $this->assertSame('healthy', $provider->healthCheck()['status']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'claude-test')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && ! array_key_exists('temperature', $data);
        });
    }

    public function test_gemini_health_uses_header_auth_and_never_places_key_in_url(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200)]);

        $provider = new GeminiProvider($this->config(['base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'api_key' => 'gemini-test', 'model' => 'gemini-3.6-flash']));
        $this->assertSame('healthy', $provider->healthCheck()['status']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-test')
            && ! str_contains($request->url(), 'gemini-test'));
    }

    public function test_deepseek_health_uses_chat_completions_and_bearer_auth(): void
    {
        Http::fake(['https://api.deepseek.com/*' => Http::response(['choices' => []], 200)]);

        $provider = new DeepSeekProvider($this->config(['base_url' => 'https://api.deepseek.com', 'api_key' => 'deepseek-test', 'model' => 'deepseek-v4-flash']));
        $this->assertSame('healthy', $provider->healthCheck()['status']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.deepseek.com/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer deepseek-test'));
    }

    public function test_azure_v1_and_ollama_full_endpoints_are_not_duplicated(): void
    {
        Http::fake([
            'https://example.openai.azure.com/*' => Http::response(['choices' => []], 200),
            'https://ollama.example/*' => Http::response(['message' => ['content' => '{"status":"ok"}']], 200),
        ]);

        $azure = new AzureOpenAiProvider($this->config([
            'endpoint' => 'https://example.openai.azure.com/openai/v1',
            'api_key' => 'azure-test',
            'model' => 'deployment-name',
        ]));
        $ollama = new OllamaProvider($this->config([
            'endpoint' => 'https://ollama.example/api/chat',
            'api_key' => 'ollama-test',
            'model' => 'gpt-oss:120b',
        ]));

        $this->assertSame('healthy', $azure->healthCheck()['status']);
        $this->assertSame('healthy', $ollama->healthCheck()['status']);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://example.openai.azure.com/openai/v1/chat/completions'
            && $request->hasHeader('api-key', 'azure-test'));
        Http::assertSent(fn (Request $request) => $request->url() === 'https://ollama.example/api/chat'
            && $request->hasHeader('Authorization', 'Bearer ollama-test'));
    }

    public function test_full_chat_endpoint_is_not_appended_twice(): void
    {
        Http::fake([
            'https://proxy.example/*' => Http::response(['choices' => []], 200),
            'https://claude-proxy.example/*' => Http::response(['content' => []], 200),
            'https://deepseek-proxy.example/*' => Http::response(['choices' => []], 200),
        ]);

        (new OpenAiProvider($this->config(['base_url' => 'https://proxy.example/v1/chat/completions', 'api_key' => 'a', 'model' => 'm'])))->healthCheck();
        (new ClaudeProvider($this->config(['base_url' => 'https://claude-proxy.example/v1/messages', 'api_key' => 'b', 'model' => 'claude-haiku-4-5-20251001'])))->healthCheck();
        (new DeepSeekProvider($this->config(['base_url' => 'https://deepseek-proxy.example/chat/completions', 'api_key' => 'c', 'model' => 'deepseek-v4-flash'])))->healthCheck();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://proxy.example/v1/chat/completions');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://claude-proxy.example/v1/messages');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://deepseek-proxy.example/chat/completions');
    }

    /** @param array<string,mixed> $overrides */
    private function config(array $overrides): array
    {
        return array_merge([
            'enabled' => true,
            'request_timeout' => 20,
            'connect_timeout' => 5,
            'retry_count' => 0,
            'retry_delay_ms' => 0,
            'temperature' => 0.2,
            'max_tokens' => 512,
            'verify_tls' => true,
            'ca_bundle' => '',
            'proxy' => '',
            'force_ipv4' => false,
        ], $overrides);
    }
}
