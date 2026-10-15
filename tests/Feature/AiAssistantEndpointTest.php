<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AcademicAssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AiAssistantEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_ai_service_result_reaches_frontend_api_response(): void
    {
        $user = User::factory()->create(['role' => 'student', 'account_type' => 'student']);

        $this->mock(AcademicAssistantService::class, function ($mock): void {
            $mock->shouldReceive('allowedAskFeatures')
                ->andReturn(['study_assistant']);
            $mock->shouldReceive('ask')
                ->once()
                ->with(
                    Mockery::type(User::class),
                    'Explain neural networks',
                    null,
                    Mockery::type('string'),
                    Mockery::type('float'),
                    null,
                    null,
                )
                ->andReturn([
                    'success' => true,
                    'answer' => 'A neural network learns patterns from examples.',
                    'provider' => 'gemini',
                    'model' => 'gemini-3.6-flash',
                    'fallback_used' => false,
                    'cached' => false,
                    'sources' => [],
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('ai.assistant.ask'), [
                'tool' => 'ask',
                'message' => 'Explain neural networks',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'answer' => 'A neural network learns patterns from examples.',
                'provider' => 'gemini',
                'model' => 'gemini-3.6-flash',
            ]);
    }

    public function test_ai_service_exception_returns_normalized_json_failure(): void
    {
        $user = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $this->mock(AcademicAssistantService::class, function ($mock): void {
            $mock->shouldReceive('allowedAskFeatures')
                ->andReturn(['lecturer_assistant', 'research_assistant', 'study_assistant']);
            $mock->shouldReceive('ask')
                ->once()
                ->andThrow(new \RuntimeException('vendor/guzzlehttp transport failure stack trace secret'));
        });

        $this->actingAs($user)
            ->postJson(route('ai.assistant.ask'), [
                'tool' => 'ask',
                'message' => 'Create a rubric for my course assessment',
            ])
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error_code' => 'AI_REQUEST_FAILED',
                'retryable' => true,
            ])
            ->assertJsonMissing(['message' => 'vendor/guzzlehttp transport failure stack trace secret']);
    }

    public function test_role_aware_assistant_feature_is_forwarded_to_service(): void
    {
        $user = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $this->mock(AcademicAssistantService::class, function ($mock): void {
            $mock->shouldReceive('allowedAskFeatures')
                ->andReturn(['lecturer_assistant', 'research_assistant', 'study_assistant']);
            $mock->shouldReceive('ask')
                ->once()
                ->with(
                    Mockery::type(User::class),
                    'Use a scholarly teaching lens for this supervision plan',
                    null,
                    Mockery::type('string'),
                    Mockery::type('float'),
                    'research_assistant',
                    null,
                )
                ->andReturn([
                    'success' => true,
                    'answer' => 'Research-informed teaching plan ready.',
                    'provider' => 'openrouter',
                    'model' => 'google/gemini-2.5-flash',
                    'fallback_used' => false,
                    'cached' => false,
                    'sources' => [],
                    'feature' => 'research_assistant',
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('ai.assistant.ask'), [
                'tool' => 'ask',
                'assistant_feature' => 'research_assistant',
                'message' => 'Use a scholarly teaching lens for this supervision plan',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'feature' => 'research_assistant',
            ]);
    }
}
