<?php

namespace Tests\Feature;

use App\Ai\Providers\GeminiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiResponseLifecycleRegressionTest extends TestCase
{
    public function test_gemini_http_200_valid_text_is_returned_with_same_request_id(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"data":{"answer":"Backpropagation updates weights using gradients."},"summary":"Answer ready"}']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 12],
            ], 200, ['x-request-id' => 'gemini-provider-request']),
        ]);

        $provider = new GeminiProvider($this->config());
        $response = $provider->handle('study_assistant', [
            '_ai_request_id' => 'acadflow-request-12345678',
            '_ai_deadline_at' => microtime(true) + 10,
            '_prompt' => ['system_prompt' => 'Return JSON.', 'user_prompt' => 'Explain backpropagation.'],
        ]);

        $this->assertTrue($response->success);
        $this->assertSame('acadflow-request-12345678', $response->requestId);
        $this->assertSame('Backpropagation updates weights using gradients.', $response->data['answer'] ?? null);
        $this->assertSame('gemini', $response->provider);

        Http::assertSent(fn (Request $request): bool =>
            $request->hasHeader('X-AcadFlow-Request-Id', 'acadflow-request-12345678')
            && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent'
        );
    }

    public function test_gemini_http_200_empty_response_has_specific_error(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response(['candidates' => []], 200)]);

        $response = (new GeminiProvider($this->config()))->handle('study_assistant', [
            '_ai_request_id' => 'acadflow-empty-12345678',
            '_ai_deadline_at' => microtime(true) + 10,
            '_prompt' => ['system_prompt' => 'Return JSON.', 'user_prompt' => 'Question'],
        ]);

        $this->assertFalse($response->success);
        $this->assertSame('AI_EMPTY_RESPONSE', $response->errorCode);
        $this->assertSame('acadflow-empty-12345678', $response->requestId);
    }

    public function test_gemini_http_200_blocked_response_has_specific_error(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'promptFeedback' => ['blockReason' => 'SAFETY'],
            'candidates' => [],
        ], 200)]);

        $response = (new GeminiProvider($this->config()))->handle('study_assistant', [
            '_ai_request_id' => 'acadflow-blocked-12345678',
            '_ai_deadline_at' => microtime(true) + 10,
            '_prompt' => ['system_prompt' => 'Return JSON.', 'user_prompt' => 'Question'],
        ]);

        $this->assertFalse($response->success);
        $this->assertSame('AI_RESPONSE_BLOCKED', $response->errorCode);
    }

    public function test_gemini_http_200_malformed_structured_response_is_not_reported_as_success(): void
    {
        Http::fake(['https://generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'this is not valid json']]],
                'finishReason' => 'STOP',
            ]],
        ], 200)]);

        $response = (new GeminiProvider($this->config()))->handle('study_assistant', [
            '_ai_request_id' => 'acadflow-malformed-12345678',
            '_ai_deadline_at' => microtime(true) + 10,
            '_prompt' => ['system_prompt' => 'Return JSON.', 'user_prompt' => 'Question'],
        ]);

        $this->assertFalse($response->success);
        $this->assertSame('AI_RESPONSE_PARSE_FAILED', $response->errorCode);
    }

    public function test_runtime_provider_timeout_is_bounded_by_remaining_lifecycle_budget(): void
    {
        $provider = new class($this->config(['request_timeout' => 60, 'post_provider_reserve_seconds' => 4])) extends GeminiProvider {
            public function timeoutFor(array $payload): int
            {
                return $this->effectiveRequestTimeout($payload);
            }
        };

        $timeout = $provider->timeoutFor(['_ai_deadline_at' => microtime(true) + 9]);
        $this->assertGreaterThanOrEqual(1, $timeout);
        $this->assertLessThanOrEqual(5, $timeout);
    }

    /** @param array<string,mixed> $overrides */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'api_key' => 'gemini-test',
            'model' => 'gemini-3.6-flash',
            'request_timeout' => 18,
            'connect_timeout' => 5,
            'retry_count' => 0,
            'retry_delay_ms' => 0,
            'fast_failover' => true,
            'temperature' => 0.2,
            'max_tokens' => 512,
            'verify_tls' => true,
            'post_provider_reserve_seconds' => 4,
        ], $overrides);
    }
}
