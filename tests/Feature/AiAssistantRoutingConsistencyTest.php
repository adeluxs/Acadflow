<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ai\AcademicAssistantService;
use Tests\TestCase;

class AiAssistantRoutingConsistencyTest extends TestCase
{
    public function test_assistant_feature_resolver_is_role_and_tool_aware(): void
    {
        $service = app(AcademicAssistantService::class);

        $student = User::factory()->make(['role' => 'student', 'account_type' => 'student']);
        $lecturer = User::factory()->make(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $this->assertSame('study_assistant', $service->featureFor($student, 'ask'));
        $this->assertSame('lecturer_assistant', $service->featureFor($lecturer, 'ask'));

        $this->assertSame('writing_assistant', $service->featureFor($student, 'writing'));
        $this->assertSame('writing_assistant', $service->featureFor($lecturer, 'writing'));

        $this->assertSame('citation_assistant', $service->featureFor($student, 'citation'));
        $this->assertSame('citation_assistant', $service->featureFor($lecturer, 'citation'));
    }
}
