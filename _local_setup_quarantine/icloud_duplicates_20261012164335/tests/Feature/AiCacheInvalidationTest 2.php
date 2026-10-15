<?php

namespace Tests\Feature;

use App\Ai\AiCache;
use App\Ai\Contracts\AiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_and_scope_generations_invalidate_cached_results(): void
    {
        $cache = app(AiCache::class);
        $payload = ['text' => 'unchanged document'];
        $response = new AiResponse(source: 'rule_engine', feature: 'research_validator', success: true, summary: 'Ready');

        $cache->put('research_validator', $payload, $response, 'research:abc');
        $this->assertInstanceOf(AiResponse::class, $cache->get('research_validator', $payload, 'research:abc'));

        $cache->forgetScope('research:abc');
        $this->assertNull($cache->get('research_validator', $payload, 'research:abc'));

        $cache->put('research_validator', $payload, $response);
        $cache->forgetFeature('research_validator');
        $this->assertNull($cache->get('research_validator', $payload));
    }
}
