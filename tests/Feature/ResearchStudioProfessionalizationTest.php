<?php

namespace Tests\Feature;

use App\Models\AcademicReference;
use App\Models\ContentDocument;
use App\Models\ResearchLiteratureNote;
use App\Models\ResearchSection;
use App\Models\Department;
use App\Models\ResearchProject;
use App\Models\ResearchProjectInvitation;
use App\Models\ScholarlyRecord;
use App\Models\University;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\IndependentAcademicContextService;
use App\Services\AcademicReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ResearchStudioProfessionalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_institutional_controls_are_hidden_until_admin_enables_research_expansion(): void
    {
        $university = University::factory()->create(['adoption_status'=>'active']);
        $department = Department::factory()->create(['faculty_id' => \App\Models\Faculty::factory()->create(['university_id' => $university->id])->id,'adoption_status'=>'institution_managed']);
        $student = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('research.create'))
            ->assertOk()
            ->assertDontSee('Institutional project')
            ->assertDontSee('Invite supervisor')
            ->assertDontSee('Connect this project to your institution');

        FeatureAccessService::setStatus('research_institutional_expansion', FeatureAccessService::STATUS_ENABLED, $student->id);

        $this->approveProfile($student);
        $this->actingAs($student)
            ->get(route('research.create'))
            ->assertOk()
            ->assertSee('Institutional project')
            ->assertSee('Department')
            ->assertSee('Invite supervisor');
    }

    public function test_supervisor_is_invited_and_not_assigned_until_acceptance(): void
    {
        Mail::fake();
        $university = University::factory()->create(['adoption_status'=>'active']);
        $faculty = \App\Models\Faculty::factory()->create(['university_id' => $university->id]);
        $department = Department::factory()->create(['faculty_id' => $faculty->id,'adoption_status'=>'institution_managed']);
        $owner = User::factory()->create([
            'role' => 'student',
            'account_type' => 'student',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $lecturer = User::factory()->create([
            'role' => 'lecturer',
            'account_type' => 'lecturer',
            'university_id' => $university->id,
            'department_id' => $department->id,
        ]);
        $type = app(IndependentAcademicContextService::class)->ensureResearchType();
        FeatureAccessService::setStatus('research_institutional_expansion', FeatureAccessService::STATUS_ENABLED, $owner->id);

        $this->approveProfile($owner);$this->approveProfile($lecturer);
        $response = $this->actingAs($owner)->post(route('research.store'), [
            'research_type_id' => $type->id,
            'institutional_project' => '1',
            'department_id' => $department->id,
            'title' => 'Professional Supervision Workflow',
            'supervisor_invitee_id' => $lecturer->id,
        ]);

        $project = ResearchProject::query()->where('title', 'Professional Supervision Workflow')->firstOrFail();
        $response->assertRedirect(route('research.show', $project));
        $this->assertNull($project->supervisor_id);

        $invitation = ResearchProjectInvitation::query()->where('research_project_id', $project->id)->firstOrFail();
        $this->assertSame('pending', $invitation->status);
        $this->assertSame($lecturer->id, $invitation->invitee_id);

        $this->actingAs($lecturer)->post(route('research.invitations.respond', $invitation), ['decision' => 'accept'])
            ->assertRedirect(route('research.index'));

        $this->assertSame($lecturer->id, $project->fresh()->supervisor_id);
        $this->assertSame('accepted', $invitation->fresh()->status);
    }

    public function test_disabled_institutional_expansion_rejects_institution_payloads(): void
    {
        $university = University::factory()->create(['adoption_status'=>'active']);
        $department = Department::factory()->create(['faculty_id' => \App\Models\Faculty::factory()->create(['university_id' => $university->id])->id,'adoption_status'=>'institution_managed']);
        $owner = User::factory()->create(['university_id' => $university->id, 'department_id' => $department->id]);
        $type = app(IndependentAcademicContextService::class)->ensureResearchType();
        FeatureAccessService::setStatus('research_institutional_expansion', FeatureAccessService::STATUS_DISABLED, $owner->id);

        $this->actingAs($owner)->post(route('research.store'), [
            'research_type_id' => $type->id,
            'workspace_mode' => 'institutional',
            'department_id' => $department->id,
            'title' => 'Blocked Institution Payload',
        ])->assertForbidden();

        $this->assertDatabaseMissing('research_projects', ['title' => 'Blocked Institution Payload']);
    }

    public function test_doi_less_papers_are_saved_as_distinct_project_references_and_notes_reopen(): void
    {
        $independent = app(IndependentAcademicContextService::class);
        $context = $independent->context();
        $type = $independent->ensureResearchType();
        $owner = User::factory()->create(['university_id' => null, 'department_id' => null]);
        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => 'Reference Library Test',
            'status' => 'draft',
            'progress' => 0,
        ]);

        $first = ScholarlyRecord::query()->create([
            'provider' => 'openalex',
            'external_identifier' => 'OA-ONE',
            'record_type' => 'work',
            'title' => 'Paper Without DOI One',
            'authors' => [['name' => 'Researcher One']],
            'publication_year' => 2025,
            'doi' => null,
            'url' => 'https://example.test/paper-one',
            'fetched_at' => now(),
        ]);
        $second = ScholarlyRecord::query()->create([
            'provider' => 'arxiv',
            'external_identifier' => 'ARXIV-TWO',
            'record_type' => 'work',
            'title' => 'Paper Without DOI Two',
            'authors' => [['name' => 'Researcher Two']],
            'publication_year' => 2026,
            'doi' => null,
            'url' => 'https://example.test/paper-two',
            'fetched_at' => now(),
        ]);

        $this->actingAs($owner)->post(route('research.literature.save', [$project, $first]))->assertRedirect();
        $this->actingAs($owner)->post(route('research.literature.save', [$project, $second]))->assertRedirect();

        $this->assertSame(2, $project->referenceLinks()->count());
        $this->assertSame(2, AcademicReference::query()->where('owner_id', $owner->id)->count());

        $reference = AcademicReference::query()->where('owner_id', $owner->id)->where('title', 'Paper Without DOI One')->firstOrFail();
        $this->actingAs($owner)->put(route('research.literature.note', [$project, $reference]), [
            'summary' => 'A saved structured review summary.',
            'methodology' => 'Mixed methods.',
            'findings' => 'A relevant finding.',
            'research_gap' => 'A clear gap remains.',
            'keywords' => 'analytics, education',
        ])->assertRedirect();

        $this->actingAs($owner)
            ->get(route('research.literature.search', ['research' => $project, 'view' => 'saved']))
            ->assertOk()
            ->assertSee('A saved structured review summary.')
            ->assertSee('Mixed methods.')
            ->assertSee('A clear gap remains.');
    }

    public function test_control_center_defaults_to_core_research_operations_only(): void
    {
        $independent = app(IndependentAcademicContextService::class);
        $context = $independent->context();
        $type = $independent->ensureResearchType();
        $owner = User::factory()->create(['university_id' => null, 'department_id' => null]);
        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => 'Focused Control Center',
            'status' => 'draft',
            'progress' => 0,
        ]);

        $this->actingAs($owner)
            ->get(route('research.workspace', $project))
            ->assertOk()
            ->assertSee('Milestones')
            ->assertSee('Project tasks')
            ->assertSee('Literature & references')
            ->assertDontSee('Research meetings & action items')
            ->assertDontSee('Collaboration & contribution evidence')
            ->assertDontSee('Approved-version archive');
    }

    public function test_literature_workflow_tracks_reading_status_collections_synthesis_and_compare(): void
    {
        $independent = app(IndependentAcademicContextService::class);
        $context = $independent->context();
        $type = $independent->ensureResearchType();
        $owner = User::factory()->create(['university_id' => null, 'department_id' => null]);
        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => 'Literature Workflow Test',
            'status' => 'draft',
            'progress' => 0,
        ]);

        $first = AcademicReference::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Machine Learning for Academic Performance',
            'authors' => [['name' => 'Ada Researcher'], ['name' => 'Ben Scholar']],
            'publication_year' => 2025,
            'source_type' => 'scholarly_work',
            'citation_key' => 'lit-workflow-one',
        ]);
        $second = AcademicReference::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Educational Analytics in Universities',
            'authors' => ['Chi Analyst'],
            'publication_year' => 2024,
            'source_type' => 'scholarly_work',
            'citation_key' => 'lit-workflow-two',
        ]);
        $project->referenceLinks()->create(['academic_reference_id' => $first->id, 'purpose' => 'research_library']);
        $project->referenceLinks()->create(['academic_reference_id' => $second->id, 'purpose' => 'research_library']);

        $this->actingAs($owner)->put(route('research.literature.note', [$project, $first]), [
            'reading_status' => 'reviewed',
            'summary' => 'This paper evaluates predictive models for student outcomes.',
            'methodology' => 'The study compared random forest and logistic regression on institutional data.',
            'findings' => 'Random forest produced the strongest predictive accuracy.',
            'limitations' => 'The dataset came from a single institution.',
            'contradictions' => 'The results differ from studies that prefer support vector machines.',
            'research_gap' => 'Explainability across multiple institutions remains unresolved.',
            'keywords' => 'machine learning, student performance',
            'collections' => 'Chapter Two, Predictive Analytics',
        ])->assertRedirect();

        $this->actingAs($owner)->put(route('research.literature.note', [$project, $second]), [
            'reading_status' => 'reading',
            'collections' => 'Chapter Two',
        ])->assertRedirect();

        $note = ResearchLiteratureNote::query()->where('academic_reference_id', $first->id)->firstOrFail();
        $this->assertSame('reviewed', $note->reading_status);
        $this->assertSame(['Chapter Two', 'Predictive Analytics'], $note->collections);
        $this->assertNotNull($note->reviewed_at);

        $this->actingAs($owner)
            ->get(route('research.literature.search', ['research' => $project, 'view' => 'synthesis']))
            ->assertOk()
            ->assertSee('Reviewed Papers')
            ->assertSee('Machine Learning')
            ->assertSee('Random Forest')
            ->assertSee('Explainability across multiple institutions remains unresolved.');

        $this->actingAs($owner)
            ->get(route('research.literature.search', [
                'research' => $project,
                'view' => 'compare',
                'compare' => [$first->uuid, $second->uuid],
            ]))
            ->assertOk()
            ->assertSee('Machine Learning for Academic Performance')
            ->assertSee('Educational Analytics in Universities')
            ->assertSee('Random forest produced the strongest predictive accuracy.');
    }

    public function test_reviewed_literature_is_available_in_chapter_two_writing_workspace(): void
    {
        $independent = app(IndependentAcademicContextService::class);
        $context = $independent->context();
        $type = $independent->ensureResearchType();
        $owner = User::factory()->create(['university_id' => null, 'department_id' => null]);
        $project = ResearchProject::query()->create([
            'university_id' => $context['university']->id,
            'department_id' => $context['department']->id,
            'workspace_mode' => 'independent',
            'academic_session_id' => $context['session']->id,
            'semester_id' => $context['semester']->id,
            'research_type_id' => $type->id,
            'owner_id' => $owner->id,
            'title' => 'Chapter Two Integration Test',
            'status' => 'draft',
            'progress' => 0,
        ]);
        $document = ContentDocument::query()->create([
            'owner_id' => $owner->id,
            'document_type' => 'research_section',
            'title' => 'Chapter 2: Literature Review',
            'body' => '',
            'status' => 'draft',
            'visibility' => 'private',
            'version_number' => 1,
        ]);
        ResearchSection::query()->create([
            'research_project_id' => $project->id,
            'content_document_id' => $document->id,
            'created_by' => $owner->id,
            'key' => 'chapter_2',
            'title' => 'Chapter 2: Literature Review',
            'position' => 2,
            'is_required' => true,
            'status' => 'draft',
        ]);
        $reference = AcademicReference::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Reviewed Evidence Paper',
            'authors' => [['display_name' => 'Jane Scholar']],
            'publication_year' => 2026,
            'source_type' => 'scholarly_work',
            'citation_key' => 'chapter-two-reviewed-paper',
        ]);
        $project->referenceLinks()->create(['academic_reference_id' => $reference->id, 'purpose' => 'research_library']);
        $project->literatureNotes()->create([
            'academic_reference_id' => $reference->id,
            'created_by' => $owner->id,
            'reading_status' => 'reviewed',
            'findings' => 'The reviewed study found a strong relationship between engagement and academic performance.',
            'collections' => ['Chapter Two'],
            'reviewed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('research.show', $project))
            ->assertOk()
            ->assertSee('Insert from your references')
            ->assertSee('Reviewed Evidence Paper')
            ->assertSee('Insert citation')
            ->assertSee('Insert evidence');
    }

    public function test_reference_formatter_supports_nested_scholarly_author_payloads(): void
    {
        $owner = User::factory()->create();
        $reference = AcademicReference::query()->create([
            'owner_id' => $owner->id,
            'title' => 'Nested Author Metadata',
            'authors' => [
                ['author' => ['display_name' => 'First Author']],
                ['name' => 'Second Author'],
            ],
            'publication_year' => 2026,
            'source_type' => 'scholarly_work',
            'citation_key' => 'nested-author-test',
        ]);

        $service = app(AcademicReferenceService::class);
        $this->assertStringContainsString('First Author', $service->format($reference, 'apa'));
        $this->assertStringContainsString('First Author et al.', $service->inText($reference, 'apa'));
    }

    private function approveProfile(User $user): void
    {
        if(!$user->university_id)return;
        \App\Models\UserInstitutionAffiliation::firstOrCreate(['user_id'=>$user->id,'university_id'=>$user->university_id,'department_id'=>$user->department_id,'affiliation_type'=>$user->role],['faculty_id'=>$user->department?->faculty_id,'status'=>'verified','verified_at'=>now(),'source'=>'test_review']);
    }

}
