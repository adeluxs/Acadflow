<?php

namespace Tests\Feature;

use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeWritingChallenge;
use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\ChallengePopupService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeChallengePopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureAccessService::setStatus('knowledge_read_to_earn', 'enabled');
        FeatureAccessService::flushRequestCache();
        Cache::flush();
    }

    private function challenges(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        KnowledgeWritingChallenge::create(['uuid' => (string) Str::uuid(), 'title' => 'Cheat Codes for Campus Life', 'theme' => 'The cheat codes nobody told you',
            'starts_at' => now()->subDay(), 'submission_deadline' => now()->addDays(20), 'results_at' => now()->addDays(28),
            'prizes' => [2000000, 1200000, 800000, 500000, 500000], 'tracks' => [], 'min_words' => 600, 'min_questions' => 3, 'judge_weight' => 50, 'finalists' => 10,
            'status' => 'published', 'created_by' => $admin->id]);
        KnowledgeReadingChallenge::create(['uuid' => (string) Str::uuid(), 'title' => 'The November Read-Off', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(20),
            'prize_pool_minor' => 5000000, 'prizes' => [2000000, 1200000, 800000, 500000, 500000], 'min_passed_reads' => 10, 'status' => 'active', 'created_by' => $admin->id]);
    }

    public function test_pops_up_after_login_then_hourly_but_not_while_reading_or_writing(): void
    {
        $this->challenges();
        $user = User::factory()->create();
        $this->actingAs($user);

        // Just logged in: due straight away, listing both challenges.
        $page = $this->get(route('dashboard'))->assertOk();
        $page->assertSee('id="challenge-popup"', false)->assertSee('data-delay="0"', false)->assertSee('Cheat Codes for Campus Life')->assertSee('The November Read-Off')->assertSee('₦50,000');

        // Never while reading, writing, or on the challenge pages themselves.
        $this->get(route('knowledge.manage.create'))->assertOk()->assertDontSee('id="challenge-popup"', false);
        $this->get(route('knowledge.rewards.index'))->assertOk()->assertDontSee('id="challenge-popup"', false);

        // Once seen, the next one is an hour away; the page timer gets the remaining seconds.
        $this->post(route('knowledge.challenge-popup.seen'))->assertNoContent();
        $this->travel(20)->minutes();
        $delay = (int) str($this->get(route('dashboard'))->getContent())->between('data-delay="', '"')->toString();
        $this->assertGreaterThan(2300, $delay);
        $this->assertLessThanOrEqual(2400, $delay);
        $this->travel(41)->minutes();
        $this->get(route('dashboard'))->assertSee('data-delay="0"', false);

        // A new login shows it again straight away.
        $this->post(route('knowledge.challenge-popup.seen'));
        event(new \Illuminate\Auth\Events\Login('web', $user, false));
        $this->assertNull(session(ChallengePopupService::SESSION_KEY));
    }

    public function test_hidden_when_nothing_is_live_or_switched_off(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('id="challenge-popup"', false);

        $this->challenges();
        Cache::flush();
        SettingService::set('knowledge_challenge_popup_enabled', false, 'boolean');
        SettingService::flushRequestCache();
        $this->get(route('dashboard'))->assertDontSee('id="challenge-popup"', false);
    }
}
