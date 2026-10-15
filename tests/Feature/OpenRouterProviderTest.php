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

    public function test_openrouter_reads_nonstandard_text_content_when_message_content_is_empty(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => '', 'reasoning' => json_encode(['data' => ['answer' => 'reasoning answer']])],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $provider = new OpenRouterProvider([
            'enabled' => true,
            'api_key' => 'test-key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'google/gemini-3.5-flash',
            'retry_count' => 0,
        ]);

        $response = $provider->handle('lecturer_assistant', ['question' => 'Draft feedback.']);

        $this->assertTrue($response->success);
        $this->assertSame('reasoning answer', $response->data['answer'] ?? null);
    }

    public function test_openrouter_extracts_content_array_text_parts(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => [
                        ['type' => 'text', 'text' => json_encode(['data' => ['answer' => 'array answer']])],
                    ]],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $response = $this->provider()->handle('lecturer_assistant', ['question' => 'Draft feedback.']);

        $this->assertTrue($response->success);
        $this->assertSame('array answer', $response->data['answer'] ?? null);
    }

    public function test_openrouter_http_200_error_object_is_not_success(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => ['message' => 'No endpoints found for model google/gemini-3.5-flash.', 'code' => 'model_not_found'],
            ], 200),
        ]);

        $response = $this->provider(['model' => 'google/gemini-3.5-flash'])->handle('lecturer_assistant', ['question' => 'Draft feedback.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_MODEL_NOT_FOUND', $response->errorCode);
        $this->assertSame('No endpoints found for model google/gemini-3.5-flash.', $response->summary);
    }

    public function test_openrouter_empty_choices_return_actionable_empty_response_failure(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => []], 200),
        ]);

        $response = $this->provider()->handle('lecturer_assistant', ['question' => 'Draft feedback.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_EMPTY_RESPONSE', $response->errorCode);
        $this->assertStringContainsString('verify the configured OpenRouter model', (string) $response->summary);
    }

    public function test_openrouter_normalizes_assistant_json_without_answer_field(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'topic' => 'Normalization',
                        'explanation' => 'Normalization reduces duplication in relational tables.',
                        'example' => 'Move repeated department names into a departments table.',
                        'self_check_questions' => ['What is 1NF?', 'Why avoid update anomalies?'],
                    ])],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $response = $this->provider()->handle('study_assistant', ['question' => 'Explain normalization.']);

        $this->assertTrue($response->success);
        $this->assertStringContainsString('Normalization reduces duplication', (string) ($response->data['answer'] ?? ''));
        $this->assertStringContainsString('Self check questions', (string) ($response->data['answer'] ?? ''));
    }

    public function test_openrouter_rejects_plain_text_for_structured_assistant_features(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'plain text instead of json'], 'finish_reason' => 'stop']],
            ], 200),
        ]);

        $response = $this->provider()->handle('study_assistant', ['question' => 'Explain normalization.']);

        $this->assertFalse($response->success);
        $this->assertSame('AI_RESPONSE_PARSE_FAILED', $response->errorCode);
    }

    public function test_openrouter_humanizes_structured_json_draft_without_answer_field(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'status' => 'draft',
                        'draft_only' => true,
                        'notice' => 'No course materials were found.',
                        'lesson_plan' => [
                            'topic' => 'Introduction to Computers',
                            'learning_objectives' => ['Define a computer', 'Distinguish hardware and software'],
                        ],
                    ])],
                    'finish_reason' => 'stop',
                ]],
            ], 200),
        ]);

        $response = $this->provider()->handle('lecturer_assistant', ['question' => 'Draft a lesson.']);

        $this->assertTrue($response->success);
        $answer = (string) ($response->data['answer'] ?? '');
        $this->assertStringContainsString('Introduction to Computers', $answer);
        $this->assertStringContainsString('Define a computer', $answer);
        $this->assertStringNotContainsString('{', $answer);
        $this->assertStringNotContainsString('"status"', $answer);
    }

    private function provider(array $overrides = []): OpenRouterProvider
    {
        return new OpenRouterProvider(array_merge([
            'enabled' => true,
            'api_key' => 'test-key',
            'base_url' => 'https://openrouter.ai/api/v1',
            'model' => 'google/gemini-2.5-flash',
            'retry_count' => 0,
        ], $overrides));
    }
}
