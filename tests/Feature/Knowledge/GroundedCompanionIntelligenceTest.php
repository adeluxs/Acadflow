<?php

namespace Tests\Feature\Knowledge;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Services\Ai\GroundedCompanionService;
use App\Services\Discovery\SearchIndexService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use App\Services\Ai\AiRuntimeConfigService;
use App\Ai\AiManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroundedCompanionIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyboard_smash_is_rejected_before_grounded_answering(): void
    {
        [$publication, $user] = $this->publishedPublication();
        app(SearchIndexService::class)->index($publication);

        $session = app(GroundedCompanionService::class)->ask($publication, 'gsgshhshsh', $user);

        $this->assertSame('completed', $session->status);
        $this->assertSame('input_guard', $session->provider);
        $this->assertSame(0, $session->sources()->count());
        $this->assertContains('likely_gibberish', data_get($session->metadata, 'question_intelligence.reasons', []));
    }

    public function test_unrelated_question_is_rejected_by_publication_scope_gate(): void
    {
        [$publication, $user] = $this->publishedPublication();
        app(SearchIndexService::class)->index($publication);
        SettingService::set('ai_grounded_relevance_threshold', 0.50, 'decimal');
        SettingService::set('ai_grounded_lexical_floor', 0.20, 'decimal');

        $session = app(GroundedCompanionService::class)->ask($publication, 'What is the weather forecast in Lagos tomorrow?', $user);

        $this->assertSame('scope_guard', $session->provider);
        $this->assertSame('question_not_supported_by_publication', data_get($session->metadata, 'evidence_gate.reason'));
        $this->assertSame(0, $session->sources()->count());
    }

    public function test_relevant_question_is_answered_from_only_the_publication_index(): void
    {
        [$publication, $user] = $this->publishedPublication();
        app(SearchIndexService::class)->index($publication);

        $session = app(GroundedCompanionService::class)->ask(
            $publication,
            'What does the publication explain about chlorophyll and photosynthesis?',
            $user
        );

        $this->assertSame('completed', $session->status);
        $this->assertNotContains($session->provider, ['input_guard', 'scope_guard', 'retrieval_guard']);
        $this->assertGreaterThan(0, $session->sources()->count());
        $this->assertTrue((bool) data_get($session->metadata, 'question_intelligence.accepted'));
        $this->assertTrue((bool) data_get($session->metadata, 'evidence_gate.accepted'));
        $this->assertFalse((bool) data_get($session->metadata, 'open_web_used', true));
        $this->assertTrue($session->sources->every(fn ($source) => (int) $source->source_id === (int) $publication->id));
    }

    public function test_small_topic_typo_can_still_match_publication_evidence(): void
    {
        [$publication, $user] = $this->publishedPublication();
        app(SearchIndexService::class)->index($publication);

        $session = app(GroundedCompanionService::class)->ask(
            $publication,
            'What does the publication say about photosynthsis and chlorophyll?',
            $user
        );

        $this->assertSame('completed', $session->status);
        $this->assertNotContains($session->provider, ['input_guard', 'scope_guard', 'retrieval_guard']);
        $this->assertGreaterThan(0, $session->sources()->count());
        $this->assertTrue((bool) data_get($session->metadata, 'evidence_gate.accepted'));
    }

    public function test_grounded_companion_follows_runtime_default_provider_switching(): void
    {
        [$publication, $user] = $this->publishedPublication();
        app(SearchIndexService::class)->index($publication);

        config([
            'ai.providers.openai.api_key' => 'test-openai-key',
            'ai.providers.openai.model' => 'gpt-4o-mini',
            'ai.providers.gemini.api_key' => 'test-gemini-key',
            'ai.providers.gemini.model' => 'gemini-1.5-flash',
        ]);
        SettingService::set('ai_mode', 'provider');
        SettingService::set('ai_enable_cache', false, 'boolean');
        SettingService::set('ai_default_provider', 'openai');
        SettingService::set('ai_default_model', 'gpt-4o-mini');
        SettingService::set('ai_fallback_provider', 'none');
        SettingService::set('ai_provider_openai_enabled', true, 'boolean');
        SettingService::set('ai_provider_openai_model', 'gpt-4o-mini');
        SettingService::set('ai_provider_openai_models', ['gpt-4o-mini'], 'json');
        SettingService::set('ai_provider_gemini_enabled', true, 'boolean');
        SettingService::set('ai_provider_gemini_model', 'gemini-1.5-flash');
        SettingService::set('ai_provider_gemini_models', ['gemini-1.5-flash'], 'json');
        SettingService::set('ai_feature_knowledge_companion_provider', 'global');
        SettingService::set('ai_feature_knowledge_companion_model', 'global');
        app(AiRuntimeConfigService::class)->invalidate();
        app(AiManager::class)->invalidateAll();

        $answer = 'Chlorophyll absorbs light and supports the light-dependent reactions. [S1]';
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['data' => ['answerable' => true, 'answer' => $answer, 'confidence' => 0.95]])]]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
            ], 200),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode(['data' => ['answerable' => true, 'answer' => $answer, 'confidence' => 0.95]])]]]]],
                'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 10],
            ], 200),
        ]);

        $openAi = app(GroundedCompanionService::class)->ask($publication, 'What role does chlorophyll play in photosynthesis?', $user);
        $this->assertSame('openai', data_get($openAi->metadata, 'provider'));
        $this->assertSame('openai', $openAi->provider);

        SettingService::set('ai_default_provider', 'gemini');
        SettingService::set('ai_default_model', 'gemini-1.5-flash');
        app(AiRuntimeConfigService::class)->invalidate();
        app(AiManager::class)->invalidateAll();

        $gemini = app(GroundedCompanionService::class)->ask($publication, 'Explain chlorophyll and the light-dependent reactions.', $user);
        $this->assertSame('gemini', data_get($gemini->metadata, 'provider'));
        $this->assertSame('gemini', $gemini->provider);
    }

    public function test_premium_publication_cannot_be_answered_without_entitlement(): void
    {
        [$publication, $user] = $this->publishedPublication('premium');
        app(SearchIndexService::class)->index($publication);

        $this->expectException(AuthorizationException::class);
        app(GroundedCompanionService::class)->ask($publication, 'Summarize the main argument.', $user);
    }

    /** @return array{0:\App\Models\KnowledgePublication,1:User} */
    private function publishedPublication(string $accessType = 'free'): array
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $creator = User::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'role' => 'lecturer',
        ]);
        $user = User::factory()->create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'role' => 'student',
        ]);

        $publication = app(PublicationService::class)->createDraft([
            'title' => 'Photosynthesis and Chlorophyll in Plant Energy Conversion',
            'body' => '<p>Photosynthesis is the process by which green plants convert light energy into chemical energy. Chlorophyll absorbs light and supports the light-dependent reactions. The publication explains that carbon dioxide and water contribute to glucose production and that oxygen is released as a product. The discussion focuses on chlorophyll, light energy, glucose, plant cells, and photosynthetic reactions.</p>',
            'excerpt' => 'An academic overview of chlorophyll, light energy, and photosynthesis.',
            'content_type' => 'article',
            'visibility' => 'institution',
            'access_type' => $accessType,
            'price' => $accessType === 'premium' ? 1000 : null,
            'tags' => ['Photosynthesis', 'Chlorophyll', 'Plant Biology'],
        ], $creator);
        $publication->update(['status' => 'published', 'published_at' => now()->subMinute()]);

        return [$publication->fresh(['document', 'tags', 'category']), $user];
    }
}
