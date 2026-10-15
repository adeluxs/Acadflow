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
            $mock->shouldReceive('ask')
                ->once()
                ->with(
                    Mockery::type(User::class),
                    'Explain neural networks',
                    null,
                    Mockery::type('string'),
                    Mockery::type('float'),
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
}
