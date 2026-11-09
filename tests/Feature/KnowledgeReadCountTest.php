<?php

namespace Tests\Feature;

use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\PublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KnowledgeReadCountTest extends TestCase
{
    use RefreshDatabase;

    private function guide(User $creator): KnowledgePublication
    {
        $this->actingAs($creator);
        $publication = app(PublicationService::class)->createDraft(['title' => 'Count me once', 'body' => '<p>Body text here.</p>',
            'publication_type_id' => DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id'), 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'x', 'ownership_declaration' => true], $creator);
        KnowledgePublication::withoutGlobalScopes()->whereKey($publication->id)->update(['status' => 'published', 'published_at' => now()]);
        auth()->logout();

        return KnowledgePublication::withoutGlobalScopes()->findOrFail($publication->id);
    }

    private function reads(KnowledgePublication $publication): int
    {
        return (int) DB::table('knowledge_publications')->where('id', $publication->id)->value('view_count');
    }

    public function test_a_reader_counts_once_however_often_they_refresh_save_or_follow(): void
    {
        FeatureAccessService::setStatus('knowledge_read_to_earn', 'enabled');
        FeatureAccessService::flushRequestCache();
        $creator = User::factory()->create();
        $publication = $this->guide($creator);
        $reader = User::factory()->create();

        $this->actingAs($reader);
        $this->get(route('knowledge.show', $publication))->assertOk();
        $this->get(route('knowledge.show', $publication))->assertOk();
        $this->from(route('knowledge.show', $publication))->post(route('knowledge.manage.bookmark', $publication))->assertRedirect();
        $this->get(route('knowledge.show', $publication))->assertOk();
        $this->from(route('knowledge.show', $publication))->post(route('knowledge.creators.follow', $creator))->assertRedirect();
        $this->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(1, $this->reads($publication));

        $this->actingAs(User::factory()->create())->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(2, $this->reads($publication));
        $this->assertSame(2, DB::table('knowledge_events')->where('knowledge_publication_id', $publication->id)->where('event_type', 'view')->count());
    }

    public function test_a_visitor_counts_once_per_browser_and_not_again_after_signing_in(): void
    {
        $publication = $this->guide(User::factory()->create());
        $first = $this->get(route('knowledge.show', $publication))->assertOk();
        $this->assertNotNull(collect($first->headers->getCookies())->first(fn ($c) => $c->getName() === 'acad_reader'));
        $this->assertSame(1, $this->reads($publication));
        $key = str_repeat('k', 40);
        $this->withCookie('acad_reader', $key)->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(2, $this->reads($publication));
        $this->withCookie('acad_reader', $key)->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(2, $this->reads($publication));

        // Same browser, now signed in: not counted again.
        $this->actingAs(User::factory()->create())->withCookie('acad_reader', $key)->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(2, $this->reads($publication));
    }

    public function test_drafts_do_not_collect_reads_and_old_inflated_counts_can_be_repaired(): void
    {
        $creator = User::factory()->create();
        $publication = $this->guide($creator);
        $reader = User::factory()->create();
        DB::table('knowledge_publications')->where('id', $publication->id)->update(['view_count' => 37]);
        foreach (range(1, 5) as $i) {
            DB::table('knowledge_events')->insert(['knowledge_publication_id' => $publication->id, 'user_id' => $reader->id, 'event_type' => 'view', 'created_at' => now()]);
        }
        DB::table('knowledge_events')->insert(['knowledge_publication_id' => $publication->id, 'user_id' => null, 'event_type' => 'view', 'created_at' => now()]);

        $this->artisan('knowledge:recount-reads')->assertSuccessful();
        $this->assertSame(37, $this->reads($publication));
        $this->artisan('knowledge:recount-reads', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, $this->reads($publication));
        $this->actingAs($reader)->get(route('knowledge.show', $publication))->assertOk();
        $this->assertSame(1, $this->reads($publication));

        KnowledgePublication::withoutGlobalScopes()->whereKey($publication->id)->update(['status' => 'draft']);
        $this->assertFalse(app(\App\Services\KnowledgeDiscoveryService::class)->recordView($publication->fresh(), User::factory()->create(), [], 'abc'));
    }
}
