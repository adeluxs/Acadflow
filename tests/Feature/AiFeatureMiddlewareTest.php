<?php

namespace Tests\Feature;

use App\Enums\AiMode;
use App\Http\Middleware\EnsureAiFeatureEnabled;
use App\Models\User;
use App\Services\Ai\AiRuntimeConfigService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class AiFeatureMiddlewareTest extends TestCase
{
    public function test_disabled_ai_feature_is_stopped_before_controller_execution(): void
    {
        $runtime = Mockery::mock(AiRuntimeConfigService::class);
        $runtime->shouldReceive('featureEnabled')
            ->once()
            ->with('research_assistant', 22)
            ->andReturnFalse();

        $request = Request::create('/ai/context/research/example', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $user = new User;
        $user->university_id = 22;
        $request->setUserResolver(fn () => $user);

        $controllerWasCalled = false;
        $response = (new EnsureAiFeatureEnabled($runtime))->handle(
            $request,
            function () use (&$controllerWasCalled) {
                $controllerWasCalled = true;
                return response()->json(['success' => true]);
            },
            'research_assistant'
        );

        $this->assertFalse($controllerWasCalled);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('AI_FEATURE_DISABLED', $response->getData(true)['error_code']);
    }

    public function test_enabled_ai_feature_reaches_controller(): void
    {
        $runtime = Mockery::mock(AiRuntimeConfigService::class);
        $runtime->shouldReceive('featureEnabled')
            ->once()
            ->with('material_assistant', 22)
            ->andReturnTrue();
        $runtime->shouldReceive('mode')
            ->once()
            ->with(22)
            ->andReturn(AiMode::PROVIDER);

        $request = Request::create('/ai/context/materials/course/material', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $user = new User;
        $user->university_id = 22;
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureAiFeatureEnabled($runtime))->handle(
            $request,
            fn () => response()->json(['success' => true]),
            'material_assistant'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
    }
    public function test_globally_disabled_ai_mode_stops_contextual_assistant_before_controller(): void
    {
        $runtime = Mockery::mock(AiRuntimeConfigService::class);
        $runtime->shouldReceive('featureEnabled')->once()->with('discussion_assistant', 22)->andReturnTrue();
        $runtime->shouldReceive('mode')->once()->with(22)->andReturn(AiMode::DISABLED);

        $request = Request::create('/ai/context/discussions/course/discussion', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $user = new User;
        $user->university_id = 22;
        $request->setUserResolver(fn () => $user);

        $controllerWasCalled = false;
        $response = (new EnsureAiFeatureEnabled($runtime))->handle(
            $request,
            function () use (&$controllerWasCalled) {
                $controllerWasCalled = true;
                return response()->json(['success' => true]);
            },
            'discussion_assistant'
        );

        $this->assertFalse($controllerWasCalled);
        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame('AI_DISABLED', $response->getData(true)['error_code']);
    }

}
