<?php

namespace Tests\Feature\Research;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResearchProject;
use App\Models\ResearchType;
use App\Models\University;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\ResearchProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_creation_reuses_shared_workflow_and_content_workspace(): void
    {
        $university = University::factory()->create();
        $faculty = Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id]);
        $student = User::factory()->create(['university_id' => $university->id, 'department_id' => $department->id, 'role' => 'student']);
        $workflow = WorkflowDefinition::create(['university_id' => $university->id, 'key' => 'test-research', 'name' => 'Test Research', 'subject_type' => ResearchProject::class, 'is_active' => true]);
        $workflow->stages()->create(['key' => 'creation', 'name' => 'Creation', 'position' => 0, 'actor_roles' => ['student'], 'is_initial' => true]);
        $workflow->stages()->create(['key' => 'approved', 'name' => 'Approved', 'position' => 1, 'actor_roles' => ['lecturer'], 'is_final' => true]);
        $type = ResearchType::create([
            'university_id' => $university->id,
            'workflow_definition_id' => $workflow->id,
            'name' => 'Research Paper',
            'slug' => 'research-paper',
            'template_schema' => ['sections' => [['key' => 'abstract', 'title' => 'Abstract', 'required' => true], ['key' => 'references', 'title' => 'References', 'required' => true]]],
            'publication_eligible' => true,
            'is_active' => true,
        ]);

        $project = app(ResearchProjectService::class)->create([
            'research_type_id' => $type->id,
            'department_id' => $department->id,
            'title' => 'Shared foundation verification',
        ], $student);

        $this->assertSame('creation', $project->status);
        $this->assertCount(2, $project->sections);
        $this->assertNotNull($project->workflowInstance);
        $this->assertDatabaseCount('content_documents', 2);
        $this->assertDatabaseCount('content_versions', 2);
        $this->assertDatabaseHas('research_project_members', ['research_project_id' => $project->id, 'user_id' => $student->id, 'role' => 'lead_author']);
    }
}
