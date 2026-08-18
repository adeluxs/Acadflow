<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;

class AiCapabilityMapTest extends TestCase
{
    public function test_acadflow_exposes_the_expected_central_ai_capability_map(): void
    {
        $expected = [
            'submission_validator',
            'plagiarism',
            'writing_assistant',
            'citation_assistant',
            'study_assistant',
            'lecturer_assistant',
            'research_assistant',
            'research_validator',
            'assignment_assistant',
            'siwes_assistant',
            'project_assistant',
            'material_assistant',
            'discussion_assistant',
            'knowledge_publication_validator',
            'knowledge_moderation',
            'knowledge_companion',
        ];

        $this->assertSame($expected, array_values((array) config('ai.features', [])));
        $this->assertNotContains('literature_review', config('ai.features', []));
        $this->assertNotContains('moderation_assistant', config('ai.features', []));
        $this->assertNotContains('recommendation_assistant', config('ai.features', []));
    }

    public function test_new_contextual_assistants_each_have_ui_metadata_and_capabilities(): void
    {
        foreach ([
            'research_assistant',
            'assignment_assistant',
            'siwes_assistant',
            'project_assistant',
            'material_assistant',
            'discussion_assistant',
        ] as $feature) {
            $this->assertNotEmpty(config('ai.assistant_profiles.'.$feature.'.label'));
            $this->assertNotEmpty(config('ai.assistant_profiles.'.$feature.'.module'));
            $this->assertNotEmpty(config('ai.assistant_profiles.'.$feature.'.description'));
            $this->assertNotEmpty(config('ai.assistant_profiles.'.$feature.'.suggestions'));
            $this->assertSame(['chat', 'structured_output'], config('ai.feature_capabilities.'.$feature));
        }
    }
}
