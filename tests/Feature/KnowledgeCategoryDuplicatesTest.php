<?php

namespace Tests\Feature;

use App\Models\KnowledgeCategory;
use App\Models\University;
use App\Models\User;
use App\Services\Knowledge\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeCategoryDuplicatesTest extends TestCase
{
    use RefreshDatabase;

    /** The old starter setup gave every university its own copy of the same categories. */
    private function seedPerUniversityCopies(int $universities = 3): array
    {
        $ids = [];
        foreach (University::factory()->count($universities)->create() as $university) {
            foreach (['Campus and Department Guides', 'Exam Preparation'] as $name) {
                $ids[$name][] = KnowledgeCategory::create(['university_id' => $university->id, 'name' => $name, 'slug' => str($name)->slug()->toString(), 'is_active' => true])->id;
            }
        }

        return $ids;
    }

    public function test_admin_sees_each_category_once_and_can_merge_university_copies(): void
    {
        $ids = $this->seedPerUniversityCopies();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $creator = User::factory()->create();
        $this->actingAs($creator);
        $publication = app(PublicationService::class)->createDraft(['title' => 'On a copy', 'body' => '<p>Body text.</p>',
            'publication_type_id' => DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id'), 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'x', 'ownership_declaration' => true], $creator);
        DB::table('knowledge_publications')->where('id', $publication->id)->update(['category_id' => $ids['Exam Preparation'][2]]);

        $page = $this->actingAs($admin)->get(route('admin.knowledge.index'))->assertOk()->assertSee('4 duplicate copies found')->getContent();
        $this->assertSame(0, substr_count($page, 'Campus and Department Guides</span>'));

        $this->post(route('admin.knowledge.categories.merge'))->assertSessionHasNoErrors();
        $page = $this->get(route('admin.knowledge.index'))->assertOk()->assertDontSee('duplicate copies found')->getContent();
        $this->assertSame(1, substr_count($page, '>Campus and Department Guides</span>'));
        $this->assertSame(1, substr_count($page, '>Exam Preparation</span>'));

        $kept = KnowledgeCategory::query()->whereNull('merged_into_id')->where('name', 'Exam Preparation')->sole();
        $this->assertNull($kept->university_id);
        $this->assertSame($kept->id, (int) DB::table('knowledge_publications')->where('id', $publication->id)->value('category_id'));
        $this->assertSame(4, KnowledgeCategory::query()->whereNotNull('merged_into_id')->count());
        $this->assertSame(6, KnowledgeCategory::query()->whereIn('id', array_merge(...array_values($ids)))->count());

        // Every user, at any university, sees one of each.
        foreach ([null, University::query()->value('id')] as $universityId) {
            $names = KnowledgeCategory::picker($universityId)->pluck('name');
            $this->assertSame($names->unique()->count(), $names->count());
            $this->assertSame(1, $names->filter(fn ($n) => $n === 'Exam Preparation')->count());
        }
    }

    public function test_platform_wide_duplicates_merge_by_command_and_cannot_be_recreated(): void
    {
        $base = KnowledgeCategory::query()->whereNull('university_id')->where('slug', 'academics-research')->firstOrFail();
        $copy = KnowledgeCategory::create(['university_id' => null, 'name' => 'Academics & Research ', 'slug' => 'academics-research', 'area' => 'academics_research', 'is_active' => true]);
        $this->assertSame(1, KnowledgeCategory::picker(null)->filter(fn ($c) => trim($c->name) === 'Academics & Research')->count());

        $this->artisan('knowledge:merge-duplicate-categories')->assertSuccessful();
        $this->assertTrue($copy->fresh()->is_active);
        $this->artisan('knowledge:merge-duplicate-categories', ['--apply' => true])->assertSuccessful();
        $this->assertSame([false, $base->id], [$copy->fresh()->is_active, (int) $copy->fresh()->merged_into_id]);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.knowledge.categories.store'), ['name' => 'Academics & Research', 'area' => 'academics_research', 'sort_order' => 1])->assertSessionHasErrors('name');
        $this->actingAs(User::factory()->create())->get(route('knowledge.index'))->assertOk();
        $this->assertSame(1, substr_count($this->get(route('knowledge.index'))->getContent(), '>Academics &amp; Research</option>'));
    }

    public function test_topics_are_renamed_so_they_do_not_repeat_formats(): void
    {
        $old = KnowledgeCategory::create(['university_id' => null, 'name' => 'Exam Preparation', 'slug' => 'exam-preparation', 'is_active' => true]);
        $custom = KnowledgeCategory::create(['university_id' => null, 'name' => 'My Own Topic', 'slug' => 'tutorials', 'is_active' => true]);
        $migration = require database_path('migrations/2026_11_09_100000_rename_overlapping_knowledge_categories.php');
        $migration->up();
        $this->assertSame(['Exams & Assessments', 'exam-preparation'], [$old->fresh()->name, $old->fresh()->slug]);
        $this->assertSame('My Own Topic', $custom->fresh()->name);
        $migration->down();
        $this->assertSame('Exam Preparation', $old->fresh()->name);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('knowledge.manage.create'))->assertOk()->assertSee('what are you sharing?')->assertSee('what is it about?');
        $this->get(route('knowledge.index'))->assertSee('All topics')->assertSee('All formats');

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.knowledge.categories.store'), ['name' => 'Study guides', 'area' => 'academics_research', 'sort_order' => 1])->assertSessionHasErrors('name');
        $this->post(route('admin.knowledge.categories.store'), ['name' => 'Exams & Assessments', 'area' => 'academics_research', 'sort_order' => 1])->assertSessionHasNoErrors();
    }
}
