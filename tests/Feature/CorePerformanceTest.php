<?php

namespace Tests\Feature;

use App\Jobs\IndexSearchableContent;
use App\Jobs\RemoveSearchableContent;
use App\Models\CommercialAccount;
use App\Models\Course;
use App\Models\ContentDocument;
use App\Models\Department;
use App\Models\FeatureEntitlement;
use App\Models\KnowledgeCitation;
use App\Models\KnowledgePublication;
use App\Models\PricingRule;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Models\Enrollment;
use App\Models\LecturerCourseAssignment;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\SubmissionGrade;
use App\Services\Commerce\AcademicSponsorshipService;
use App\Services\Commerce\PricingCatalogService;
use App\Services\Discovery\SearchIndexService;
use App\Services\KnowledgeDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CorePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function publication(?User $user = null): KnowledgePublication
    {
        $user ??= User::factory()->create();
        $document = ContentDocument::create(['owner_id' => $user->id, 'document_type' => 'knowledge_publication', 'title' => 'Efficient academic resource', 'body' => '<p>Research methods and evidence.</p>', 'status' => 'published', 'visibility' => 'public']);

        return KnowledgePublication::create(['creator_id' => $user->id, 'content_document_id' => $document->id, 'title' => 'Efficient academic resource', 'content_type' => 'study_guide', 'status' => 'published', 'published_at' => now()->subDay(), 'visibility' => 'public', 'access_type' => 'free']);
    }

    private function queriesDuring(callable $callback): array
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $callback();

            return DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_read_counters_and_editor_bookkeeping_do_not_queue_index_rebuilds_but_content_and_visibility_do(): void
    {
        Queue::fake();
        $publication = $this->publication()->fresh();
        Queue::fake();
        for ($i = 0; $i < 10; $i++) {
            $publication->increment('view_count');
        }
        $publication->increment('bookmark_count');
        $document = $publication->document;
        $document->update(['autosaved_at' => now(), 'version_number' => 2, 'locked_by' => $publication->creator_id]);
        Queue::assertNothingPushed();

        $document->update(['body' => '<p>Changed methods.</p>', 'version_number' => 3]);
        $publication->update(['visibility' => 'institution']);
        Queue::assertPushed(IndexSearchableContent::class, 2);
        $publication->update(['status' => 'draft']);
        Queue::assertPushed(RemoveSearchableContent::class, 1);
    }

    public function test_unchanged_indexing_preserves_chunk_ids_and_performs_no_writes_or_embedding_work(): void
    {
        Queue::fake();
        $publication = $this->publication();
        $index = app(SearchIndexService::class);
        $first = $index->index($publication);
        $chunkIds = $first->chunks->pluck('id')->all();
        $this->assertNotEmpty($chunkIds);
        $queries = $this->queriesDuring(function () use ($index, $publication, $chunkIds): void {
            $second = $index->index($publication->fresh());
            $this->assertSame($chunkIds, $second->chunks->pluck('id')->all());
        });
        $this->assertEmpty(array_filter($queries, fn ($q) => preg_match('/^(insert|update|delete)/i', $q['query'])), 'Unchanged search content must not rewrite documents or chunks.');

        $publication->update(['visibility' => 'institution', 'access_type' => 'institution']);
        $changed = $index->index($publication->fresh());
        $this->assertNotSame($first->checksum, $changed->checksum);
        $this->assertSame('institution', $changed->visibility);
        $this->assertSame('institution', $changed->access_type);
        $publication->document->update(['body' => '<p>Updated research evidence.</p>']);
        $updated = $index->index($publication->fresh());
        $this->assertStringContainsString('Updated research evidence.', $updated->body);
    }

    public function test_an_old_index_job_cannot_republish_unpublished_content(): void
    {
        Queue::fake();
        $publication = $this->publication();
        $index = app(SearchIndexService::class);
        $index->index($publication);
        $publication->update(['status' => 'draft']);
        (new IndexSearchableContent(KnowledgePublication::class, $publication->id))->handle($index);
        $this->assertDatabaseMissing('search_documents', ['searchable_type' => KnowledgePublication::class, 'searchable_id' => $publication->id]);
    }

    public function test_knowledge_cards_use_bounded_previews_while_detail_keeps_full_body_without_version_or_citation_hydration(): void
    {
        Queue::fake();
        $publication = $this->publication();
        $body = '<p>Research introduction.</p>'.str_repeat('<p>Evidence and methods.</p>', 4000).'<p>FULL_DOCUMENT_END</p>';
        $publication->document->update(['body' => $body]);
        $publication->document->versions()->create(['version_number' => 1, 'body' => str_repeat('HISTORICAL_PRIVATE_BODY', 1000), 'author_id' => $publication->creator_id, 'change_summary' => 'Initial version']);
        $citing = $this->publication($publication->creator);
        KnowledgeCitation::create(['citing_publication_id' => $citing->id, 'cited_publication_id' => $publication->id]);
        $cards = app(KnowledgeDiscoveryService::class)->search([], null);
        $card = $cards->getCollection()->firstWhere('id', $publication->id);
        $this->assertLessThanOrEqual(2048, mb_strlen($card->document->body));
        $this->assertStringContainsString('Research introduction.', $card->document->body);
        $this->assertStringNotContainsString('FULL_DOCUMENT_END', $card->document->body);

        $queries = $this->queriesDuring(function () use ($publication): void {
            $this->get(route('knowledge.show', $publication))->assertOk()->assertSee('FULL_DOCUMENT_END')->assertDontSee('HISTORICAL_PRIVATE_BODY')
                ->assertViewHas('publication', fn ($record) => $record->citations_received_count === 1 && ! $record->relationLoaded('citationsReceived') && ! $record->document->relationLoaded('versions'));
        });
        $this->assertFalse(collect($queries)->contains(fn ($q) => str_contains($q['query'], 'from "content_versions"')));
    }

    public function test_bulk_pricing_matches_individual_version_scope_and_time_rules_in_one_query(): void
    {
        $catalog = app(PricingCatalogService::class);
        foreach (['feature.research_studio', 'ai.study_assistant'] as $key) {
            foreach ([['version' => 1], ['version' => 2], ['version' => 3, 'enabled' => false], ['version' => 4, 'starts_at' => now()->addDay()], ['version' => 5, 'ends_at' => now()->subDay()], ['version' => 6, 'scope_type' => 'institution', 'scope_id' => 77]] as $attributes) {
                PricingRule::create(array_merge(['key' => $key, 'name' => $key, 'scope_type' => 'global', 'scope_id' => 0, 'version' => 1, 'currency' => 'NGN', 'unit_amount_minor' => 100, 'enabled' => true], $attributes));
            }
        }
        $keys = ['feature.research_studio', 'ai.study_assistant', 'feature.missing'];
        $expected = collect($keys)->mapWithKeys(fn ($key) => [$key => $catalog->activeRule($key)?->id]);
        $rules = null;
        $queries = $this->queriesDuring(function () use ($catalog, $keys, &$rules): void { $rules = $catalog->activeRules($keys); });
        $this->assertCount(1, $queries);
        foreach ($keys as $key) {
            $this->assertSame($expected[$key], $rules->get($key)?->id);
        }
        $this->assertSame($catalog->aiPricing('study_assistant')['amount_minor'], $catalog->aiPricingFromRule($rules->get('ai.study_assistant'))['amount_minor']);
    }

    public function test_paid_features_page_batches_pricing_entitlements_and_sponsors_and_reloads_changed_access(): void
    {
        $department = Department::factory()->create(['adoption_status' => 'independent_active']);
        $institution = $department->faculty->university;
        $student = User::factory()->create(['role' => 'student', 'university_id' => $institution->id, 'department_id' => $department->id]);
        UserInstitutionAffiliation::create(['user_id' => $student->id, 'university_id' => $institution->id, 'department_id' => $department->id, 'faculty_id' => $department->faculty_id, 'affiliation_type' => 'student', 'status' => 'verified', 'verified_at' => now(), 'source' => 'test_review']);
        $definitions = [];
        for ($i = 0; $i < 40; $i++) {
            $definitions['feature_'.$i] = ['title' => 'Feature '.$i, 'category' => 'Academic tools', 'description' => 'Academic feature '.$i];
        }
        config(['monetization.pricing_features' => $definitions]);
        $account = CommercialAccount::create(['department_id' => $department->id, 'name' => 'Department sponsor', 'currency' => 'NGN', 'status' => 'active', 'prepaid_balance_minor' => 10000, 'metadata' => ['sponsored_features' => ['feature_0']]]);
        $future = FeatureEntitlement::create(['user_id' => $student->id, 'feature' => 'feature_1', 'access_type' => 'granted', 'status' => 'active', 'starts_at' => now()->addDay()]);
        $queries = $this->queriesDuring(function () use ($student): void {
            $this->actingAs($student)->get(route('commerce.features'))->assertOk()
                ->assertViewHas('featureRows', fn ($rows) => $rows->count() === 40 && $rows->firstWhere('key', 'feature_0')['sponsored'] && ! $rows->firstWhere('key', 'feature_1')['entitlement']);
        });
        foreach (['pricing_rules', 'feature_entitlements', 'commercial_accounts'] as $table) {
            $this->assertCount(1, array_filter($queries, fn ($q) => str_starts_with($q['query'], 'select') && str_contains($q['query'], 'from "'.$table.'"')), 'Catalogue should query '.$table.' once regardless of feature count.');
        }
        $account->update(['status' => 'paused']);
        $this->get(route('commerce.features'))->assertOk()->assertViewHas('featureRows', fn ($rows) => ! $rows->firstWhere('key', 'feature_0')['sponsored']);
        $this->assertSame(10000, $account->fresh()->prepaid_balance_minor);
        $this->assertNull(app(AcademicSponsorshipService::class)->account($student->fresh(), 'feature_0', lock: true));
    }

    public function test_dashboards_bound_course_cards_while_totals_and_grades_include_courses_outside_the_preview(): void
    {
        Queue::fake();
        $student = User::factory()->create(['role' => 'student']);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $semester = Semester::factory()->create();
        $department = Department::factory()->create();
        for ($i = 0; $i < 12; $i++) {
            $course = Course::factory()->create(['department_id' => $department->id, 'access_mode' => 'independent', 'owner_user_id' => $lecturer->id, 'code' => sprintf('PERF%02d', $i), 'credit_hours' => 3]);
            Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'enrolled', 'enrolled_at' => now()->addMinutes($i)]);
            LecturerCourseAssignment::create(['user_id' => $lecturer->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'is_coordinator' => true]);
            $submission = Submission::factory()->create(['user_id' => $student->id, 'course_id' => $course->id, 'semester_id' => $semester->id, 'status' => 'graded']);
            SubmissionGrade::create(['submission_id' => $submission->id, 'user_id' => $lecturer->id, 'score' => $i === 0 ? 10 : 70, 'max_score' => 100, 'is_final' => true]);
        }
        $this->actingAs($student)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.student')
            ->assertViewHas('enrollmentCount', 12)->assertViewHas('submissionCount', 12)
            ->assertViewHas('enrollments', fn ($rows) => $rows->count() === 5)
            ->assertViewHas('courseProgress', fn ($rows) => $rows->count() === 5)
            ->assertViewHas('submissions', fn ($rows) => $rows->count() === 5)
            ->assertViewHas('cgpa', 4.58);
        $this->actingAs($lecturer)->get(route('dashboard'))->assertOk()->assertViewIs('dashboard.lecturer')
            ->assertViewHas('courseCount', 12)->assertViewHas('studentCount', 1)
            ->assertViewHas('courses', fn ($rows) => $rows->count() === 6)
            ->assertViewHas('coursePerformance', fn ($rows) => $rows->count() === 6)
            ->assertViewHas('averageClass', 65.0)->assertViewHas('submissionOverview', fn ($overview) => $overview['approved'] === 12);
    }
}
