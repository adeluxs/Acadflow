<?php

namespace Tests\Feature;

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

class AiLifecycleResilienceTest extends TestCase
{
    public function test_valid_provider_answer_survives_noncritical_cache_billing_and_analytics_failures(): void
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

        $router->shouldReceive('mode')->andReturn(AiMode::PROVIDER);
        $router->shouldReceive('providerChain')->once()->andReturn([
            ['provider' => 'gemini', 'model' => 'gemini-3.6-flash', 'role' => 'primary'],
        ]);
        $router->shouldReceive('route')->andReturn(['feature' => 'study_assistant', 'mode' => 'provider']);
        $router->shouldReceive('providerSupportsFeature')->with('gemini', 'study_assistant')->andReturnTrue();
        $router->shouldReceive('provider')->with('gemini', null, 'gemini-3.6-flash')->andReturn($provider);

        $runtime->shouldReceive('featureEnabled')->andReturnTrue();
        $runtime->shouldReceive('interactiveRequestBudget')->andReturn(24);
        $runtime->shouldReceive('routingFingerprint')->andReturn('test-routing');
        $runtime->shouldReceive('dailyRequestLimit')->andReturn(0);
        $runtime->shouldReceive('monthlyRequestLimit')->andReturn(0);
        $runtime->shouldReceive('maxMonthlyCostMicroUsd')->andReturn(0);
        $runtime->shouldReceive('responseReserveSeconds')->andReturn(4);
        $runtime->shouldReceive('providerEnabled')->with('gemini', null)->andReturnTrue();
        $runtime->shouldReceive('providerConfigurationComplete')->with('gemini', null)->andReturnTrue();

        $prompts->shouldReceive('enrich')->once()->andReturnUsing(function (string $feature, array $payload): array {
            $payload['_prompt'] = [
                'system_prompt' => 'Return JSON.',
                'user_prompt' => 'Test',
                'response_schema' => [],
            ];
            return $payload;
        });

        $cache->shouldReceive('get')->once()->andReturnNull();
        $cache->shouldReceive('put')->once()->andThrow(new \RuntimeException('cache unavailable'));

        $billing->shouldReceive('reserve')->once()->andReturn([
            'source' => 'platform', 'charge_minor' => 0, 'request_id' => 'logical-200',
        ]);
        $billing->shouldReceive('settle')->once()->andThrow(new \RuntimeException('settlement write unavailable'));

        $provider->shouldReceive('isAvailable')->andReturnTrue();
        $provider->shouldReceive('handle')->once()->andReturn(new AiResponse(
            source: 'gemini',
            feature: 'study_assistant',
            success: true,
            data: ['answer' => 'This valid provider answer must reach the user.'],
            summary: 'Answer ready',
            provider: 'gemini',
            model: 'gemini-3.6-flash',
            requestId: 'logical-200',
        ));

        $validator->shouldReceive('errors')->once()->andReturn([]);
        $analytics->shouldReceive('record')->once()->andThrow(new \RuntimeException('analytics unavailable'));

        $manager = new AiManager($router, $engine, $cache, $analytics, $prompts, $validator, $runtime, $billing);
        $response = $manager->analyze('study_assistant', ['question' => 'Explain this'], requestId: 'logical-200');

        $this->assertTrue($response->success);
        $this->assertSame('logical-200', $response->requestId);
        $this->assertSame('Answer ready', $response->summary);
        $this->assertSame('This valid provider answer must reach the user.', $response->data['answer'] ?? null);
        $this->assertTrue((bool) data_get($response->metadata, 'billing_settlement_pending'));
    }
}
