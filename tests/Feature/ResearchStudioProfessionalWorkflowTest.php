<?php

namespace Tests\Feature;

use App\Models\ContentDocument;
use App\Models\ResearchProject;
use App\Models\ResearchSection;
use App\Models\User;
use App\Services\IndependentAcademicContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchStudioProfessionalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_writing_workspace_focuses_one_selected_chapter_with_professional_guidance(): void
    {
        [$owner, $project] = $this->independentProject('Professional Writing Workflow');
        $chapterOne = $this->section($project, $owner, 'chapter_1', 'Chapter 1: Introduction', 1);
        $chapterThree = $this->section($project, $owner, 'chapter_3', 'Chapter 3: Methodology', 3);

        $this->actingAs($owner)
            ->get(route('research.show', ['research' => $project, 'section' => $chapterThree->uuid]))
            ->assertOk()
            ->assertSee('Chapter navigator')
            ->assertSee('Chapter 3 · Methodology')
            ->assertSee('Research design')
            ->assertSee('Sample and sampling technique')
            ->assertSee('Data analysis method')
            ->assertSee('Chapter 1')
            ->assertDontSee('Institutional supervision');
    }

    public function test_chapter_guidance_exposes_stable_ai_prompt_contract(): void
    {
        [$owner, $project] = $this->independentProject('Guidance Contract');
        $chapterThree = $this->section($project, $owner, 'chapter_3', 'Chapter 3: Methodology', 3);

        $guidance = app(\App\Services\ResearchWritingExperienceService::class)->guidance($chapterThree);

        $this->assertArrayHasKey('ai_prompts', $guidance);
        $this->assertIsArray($guidance['ai_prompts']);
        $this->assertNotEmpty($guidance['ai_prompts']);
        $this->assertArrayNotHasKey('aiPrompts', $guidance);
    }

    public function test_control_center_uses_research_journey_and_hides_institutional_tools_by_default(): void
    {
        [$owner, $project] = $this->independentProject('Professional Control Center');
        $this->section($project, $owner, 'chapter_1', 'Chapter 1: Introduction', 1);
        $this->section($project, $owner, 'chapter_2', 'Chapter 2: Literature Review', 2);

        $this->actingAs($owner)
            ->get(route('research.workspace', $project))
            ->assertOk()
            ->assertSee('Research journey')
            ->assertSee('Next action')
            ->assertSee('Continue writing')
            ->assertSee('Literature &amp; references', false)
            ->assertDontSee('Institutional workspace')
            ->assertDontSee('Collaboration & contribution evidence')
            ->assertDontSee('Research meetings & action items');
    }

    public function test_disabled_optional_research_module_cannot_be_called_directly(): void
    {
        [$owner, $project] = $this->independentProject('Disabled Dataset Guard');

        $this->actingAs($owner)
            ->post(route('research.datasets.store', $project), [])
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('research.specialized.show', $project))
            ->assertForbidden();
    }

    private function independentProject(string $title): array
    {
        $context = app(IndependentAcademicContextService::class)->context();
        $type = app(IndependentAcademicContextService::class)->ensureResearchType();
        $owner = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => null,
            'department_id' => null,
        ]);
        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => $title,
            'status' => 'draft',
            'progress' => 0,
        ]);

        return [$owner, $project];
    }

    private function section(ResearchProject $project, User $owner, string $key, string $title, int $position): ResearchSection
    {
        $document = ContentDocument::query()->create([
            'owner_id' => $owner->id,
            'document_type' => 'research_section',
            'title' => $title,
            'body' => '',
            'status' => 'draft',
            'visibility' => 'private',
            'version_number' => 1,
        ]);

        return ResearchSection::query()->create([
            'research_project_id' => $project->id,
            'content_document_id' => $document->id,
            'created_by' => $owner->id,
            'key' => $key,
            'title' => $title,
            'position' => $position,
            'is_required' => true,
            'status' => 'draft',
        ]);
    }
}
