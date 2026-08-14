<?php

namespace Tests\Feature\Knowledge;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResearchProject;
use App\Models\ResearchType;
use App\Models\University;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\ResearchPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchPublicationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_research_creates_a_permanently_linked_hub_draft(): void
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $author = User::factory()->create(['university_id' => $university->id, 'department_id' => $department->id]);
        $type = ResearchType::create(['university_id' => $university->id, 'name' => 'Thesis', 'slug' => 'thesis', 'publication_eligible' => true, 'is_active' => true]);
        $project = ResearchProject::create([
            'university_id' => $university->id,
            'department_id' => $department->id,
            'research_type_id' => $type->id,
            'owner_id' => $author->id,
            'title' => 'Approved research',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $document = app(ContentWorkspaceService::class)->create(['document_type' => 'research_section', 'title' => 'Abstract', 'body' => '<p>Verified content</p>'], $author);
        $project->sections()->create(['content_document_id' => $document->id, 'key' => 'abstract', 'title' => 'Abstract', 'position' => 0, 'is_required' => true, 'status' => 'approved']);

        $publication = app(ResearchPublicationService::class)->createPublication($project, $author, ['visibility' => 'institution']);

        $this->assertSame($project->id, $publication->source_research_project_id);
        $this->assertSame('draft', $publication->status);
        $this->assertDatabaseHas('knowledge_publications', ['id' => $publication->id, 'source_research_project_id' => $project->id]);
        $this->assertStringContainsString('Verified content', $publication->document->body);
    }
}
