<?php

namespace Tests\Feature;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\University;
use App\Models\User;
use App\Services\Knowledge\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeReaderAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function setUpGuide(): array
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);
        $publication = app(PublicationService::class)->createDraft(['title' => 'Analytics guide', 'body' => '<p>Body.</p>',
            'publication_type_id' => DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id'), 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'x', 'ownership_declaration' => true], $creator);
        KnowledgePublication::withoutGlobalScopes()->whereKey($publication->id)->update(['status' => 'published', 'published_at' => now()]);
        $question = KnowledgeQuizQuestion::create(['knowledge_publication_id' => $publication->id, 'question' => 'Hard question?', 'options' => ['a', 'b'], 'correct_option' => 0, 'source' => 'ai', 'sort_order' => 0, 'is_active' => true]);

        $big = University::factory()->create(['name' => 'Big University']);
        $small = University::factory()->create(['name' => 'Tiny College']);
        $readers = collect();
        foreach ([$big, $big, $big, $big, $small] as $i => $university) {
            $reader = User::factory()->create(['university_id' => $university->id, 'first_name' => 'Reader'.$i]);
            $readers->push($reader);
            DB::table('knowledge_publication_reads')->insert(['knowledge_publication_id' => $publication->id, 'user_id' => $reader->id, 'created_at' => now()->subDays($i)]);
            DB::table('knowledge_read_attempts')->insert(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $reader->id, 'knowledge_publication_id' => $publication->id, 'period' => now()->format('Y-m'),
                'question_count' => 1, 'correct_count' => $i < 2 ? 1 : 0, 'score_percent' => $i < 2 ? 100 : 0, 'passed' => $i < 2, 'points' => $i < 2 ? 10 : 0, 'credit_minor' => 0,
                'status' => $i < 2 ? 'counted' : 'not_counted', 'reason' => $i < 2 ? null : 'below_pass_mark', 'had_pass' => true, 'counts_for_creator' => $i < 2, 'read_seconds' => 120,
                'answers' => json_encode([$question->id => $i < 2 ? 0 : 1]), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('knowledge_publication_reads')->insert(['knowledge_publication_id' => $publication->id, 'user_id' => null, 'visitor_key' => str_repeat('a', 64), 'created_at' => now()]);
        DB::table('knowledge_publications')->where('id', $publication->id)->update(['view_count' => 6]);

        return [$creator, KnowledgePublication::withoutGlobalScopes()->find($publication->id), $readers];
    }

    public function test_creator_sees_totals_but_never_names(): void
    {
        [$creator, $publication, $readers] = $this->setUpGuide();
        $page = $this->actingAs($creator)->get(route('knowledge.manage.readers', $publication))->assertOk();
        $page->assertSee('Verified reads')->assertSee('40%')->assertSee('Big University')->assertSee('Other or not set')
            ->assertDontSee('Tiny College')->assertSee('Hard question?')->assertSee('40% right');
        foreach ($readers as $reader) {
            $page->assertDontSee($reader->email)->assertDontSee($reader->first_name.' ');
        }
        // Someone else can't open it.
        $this->actingAs(User::factory()->create())->get(route('knowledge.manage.readers', $publication))->assertForbidden();
    }

    public function test_admin_sees_each_reader_with_quiz_results(): void
    {
        [, $publication, $readers] = $this->setUpGuide();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.knowledge.readers.index'))->assertOk()->assertSee('Analytics guide');
        $page = $this->get(route('admin.knowledge.readers', $publication))->assertOk();
        $page->assertSee($readers[0]->email)->assertSee('100% passed')->assertSee('0% failed')->assertSee('Tiny College')->assertSee('below pass mark');
        $this->get(route('admin.knowledge.readers', [$publication, 'q' => $readers[4]->email]))->assertSee($readers[4]->email)->assertDontSee($readers[0]->email);
        $this->actingAs($readers[0])->get(route('admin.knowledge.readers', $publication))->assertForbidden();
    }
}
