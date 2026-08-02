<?php

namespace Tests\Unit;

use App\Ai\AiAnalytics;
use App\Ai\AiCache;
use App\Ai\AiManager;
use App\Ai\AiRouter;
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
        config(['ai.default_mode' => 'disabled']);

        $router = new AiRouter(new RuleEngine());
        $manager = new AiManager($router, new RuleEngine(), new AiCache(), new AiAnalytics());

        $response = $manager->analyze('submission_validator', ['type' => 'assignment', 'text' => 'hello']);

        $this->assertFalse($response->success);
        $this->assertSame('disabled', $response->source);
    }

    public function test_hybrid_mode_returns_rule_response(): void
    {
        config(['ai.default_mode' => 'hybrid']);

        $router = new AiRouter(new RuleEngine());
        $manager = new AiManager($router, new RuleEngine(), new AiCache(), new AiAnalytics());

        $response = $manager->analyze('submission_validator', [
            'type' => 'assignment',
            'text' => 'Introduction and conclusion with references provided here.',
        ]);

        $this->assertTrue($response->success);
        $this->assertSame('rule_engine', $response->source);
    }

    public function test_cache_avoids_reprocessing(): void
    {
        config(['ai.default_mode' => 'rule_based']);

        $cache = new AiCache();
        $router = new AiRouter(new RuleEngine());
        $manager = new AiManager($router, new RuleEngine(), $cache, new AiAnalytics());

        $payload = ['type' => 'assignment', 'text' => 'Introduction and conclusion with references.'];
        $first = $manager->analyze('submission_validator', $payload, scope: 'sub-1');

        // Second call should be served from cache (deterministic key)
        $second = $manager->analyze('submission_validator', $payload, scope: 'sub-1');

        $this->assertTrue($second->cached);
        $this->assertSame($first->summary, $second->summary);
    }
}
