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

    public function test_resource_lists_are_paginated_and_only_the_selected_document_body_is_loaded(): void
    {
        [$owner, $project] = $this->independentProject('Resource paging');
        \Illuminate\Support\Facades\Queue::fake();
        $first = null;
        for ($i = 0; $i < 31; $i++) {
            $document = ContentDocument::create(['owner_id' => $owner->id, 'document_type' => 'research_resource', 'title' => 'Resource '.$i, 'body' => '<p>Hidden resource body '.$i.'</p>', 'version_number' => 1]);
            $resource = \App\Models\ResearchResource::create(['research_project_id' => $project->id, 'created_by' => $owner->id, 'content_document_id' => $document->id, 'kind' => 'instrument', 'title' => 'Resource '.$i]);
            $first ??= $resource;
        }
        $this->actingAs($owner)->get(route('research.plan', $project))->assertOk()
            ->assertViewHas('resources', fn ($rows) => $rows->count() === 25 && $rows->total() === 31)
            ->assertDontSee('Hidden resource body');
        $this->get(route('research.plan', ['research' => $project, 'resource' => $first->uuid]))->assertOk()->assertSee('Hidden resource body 0');
        $this->get(route('research.plan', ['research' => $project, 'resources_page' => 2]))->assertOk()->assertViewHas('resources', fn ($rows) => $rows->count() === 6);
    }

    public function test_library_filters_apply_before_pagination_and_discovery_does_not_load_the_library(): void
    {
        [$owner, $project] = $this->independentProject('Library paging');
        for ($i = 0; $i < 31; $i++) {
            $reference = \App\Models\AcademicReference::create(['owner_id' => $owner->id, 'title' => $i === 0 ? 'Needle older paper' : 'Other paper '.$i, 'citation_key' => 'paged-reference-'.$i]);
            $project->referenceLinks()->create(['academic_reference_id' => $reference->id]);
        }
        $this->actingAs($owner)->get(route('research.literature.search', $project))->assertOk()
            ->assertViewHas('referenceLinks', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('libraryStats', fn ($stats) => $stats['total'] === 31);
        $this->get(route('research.literature.search', ['research' => $project, 'view' => 'saved']))->assertOk()
            ->assertViewHas('libraryPage', fn ($page) => $page->count() === 25 && $page->total() === 31);
        $this->get(route('research.literature.search', ['research' => $project, 'view' => 'saved', 'library_q' => 'Needle']))->assertOk()
            ->assertSee('Needle older paper')->assertViewHas('libraryPage', fn ($page) => $page->total() === 1);
    }

    public function test_large_exports_are_deduplicated_built_in_a_job_and_downloadable_only_by_the_requester(): void
    {
        [$owner, $project] = $this->independentProject('Queued research export');
        $section = $this->section($project, $owner, 'chapter_1', 'Chapter 1: Introduction', 1);
        $section->document->update(['body' => '<p>Actual supplied research text.</p>']);
        config(['performance.queued_export_bytes' => 1, 'queue.default' => 'database']);
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        $response = $this->actingAs($owner)->get(route('research.export.docx', $project))->assertRedirect();
        $location = $response->headers->get('Location');
        $id = basename($location);
        $this->get(route('research.export.docx', $project))->assertRedirect($location);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\BuildResearchExport::class, 1);
        \Illuminate\Support\Facades\Queue::assertPushedOn('exports', \App\Jobs\BuildResearchExport::class);
        $this->getJson($location)->assertOk()->assertJsonPath('status', 'queued');
        $job = new \App\Jobs\BuildResearchExport($id);
        $job->handle(app(\App\Services\ResearchDocumentExportService::class));
        $this->getJson($location)->assertOk()->assertJsonPath('status', 'completed');
        $this->get(route('research.exports.download', $id))->assertOk()->assertDownload('queued-research-export.docx');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']))->getJson($location)->assertNotFound();
    }

    public function test_failed_exports_can_be_retried_and_expired_artifacts_are_pruned(): void
    {
        [$owner, $project] = $this->independentProject('Export retry');
        config(['queue.default' => 'database']);
        \Illuminate\Support\Facades\Queue::fake();
        $service = app(\App\Services\ResearchExportQueue::class);
        $id = $service->start($project, $owner, 'pdf');
        (new \App\Jobs\BuildResearchExport($id))->failed(new \RuntimeException('Internal transport detail'));
        $this->actingAs($owner)->getJson(route('research.exports.show', $id))->assertOk()->assertJsonPath('status', 'failed')->assertDontSee('Internal transport detail');
        $this->assertNotSame($id, $service->start($project, $owner, 'pdf'));
        \Illuminate\Support\Facades\Storage::fake('local');
        $old = 'research-exports/'.now()->subDays(4)->format('Y-m-d').'/old.pdf';
        $new = 'research-exports/'.now()->format('Y-m-d').'/new.pdf';
        \Illuminate\Support\Facades\Storage::disk('local')->put($old, 'old');
        \Illuminate\Support\Facades\Storage::disk('local')->put($new, 'new');
        $this->artisan('research:prune-exports')->assertSuccessful();
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($old);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($new);
    }

    public function test_writing_history_is_paginated_and_older_versions_remain_accessible(): void
    {
        [$owner, $project] = $this->independentProject('Bounded writing history');
        $section = $this->section($project, $owner, 'chapter_1', 'Chapter 1: Introduction', 1);
        $other = $this->section($project, $owner, 'chapter_2', 'Chapter 2: Literature Review', 2);
        for ($i = 1; $i <= 30; $i++) {
            \App\Models\ContentVersion::create(['content_document_id' => $section->content_document_id, 'version_number' => $i, 'body' => 'History marker '.$i, 'created_at' => now()]);
            \App\Models\ContentVersion::create(['content_document_id' => $other->content_document_id, 'version_number' => $i, 'body' => 'Unselected history '.$i, 'created_at' => now()]);
        }
        $this->actingAs($owner)->get(route('research.show', $project))->assertOk()
            ->assertViewHas('sectionVersions', fn ($versions) => $versions->count() === 5 && $versions->total() === 30)
            ->assertSee('History marker 30')->assertDontSee('History marker 1<', false)->assertDontSee('Unselected history');
        $this->get(route('research.show', ['research' => $project, 'versions_page' => 6]))->assertOk()->assertSee('History marker 1');
    }

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
