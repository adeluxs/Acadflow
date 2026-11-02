<?php

namespace Tests\Feature;

use App\Models\ResearchProject;
use App\Models\ResearchResource;
use App\Models\User;
use App\Services\ContentWorkspaceService;
use App\Services\FeatureAccessService;
use App\Services\IndependentAcademicContextService;
use App\Services\ResearchAnalysisService;
use App\Services\ResearchDocumentExportService;
use App\Services\ResearchProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResearchStudioEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User { return User::factory()->create(['role' => 'student', 'account_type' => 'student', 'university_id' => null, 'department_id' => null]); }
    private function data(string $method = 'software', array $extra = []): array
    {
        $context = app(IndependentAcademicContextService::class)->context();
        return array_merge(['research_type_id' => app(IndependentAcademicContextService::class)->ensureResearchType()->id, 'workspace_mode' => 'independent', 'university_id' => $context['university']->id, 'department_id' => $context['department']->id, 'academic_session_id' => $context['session']->id, 'semester_id' => $context['semester']->id, 'title' => 'A real research workspace', 'methodology' => $method, 'discipline' => 'Computer science', 'objectives' => 'Evaluate the supplied evidence.', 'research_level' => 'undergraduate', 'report_structure' => 'methodology'], $extra);
    }
    private function project(User $owner, string $method = 'software', array $extra = []): ResearchProject { return app(ResearchProjectService::class)->create($this->data($method, $extra), $owner); }

    public function test_all_designs_create_distinct_saved_plans_without_fabricated_content(): void
    {
        $owner = $this->owner();
        foreach (config('research_methodologies.methodologies') as $key => $method) {
            $project = $this->project($owner, $key);
            $this->assertSame($key, data_get($project->metadata, 'research_setup.methodology'));
            $this->assertSame($method['decision'], data_get($project->metadata, 'research_setup.method.decision'));
            $this->assertCount(count($method['sections']) + 3, $project->sections);
            $this->assertCount(count($method['stages']), $project->milestones);
            $this->assertTrue($project->sections->every(fn ($s) => $s->document->body === ''));
            $this->assertSame(0.0, app(ResearchProjectService::class)->recalculateProgress($project));
        }
        $this->assertFalse($this->project($owner, 'doctrinal_legal')->sections->contains('key', 'chapter_3'));
    }

    public function test_final_year_structure_and_admin_templates_remain_configurable(): void
    {
        $owner = $this->owner();
        $project = $this->project($owner, 'interviews', ['studio_mode' => 'final_year', 'report_structure' => 'five_chapter']);
        $this->assertCount(10, $project->sections);
        $this->assertTrue($project->sections->contains('key', 'defence_preparation'));
        $this->assertSame(['coreq', 'srqr'], data_get($project->metadata, 'research_setup.method.frameworks'));
        $type = $project->researchType;
        $type->update(['template_schema' => ['sections' => [['key' => 'approved_custom', 'title' => 'Institution structure']]]]);
        $custom = $this->project($owner, 'systematic_review', ['report_structure' => 'institutional']);
        $this->assertSame(['approved_custom'], $custom->sections->pluck('key')->all());
        $this->actingAs($owner)->get(route('research.create'))->assertOk()->assertSee('Final-Year Project Studio')->assertSee('Phenomenology');
        $this->get(route('research.plan', $project))->assertOk()->assertSee('COREQ')->assertSee('Sample-size planning');
    }

    public function test_autosave_rejects_stale_versions_and_preserves_history_and_progress(): void
    {
        $owner = $this->owner(); $project = $this->project($owner); $section = $project->sections->first();
        $url = route('research.sections.save', [$project, $section]);
        $this->actingAs($owner)->putJson($url, ['body' => '<p>Original evidence.</p>', 'expected_version' => 1])->assertOk()->assertJsonPath('version', 2);
        $this->putJson($url, ['body' => '<p>Stale overwritten evidence.</p>', 'expected_version' => 1])->assertConflict();
        $this->assertSame('<p>Original evidence.</p>', $section->document->fresh()->body);
        $this->putJson($url, ['body' => '<p>Original evidence.</p>', 'expected_version' => 2])->assertOk()->assertJsonPath('version', 2);
        $this->assertSame(2, $section->document->versions()->count());
        $this->assertGreaterThan(0, (float) $project->fresh()->progress);
        $initial = $section->document->versions()->where('version_number', 1)->first();
        $this->post(route('research.sections.versions.restore', [$project, $section, $initial]))->assertRedirect();
        $this->assertSame('', $section->document->fresh()->body);
        $this->assertSame(3, $section->document->fresh()->version_number);
    }

    public function test_private_library_resources_have_versions_and_deny_other_projects_and_users(): void
    {
        $owner = $this->owner(); $project = $this->project($owner);
        $this->actingAs($owner)->post(route('research.resources.store', $project), ['kind' => 'instrument', 'title' => 'Interview guide'])->assertRedirect()->assertSessionHasNoErrors();
        $resource = $project->resources()->firstOrFail();
        $this->assertStringContainsString('pilot', strtolower($resource->document->body));
        $this->putJson(route('research.resources.save', [$project, $resource]), ['body' => '<p>A reviewed question.</p>', 'expected_version' => 1])->assertOk();
        $this->assertSame(2, $resource->document->fresh()->version_number);
        $this->putJson(route('research.resources.save', [$project, $resource]), ['body' => 'stale', 'expected_version' => 1])->assertConflict();
        $other = $this->project($owner);
        $this->putJson(route('research.resources.save', [$other, $resource]), ['body' => 'cross project', 'expected_version' => 2])->assertNotFound();
        $this->actingAs($this->owner())->get(route('research.plan', $project))->assertForbidden();
        $this->putJson(route('research.resources.save', [$project, $resource]), ['body' => 'forbidden', 'expected_version' => 2])->assertForbidden();
    }

    public function test_guideline_upload_is_private_and_requires_confirmation_before_structure_changes(): void
    {
        Storage::fake('local'); Storage::fake('public');
        \App\Services\SettingService::set('allowed_file_extensions', 'pdf,docx,txt,csv');
        $owner = $this->owner(); $project = $this->project($owner);
        $first = $project->sections->first();
        app(ContentWorkspaceService::class)->autosave($first->document, $owner, '<p>Keep my research.</p>');
        $this->actingAs($owner)->post(route('research.resources.store', $project), ['kind' => 'guideline', 'title' => 'Department handbook', 'file' => UploadedFile::fake()->createWithContent('handbook.txt', "Chapter One: Context\nChapter Two: Analysis\nReferences")])->assertRedirect()->assertSessionHasNoErrors();
        $resource = $project->resources()->firstOrFail();
        $this->assertSame('private', $resource->mediaAsset->visibility);
        $this->assertSame('local', $resource->mediaAsset->disk);
        $this->assertCount(3, $resource->metadata['suggested_headings']);
        $before = $project->sections()->count();
        $url = route('research.resources.structure', [$project, $resource]);
        $this->post($url, ['headings' => "Chapter One: Context\nReferences"])->assertSessionHasErrors('confirmed');
        $this->assertSame($before, $project->sections()->count());
        $this->post($url, ['headings' => "Chapter One: Context\nReferences", 'confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($before + 1, $project->sections()->count());
        $this->assertSame('<p>Keep my research.</p>', $first->document->fresh()->body);
        $this->get(route('research.resources.download', [$project, $resource]))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($this->owner())->get(route('research.resources.download', [$project, $resource]))->assertForbidden();
    }

    public function test_computed_analysis_uses_only_supplied_values_and_obeys_module_settings(): void
    {
        $owner = $this->owner(); $project = $this->project($owner, 'survey');
        $data = ['file' => UploadedFile::fake()->createWithContent('data.csv', "score,group\n1,A\n2,B\n3,A\n,B\n"), 'deidentified' => 1];
        $url = route('research.analysis.describe', $project);
        $this->actingAs($owner)->post($url, $data)->assertForbidden();
        FeatureAccessService::setStatus('research_datasets', FeatureAccessService::STATUS_ENABLED, $owner->id);
        $this->post($url, $data)->assertRedirect();
        $resource = $project->resources()->firstOrFail();
        $this->assertSame(4, $resource->metadata['rows']);
        $this->assertSame(2, $resource->metadata['columns'][0]['mean']);
        $this->assertSame(1, $resource->metadata['columns'][0]['sample_sd']);
        $this->assertSame(1, $resource->metadata['columns'][0]['missing']);
        $this->assertArrayNotHasKey('values', $resource->metadata);
        $this->assertNull($resource->media_asset_id);
        $this->get(route('research.plan', ['research' => $project, 'resource' => $resource->uuid]))->assertOk()->assertSee('Data completeness by column');
        $this->putJson(route('research.resources.save', [$project, $resource]), ['body' => 'Invented results', 'expected_version' => 1])->assertStatus(423);
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('data.csv', "x,x\n1,2"), 'deidentified' => 1])->assertSessionHasErrors('file');
    }

    public function test_sample_size_records_assumptions_without_claiming_power_or_approval(): void
    {
        $result = app(ResearchAnalysisService::class)->sampleSize(95, .05, .5, null, 1);
        $this->assertSame(385, $result['completed_responses']);
        $this->assertSame(385, $result['invite']);
        $finite = app(ResearchAnalysisService::class)->sampleSize(95, .05, .5, 1000, .8);
        $this->assertSame(278, $finite['completed_responses']);
        $this->assertSame(348, $finite['invite']);
        $owner = $this->owner(); $project = $this->project($owner);
        $this->actingAs($owner)->post(route('research.analysis.sample-size', $project), ['confidence' => 95, 'margin' => .05, 'proportion' => .5, 'response_rate' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStringContainsString('Not a power calculation', $project->resources()->first()->document->body);
    }

    public function test_docx_is_real_parseable_open_xml_and_pdf_and_html_use_authorised_content(): void
    {
        $owner = $this->owner(); $project = $this->project($owner, 'doctrinal_legal');
        app(ContentWorkspaceService::class)->autosave($project->sections->first()->document, $owner, '<p>Actual <strong>legal evidence &amp; analysis</strong>.</p><table><tr><td>Authority</td><td>2026</td></tr></table>');
        $path = app(ResearchDocumentExportService::class)->docx($project);
        try {
            $zip = new \ZipArchive; $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('word/document.xml'); $this->assertStringContainsString('<w:b/>', $xml);
            $this->assertStringContainsString('legal evidence &amp; analysis', $xml);
            $this->assertStringContainsString('<w:tbl>', $xml);
            $this->assertNotFalse(simplexml_load_string($xml));
            $this->assertNotFalse(simplexml_load_string($zip->getFromName('word/styles.xml'))); $zip->close();
        } finally { unlink($path); }
        $this->actingAs($owner)->get(route('research.export.docx', $project))->assertDownload('a-real-research-workspace.docx');
        $this->get(route('research.export.html', $project))->assertOk()->assertSee('legal evidence &amp; analysis', false)->assertSee('Contents');
        $response = $this->get(route('research.export.pdf', $project))->assertOk();
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->actingAs($this->owner())->get(route('research.export.docx', $project))->assertForbidden();
    }
    public function test_qualitative_codes_remain_source_bound_and_insertion_detects_stale_sections(): void
    {
        $owner = $this->owner(); $project = $this->project($owner, 'interviews');
        $this->actingAs($owner)->post(route('research.resources.store', $project), ['kind' => 'coding', 'title' => 'Anonymised transcript register'])->assertSessionHasNoErrors();
        $source = $project->resources()->firstOrFail();
        $payload = ['source_uuid' => $source->uuid, 'locator' => 'P01 lines 12–14', 'excerpt' => 'Researcher-supplied anonymised evidence.', 'code' => 'Access', 'definition' => 'Statements about access barriers.', 'theme' => 'Participation', 'memo' => 'Consider alternative interpretations.', 'deidentified' => 1];
        $this->post(route('research.analysis.coding', $project), $payload)->assertSessionHasNoErrors();
        $coded = $project->resources()->where('kind', 'coded_excerpt')->firstOrFail();
        $this->assertSame($source->uuid, $coded->metadata['source_uuid']);
        $this->get(route('research.plan', ['research' => $project, 'resource' => $coded->uuid]))->assertOk()->assertSee('Participation')->assertSee('Consider alternative interpretations.');
        $section = $project->sections->first();
        $this->post(route('research.resources.insert', [$project, $coded]), ['destination' => $section->uuid.':1'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('Researcher-supplied anonymised evidence.', $section->document->fresh()->body);
        $this->postJson(route('research.resources.insert', [$project, $coded]), ['destination' => $section->uuid.':1'])->assertConflict();
        $other = $this->project($owner);
        $this->post(route('research.analysis.coding', $other), $payload)->assertNotFound();
        $this->patch(route('research.resources.archive', [$project, $coded]), ['archived' => 1])->assertSessionHasNoErrors();
        $this->assertNotNull(data_get($coded->fresh()->metadata, 'archived_at'));
        $this->assertNotNull($coded->fresh()->document);
    }

    public function test_tables_survive_sanitizing_without_active_attributes(): void
    {
        $clean = app(\App\Services\RichTextSanitizer::class)->sanitize('<table onclick="alert(1)"><tr><td style="background:url(javascript:bad)">Observed value</td></tr></table>');
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringContainsString('Observed value', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $nested = '<unknown><another><span onclick="alert(1)">Evidence</span><img src="javascript:bad" onerror="alert(1)"><script>alert(1)</script></another></unknown>';
        foreach ([app(\App\Services\RichTextSanitizer::class), app(\App\Services\SafeHtmlService::class)] as $sanitizer) {
            $safe = $sanitizer->sanitize($nested);
            $this->assertStringContainsString('Evidence', $safe);
            $this->assertStringNotContainsString('onclick', $safe);
            $this->assertStringNotContainsString('onerror', $safe);
            $this->assertStringNotContainsString('javascript:', $safe);
            $this->assertStringNotContainsString('<script', $safe);
        }
    }

    public function test_supervisor_approval_is_version_bound_and_corrections_unlock_author_work(): void
    {
        $owner = $this->owner(); $project = $this->project($owner, 'qualitative');
        $supervisor = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $project->update(['supervisor_id' => $supervisor->id]);
        $section = $project->sections->first();
        $approve = route('research.sections.approve', [$project, $section]);
        $save = route('research.sections.save', [$project, $section]);
        $this->actingAs($supervisor)->postJson($approve, ['expected_version' => 1])->assertUnprocessable();
        $this->actingAs($owner)->putJson($save, ['body' => 'Actual evidence for review.', 'expected_version' => 1])->assertOk();
        $this->actingAs($supervisor)->postJson($approve, ['expected_version' => 1])->assertConflict();
        $this->post($approve, ['expected_version' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($section->fresh()->locked_at);
        $this->actingAs($owner)->putJson($save, ['body' => 'Should not bypass lock.', 'expected_version' => 2])->assertStatus(423);
        $this->actingAs($supervisor)->post(route('research.sections.corrections', [$project, $section]), ['type' => 'methodology', 'description' => 'Explain the selection rationale.'])->assertSessionHasNoErrors();
        $this->assertNull($section->fresh()->locked_at);
        $this->assertNull($section->fresh()->approved_at);
        $this->actingAs($owner)->putJson($save, ['body' => 'Actual evidence with a clarified selection rationale.', 'expected_version' => 2])->assertOk()->assertJsonPath('version', 3);
    }

    public function test_reporting_preparation_and_document_formatting_are_saved_and_validated(): void
    {
        $owner = $this->owner(); $project = $this->project($owner, 'systematic_review');
        $data = ['objectives' => 'Synthesize verified studies.', 'citation_style' => 'vancouver', 'format_font' => 'sans-serif', 'format_size' => 11, 'format_spacing' => 2, 'format_margin' => 30, 'requirements_reviewed' => [0, 1], 'reporting_review' => ['prisma:0', 'prisma:1'], 'citation_template' => '{authors} / {year} / {title}'];
        $this->actingAs($owner)->put(route('research.plan.update', $project), $data)->assertSessionHasNoErrors();
        $this->assertSame('vancouver', $project->fresh()->metadata['citation_style']);
        $reference = \App\Models\AcademicReference::create(['owner_id' => $owner->id, 'title' => 'Verified source', 'authors' => ['Actual Author'], 'publication_year' => 2024, 'citation_key' => 'verified']);
        $this->assertSame('Actual Author / 2024 / Verified source', app(\App\Services\AcademicReferenceService::class)->formatForProject($reference, $project->fresh()));
        $this->put(route('research.plan.update', $project), array_replace($data, ['citation_template' => '{invented} {title}']))->assertSessionHasErrors('citation_template');
        $this->assertSame(['prisma:0', 'prisma:1'], data_get($project->fresh()->metadata, 'research_setup.reporting_review'));
        $this->get(route('research.export.html', $project))->assertOk()->assertSee('font-size:11pt', false)->assertSee('margin:30mm', false);
        $this->put(route('research.plan.update', $project), array_replace($data, ['reporting_review' => ['invented:99']]))->assertSessionHasErrors('reporting_review.0');
    }

}
