<?php

namespace Tests\Unit;

use App\Ai\AiAnalytics;
use App\Ai\AiCache;
use App\Ai\AiManager;
use App\Ai\AiRouter;
use App\Ai\Contracts\AiProviderInterface;
use App\Ai\Contracts\AiResponse;
use App\Ai\Rules\RuleEngine;
use App\Enums\AiMode;
use App\Services\Ai\AiPromptService;
use App\Services\Ai\AiResponseSchemaValidator;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\Commerce\AiUsageBillingService;
use Mockery;
use Tests\TestCase;

class AiPostProviderResilienceTest extends TestCase
{
    public function test_valid_provider_answer_survives_non_critical_billing_and_analytics_failures(): void
    {
        $router = Mockery::mock(AiRouter::class);
        $engine = Mockery::mock(RuleEngine::class);
        $cache = Mockery::mock(AiCache::class);
        $analytics = Mockery::mock(AiAnalytics::class);
        $prompts = Mockery::mock(AiPromptService::class);
        $validator = Mockery::mock(AiResponseSchemaValidator::class);
        $runtime = Mockery::mock(AiRuntimeConfigService::class);
        $billing = Mockery::mock(AiUsageBillingService::class);
        $provider = Mockery::mock(AiProviderInterface::class);

        $runtime->shouldReceive('featureEnabled')->andReturnTrue();
        $runtime->shouldReceive('routingFingerprint')->andReturn('routing-test');
        $runtime->shouldReceive('dailyRequestLimit')->andReturn(0);
        $runtime->shouldReceive('monthlyRequestLimit')->andReturn(0);
        $runtime->shouldReceive('maxMonthlyCostMicroUsd')->andReturn(0);
        $runtime->shouldReceive('providerEnabled')->andReturnTrue();
        $runtime->shouldReceive('providerConfigurationComplete')->andReturnTrue();

        $router->shouldReceive('mode')->andReturn(AiMode::PROVIDER);
        $router->shouldReceive('route')->andReturn([
            'mode' => 'provider', 'resolved_provider' => 'gemini', 'resolved_model' => 'gemini-3.6-flash',
        ]);
        $router->shouldReceive('providerChain')->andReturn([[
            'provider' => 'gemini', 'model' => 'gemini-3.6-flash', 'role' => 'primary',
        ]]);
        $router->shouldReceive('providerSupportsFeature')->andReturnTrue();
        $router->shouldReceive('provider')->andReturn($provider);

        $provider->shouldReceive('isAvailable')->andReturnTrue();
        $provider->shouldReceive('handle')->andReturn(new AiResponse(
            source: 'gemini', feature: 'study_assistant', success: true,
            data: ['answer' => 'A valid provider answer.'], summary: 'A valid provider answer.',
            requestId: 'provider-local-id', provider: 'gemini', model: 'gemini-3.6-flash',
        ));

        $prompts->shouldReceive('enrich')->andReturnUsing(fn (string $feature, array $payload) => $payload);
        $cache->shouldReceive('get')->andReturnNull();
        $cache->shouldReceive('put')->once();
        $validator->shouldReceive('errors')->andReturn([]);

        $billing->shouldReceive('reserve')->andReturn(['source' => 'platform', 'charge_minor' => 0, 'request_id' => 'manager-request-12345678']);
        $billing->shouldReceive('settle')->once()->andThrow(new \RuntimeException('simulated post-provider settlement failure'));
        $billing->shouldReceive('release')->once();
        $analytics->shouldReceive('record')->once()->andThrow(new \RuntimeException('simulated analytics failure'));

        $manager = new AiManager($router, $engine, $cache, $analytics, $prompts, $validator, $runtime, $billing);
        $response = $manager->analyze('study_assistant', [
            'question' => 'Explain gradients',
            '_ai_request_id' => 'manager-request-12345678',
        ]);

        $this->assertTrue($response->success);
        $this->assertSame('manager-request-12345678', $response->requestId);
        $this->assertSame('A valid provider answer.', $response->data['answer'] ?? null);
        $this->assertSame('reconciliation_required', $response->metadata['billing_status'] ?? null);
    }
}
