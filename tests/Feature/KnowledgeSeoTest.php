<?php

namespace Tests\Feature;

use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KnowledgeSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function guide(string $title, string $access = 'free', string $visibility = 'public', bool $publish = true): KnowledgePublication
    {
        $creator = User::factory()->create();
        $this->actingAs($creator);
        $publication = app(PublicationService::class)->createDraft(['title' => $title, 'body' => '<p>How to read smart before exams. '.str_repeat('Plan your week. ', 30).'</p>',
            'publication_type_id' => DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id'), 'access_type' => 'free',
            'visibility' => 'public', 'excerpt' => 'A short summary of '.$title.'.', 'ownership_declaration' => true], $creator);
        // Premium/institution access needs creator eligibility checks we don't need here; set it directly.
        KnowledgePublication::withoutGlobalScopes()->whereKey($publication->id)->update(['visibility' => $visibility, 'access_type' => $access, 'price' => $access === 'premium' ? 500 : 0]
            + ($publish ? ['status' => 'published', 'published_at' => now()] : []));
        auth()->logout();

        return KnowledgePublication::withoutGlobalScopes()->findOrFail($publication->id);
    }

    public function test_guides_have_full_search_and_social_metadata(): void
    {
        $guide = $this->guide('Exam Week Survival');
        $page = $this->get(route('knowledge.show', $guide))->assertOk();
        $page->assertSee('<meta name="description" content="A short summary of Exam Week Survival."', false)
            ->assertSee('<link rel="canonical" href="'.route('knowledge.show', $guide).'"', false)
            ->assertSee('<meta property="og:type" content="article"', false)
            ->assertSee('twitter:card', false)
            ->assertSee('"@type":"Article"', false)
            ->assertSee('"isAccessibleForFree":true', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('index, follow, max-image-preview:large', false);

        $premium = $this->guide('Paid Exam Pack', 'premium');
        $this->get(route('knowledge.show', $premium))->assertOk()->assertSee('"isAccessibleForFree":false', false);

        // Search results are noindex; the hub itself is indexable. App pages stay out of search.
        $this->get(route('knowledge.index'))->assertSee('"@type":"CollectionPage"', false)->assertDontSee('noindex', false);
        $this->get(route('knowledge.index', ['q' => 'exam']))->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_sitemap_index_lists_public_guides_only(): void
    {
        $public = $this->guide('Public Guide');
        $draft = $this->guide('Draft Guide', 'free', 'public', false);
        $institution = $this->guide('Campus Only Guide', 'institution');

        $index = $this->get(route('public.sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString(route('sitemap.part', 'knowledge-1'), $index);
        $this->assertStringContainsString(route('sitemap.part', 'pages'), $index);

        $guides = $this->get(route('sitemap.part', 'knowledge-1'))->assertOk()->getContent();
        $this->assertStringContainsString(route('knowledge.show', $public), $guides);
        $this->assertStringContainsString('<lastmod>', $guides);
        $this->assertStringNotContainsString($draft->slug, $guides);
        $this->assertStringNotContainsString($institution->slug, $guides);
        $this->get(route('sitemap.part', 'knowledge-collections'))->assertOk()->assertSee(route('knowledge.writing.index'), false);
        $this->get(route('sitemap.part', 'pages'))->assertOk()->assertSee(route('home'), false);
        $this->get(route('sitemap.part', 'knowledge-99'))->assertNotFound();
    }

    public function test_robots_llms_and_indexnow(): void
    {
        $this->guide('Free Guide For AI');
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('User-agent: GPTBot', $robots);
        $this->assertStringContainsString('Disallow: /admin/', $robots);
        $this->assertStringContainsString('Sitemap: '.route('public.sitemap'), $robots);
        $this->assertStringNotContainsString("User-agent: ClaudeBot\nDisallow: /\n", $robots);

        $this->get('/llms.txt')->assertOk()->assertSee('# AcadSyntra', false)->assertSee('Free Guide For AI', false);

        SettingService::set('seo_allow_ai_crawlers', false, 'boolean');
        SettingService::flushRequestCache();
        $this->assertStringContainsString("Applebot-Extended\nUser-agent: CCBot\nDisallow: /", $this->get('/robots.txt')->getContent());

        $key = \App\Http\Controllers\AiDiscoveryController::indexNowKey();
        $this->get('/'.$key.'.txt')->assertOk()->assertSee($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();

        Queue::fake();
        $guide = $this->guide('Moderated Guide', 'free', 'public', false);
        DB::table('knowledge_publications')->where('id', $guide->id)->update(['status' => 'pending_review']);
        app(\App\Services\Knowledge\ModerationService::class)->publish($guide->fresh(), User::factory()->create(['role' => 'super_admin']));
        Queue::assertPushed(\App\Jobs\PingIndexNow::class, fn ($job) => $job->urls === [route('knowledge.show', $guide->fresh())]);
    }
}
