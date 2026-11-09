<?php

namespace Tests\Feature;

use App\Console\Commands\SeedKnowledgeStarterContent;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeStarterContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_content_is_complete_and_valid(): void
    {
        $items = SeedKnowledgeStarterContent::items();
        $this->assertCount(35, $items);
        $categories = DB::table('knowledge_categories')->whereNull('university_id')->pluck('name')->all();
        $types = DB::table('knowledge_publication_types')->where('is_active', true)->pluck('slug')->all();
        $this->assertSame(array_fill_keys($categories, 7), array_count_values(array_column($items, 'category')));
        $this->assertCount(35, array_unique(array_column($items, 'title')));
        foreach ($items as $item) {
            $this->assertContains($item['type'], $types, $item['title']);
            $this->assertNotEmpty($item['excerpt']);
            $this->assertGreaterThan(150, str_word_count(strip_tags($item['body'])), $item['title']);
            $this->assertCount(4, $item['questions'], $item['title']);
            foreach ($item['questions'] as $question) {
                $this->assertCount(4, $question['options'], $question['q']);
                $this->assertArrayHasKey($question['answer'], $question['options'], $question['q']);
            }
        }
    }

    public function test_command_publishes_once_with_quiz_questions(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->artisan('knowledge:seed-starter', ['--author' => $admin->email])->assertSuccessful();
        $this->assertSame(35, KnowledgePublication::withoutGlobalScopes()->where('creator_id', $admin->id)->where('status', 'published')->where('visibility', 'public')->count());
        $this->assertSame(140, KnowledgeQuizQuestion::count());

        $this->artisan('knowledge:seed-starter', ['--author' => $admin->email])->assertSuccessful();
        $this->assertSame(35, KnowledgePublication::withoutGlobalScopes()->count());

        $publication = KnowledgePublication::withoutGlobalScopes()->where('title', 'Supply and Demand Made Simple (ECO 101)')->firstOrFail();
        $this->get(route('knowledge.show', $publication))->assertOk()->assertSee('The law of demand', false);
        $this->get(route('knowledge.index'))->assertOk();
    }

    public function test_draft_and_dry_run_options(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->artisan('knowledge:seed-starter', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, KnowledgePublication::withoutGlobalScopes()->count());
        $this->artisan('knowledge:seed-starter', ['--draft' => true])->assertSuccessful();
        $this->assertSame(35, KnowledgePublication::withoutGlobalScopes()->where('creator_id', $admin->id)->where('status', 'draft')->count());
        $this->artisan('knowledge:seed-starter', ['--author' => 'nobody@example.com'])->assertFailed();
    }
}
