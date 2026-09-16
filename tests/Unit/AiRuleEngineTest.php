<?php

namespace Tests\Unit;

use App\Ai\AiManager;
use App\Services\Ai\AiRuntimeConfigService;
use App\Services\SettingService;
use App\Ai\Rules\RuleEngine;
use App\Ai\Contracts\AiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_engine_detects_missing_project_chapters(): void
    {
        $engine = new RuleEngine();

        $response = $engine->run('submission_validator', [
            'type' => 'project',
            'text' => 'This is a short introduction about my project.',
        ]);

        $this->assertInstanceOf(AiResponse::class, $response);
        $this->assertSame('rule_engine', $response->source);
        $this->assertNotEmpty($response->issues);

        $codes = array_column($response->issues, 'code');
        $this->assertContains('missing_project_chapters', $codes);
    }

    public function test_rule_engine_awards_high_score_for_complete_assignment(): void
    {
        $engine = new RuleEngine();

        $text = implode("\n\n", [
            'Introduction',
            'This assignment introduces the topic with sufficient depth and analysis.',
            'Conclusion',
            'We conclude the discussion with final remarks and future work.',
            'References',
            'Author, A. (2020). Some Book. Publisher.',
        ]);

        $response = $engine->run('submission_validator', [
            'type' => 'assignment',
            'text' => $text,
        ]);

        $this->assertGreaterThan(0, $response->score);
        $this->assertIsFloat($response->score);
    }

    public function test_issues_are_prioritized(): void
    {
        $engine = new RuleEngine();

        $response = $engine->run('submission_validator', [
            'type' => 'project',
            'text' => 'minimal',
        ]);

        $priorities = array_column($response->issues, 'priority');
        $sorted = $priorities;
        sort($sorted);

        $this->assertSame($sorted, $priorities);
    }

    public function test_disabled_mode_returns_failure(): void
    {
        SettingService::set('ai_mode', 'disabled');
        app(AiRuntimeConfigService::class)->invalidate();

        $response = app(AiManager::class)->analyze('submission_validator', ['type' => 'assignment', 'text' => 'hello']);

        $this->assertFalse($response->success);
        $this->assertSame('disabled', $response->source);
    }

    public function test_hybrid_mode_returns_rule_response(): void
    {
        SettingService::set('ai_mode', 'hybrid');
        app(AiRuntimeConfigService::class)->invalidate();

        $response = app(AiManager::class)->analyze('submission_validator', [
            'type' => 'assignment',
            'text' => 'Introduction and conclusion with references provided here.',
        ]);

        $this->assertTrue($response->success);
        $this->assertSame('rule_engine_fallback', $response->source);
        $this->assertTrue($response->fallbackUsed);
    }

    public function test_cache_avoids_reprocessing(): void
    {
        SettingService::set('ai_mode', 'rule_based');
        SettingService::set('ai_enable_cache', true, 'boolean');
        app(AiRuntimeConfigService::class)->invalidate();
        $manager = app(AiManager::class);

        $payload = ['type' => 'assignment', 'text' => 'Introduction and conclusion with references.'];
        $first = $manager->analyze('submission_validator', $payload, scope: 'sub-1');

        // Second call should be served from cache (deterministic key)
        $second = $manager->analyze('submission_validator', $payload, scope: 'sub-1');

        $this->assertTrue($second->cached);
        $this->assertSame($first->summary, $second->summary);
    }
}
