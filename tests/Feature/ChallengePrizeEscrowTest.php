<?php

namespace Tests\Feature;

use App\Models\AcademicChallenge;
use App\Models\AcademicChallengeEntry;
use App\Models\ChallengePrizeFund;
use App\Models\KnowledgePublication;
use App\Models\Notification;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Commerce\WalletService;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\ChallengePrizeService;
use App\Services\Knowledge\PublicationService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ChallengePrizeEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['notifications', 'academic_challenges', 'knowledge_read_to_earn'] as $feature) {
            FeatureAccessService::setStatus($feature, 'enabled');
        }
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function wallet(User $user): WalletAccount
    {
        return app(WalletService::class)->account($user)->fresh();
    }

    private function topUp(User $user, int $naira): void
    {
        app(WalletService::class)->creditSpending($user, $naira * 100, 'wallet_funding', null, null, 'Test top-up');
    }

    private function host(User $creator, string $type = 'cash', string $prizes = '10000, 5000'): AcademicChallenge
    {
        $this->actingAs($creator)->post(route('knowledge.challenges.store'), [
            'title' => 'Best SIWES tips', 'description' => 'Share your best SIWES tips.', 'challenge_type' => 'writing', 'visibility' => 'public',
            'participation_mode' => 'individual', 'status' => 'published', 'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'submission_deadline' => now()->addDays(7)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'judging_criteria' => ['Quality'], 'prize_type' => $type, 'prizes_text' => $prizes,
        ])->assertSessionHasNoErrors();

        return AcademicChallenge::withoutGlobalScopes()->where('organizer_id', $creator->id)->latest('id')->firstOrFail();
    }

    private function entrant(AcademicChallenge $challenge, float $score): User
    {
        $user = User::factory()->create();
        AcademicChallengeEntry::create(['academic_challenge_id' => $challenge->id, 'user_id' => $user->id, 'title' => 'Entry '.$score, 'status' => 'submitted', 'is_final' => true, 'submitted_at' => now(), 'score' => $score]);

        return $user;
    }

    private function finish(AcademicChallenge $challenge, User $creator): void
    {
        $challenge->update(['status' => 'judging']);
        $this->actingAs($creator)->post(route('knowledge.challenges.results.publish', $challenge))->assertSessionHasNoErrors();
        $this->assertSame('completed', $challenge->fresh()->status);
    }

    public function test_prize_challenge_stays_draft_until_funded_with_fee(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator);
        $fund = ChallengePrizeFund::query()->where('academic_challenge_id', $challenge->id)->firstOrFail();
        $this->assertSame(['draft', 'unfunded', 1500000, 75000], [$challenge->status, $fund->status, (int) $fund->total_minor, (int) $fund->fee_minor]);

        $this->patch(route('knowledge.challenges.status', $challenge), ['status' => 'published'])->assertSessionHasErrors();
        $this->get(route('knowledge.challenges.show', $challenge))->assertOk()->assertSee('Fund your prizes to go live')->assertSee('Top up wallet');

        $this->topUp($creator, 15750);
        $this->post(route('knowledge.challenges.prizes.fund', $challenge), ['publish' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['published', 'funded'], [$challenge->fresh()->status, $fund->fresh()->status]);
        $this->assertSame(0, (int) $this->wallet($creator)->spending_balance_minor);
        $this->assertTrue(Notification::query()->where('user_id', $creator->id)->where('type', 'challenge_prize_funded')->exists());
        $this->get(route('knowledge.challenges.index'))->assertSee('Prize guaranteed')->assertSee('₦15,000');

        // Locked once funded.
        $this->put(route('knowledge.challenges.update', $challenge), [
            'title' => 'Best SIWES tips', 'description' => 'x', 'challenge_type' => 'writing', 'visibility' => 'public', 'participation_mode' => 'individual',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'), 'submission_deadline' => now()->addDays(7)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
            'judging_criteria' => ['Quality'], 'prize_type' => 'cash', 'prizes_text' => '100',
        ])->assertSessionHasErrors('prize_type');
        // Others can't fund someone else's challenge, and funded challenges can't be deleted.
        $this->actingAs(User::factory()->create())->post(route('knowledge.challenges.prizes.fund', $challenge))->assertForbidden();
        $this->actingAs($creator)->delete(route('knowledge.challenges.destroy', $challenge))->assertStatus(422);
    }

    public function test_cash_prizes_cannot_be_paid_with_study_credit(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator, 'cash', '2000');
        // Study credit from Read & Earn is spend-only, so it must not become someone's withdrawable cash.
        app(WalletService::class)->creditSpending($creator, 500000, 'knowledge_reward_credit', null, null, 'Read & Earn');
        $this->post(route('knowledge.challenges.prizes.fund', $challenge))->assertSessionHasErrors('fund');
        $this->assertSame('unfunded', $challenge->prizeFund()->value('status'));

        // The same credit can back a study-credit prize.
        $credit = $this->host($creator, 'study_credit', '2000');
        $this->post(route('knowledge.challenges.prizes.fund', $credit))->assertSessionHasNoErrors();
        $this->assertSame('funded', $credit->prizeFund()->value('status'));

        // Withdrawable earnings can back cash prizes.
        app(WalletService::class)->creditAvailableEarnings($creator, 300000, 'knowledge_reward_cash', null, 'Pool');
        $this->post(route('knowledge.challenges.prizes.fund', $challenge))->assertSessionHasNoErrors();
        $fund = $challenge->prizeFund()->first();
        $this->assertSame([210000, 0], [(int) $fund->from_earnings_minor, (int) $fund->from_spending_minor]);
    }

    public function test_creator_pays_winners_and_unused_places_are_refunded(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator, 'cash', '10000, 5000, 2000');
        $this->topUp($creator, 20000);
        $this->post(route('knowledge.challenges.prizes.fund', $challenge), ['publish' => 1])->assertSessionHasNoErrors();
        $first = $this->entrant($challenge, 90);
        $second = $this->entrant($challenge, 70);

        $this->actingAs($creator)->post(route('knowledge.challenges.prizes.pay', $challenge))->assertSessionHasErrors('fund');
        $this->finish($challenge, $creator);
        $this->actingAs($first)->post(route('knowledge.challenges.prizes.pay', $challenge))->assertForbidden();
        $this->actingAs($creator)->get(route('knowledge.challenges.show', $challenge))->assertSee('Pay winners now');
        $this->post(route('knowledge.challenges.prizes.pay', $challenge))->assertSessionHasNoErrors();

        $this->assertSame(1000000, (int) $this->wallet($first)->available_earnings_minor);
        $this->assertSame(500000, (int) $this->wallet($second)->available_earnings_minor);
        $fund = $challenge->prizeFund()->first();
        $this->assertSame(['settled', 1500000, 200000], [$fund->status, (int) $fund->disbursed_minor, (int) $fund->refunded_minor]);
        // 20,000 top-up − 17,850 charged + 2,000 unused place refunded; the 850 fee is kept.
        $this->assertSame(415000, (int) $this->wallet($creator)->spending_balance_minor);
        $this->assertTrue(Notification::query()->where('user_id', $first->id)->where('type', 'challenge_prize_won')->exists());
        $this->post(route('knowledge.challenges.prizes.pay', $challenge))->assertSessionHasErrors('fund');
    }

    public function test_study_credit_prizes_go_to_spending_balance(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator, 'study_credit', '3000');
        $this->topUp($creator, 3150);
        $this->post(route('knowledge.challenges.prizes.fund', $challenge), ['publish' => 1])->assertSessionHasNoErrors();
        $winner = $this->entrant($challenge, 80);
        $this->finish($challenge, $creator);
        $this->actingAs($creator)->post(route('knowledge.challenges.prizes.pay', $challenge))->assertSessionHasNoErrors();
        $wallet = $this->wallet($winner);
        $this->assertSame([300000, 0], [(int) $wallet->spending_balance_minor, (int) $wallet->available_earnings_minor]);
    }

    public function test_cancelling_refunds_prize_but_keeps_fee(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator, 'cash', '10000');
        $this->topUp($creator, 10500);
        $this->post(route('knowledge.challenges.prizes.fund', $challenge), ['publish' => 1])->assertSessionHasNoErrors();
        $this->patch(route('knowledge.challenges.status', $challenge), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('settled', $challenge->prizeFund()->value('status'));
        $this->assertSame(1000000, (int) $this->wallet($creator)->spending_balance_minor);
        $this->assertTrue(Notification::query()->where('user_id', $creator->id)->where('type', 'challenge_prize_refunded')->exists());
    }

    public function test_winners_are_paid_automatically_and_admin_can_settle(): void
    {
        $creator = User::factory()->create();
        $challenge = $this->host($creator, 'cash', '5000');
        $this->topUp($creator, 5250);
        $this->post(route('knowledge.challenges.prizes.fund', $challenge), ['publish' => 1])->assertSessionHasNoErrors();
        $winner = $this->entrant($challenge, 50);
        $this->finish($challenge, $creator);

        $this->artisan('challenges:auto-disburse-prizes')->assertSuccessful();
        $this->assertSame('funded', $challenge->prizeFund()->value('status'));
        $challenge->update(['results_published_at' => now()->subDays(8)]);
        $this->artisan('challenges:auto-disburse-prizes')->assertSuccessful();
        $this->assertSame('settled', $challenge->prizeFund()->value('status'));
        $this->assertSame(500000, (int) $this->wallet($winner)->available_earnings_minor);

        // Admin settle on a second, cancelled-by-status challenge.
        $other = $this->host($creator, 'cash', '1000');
        $this->topUp($creator, 1050);
        $this->post(route('knowledge.challenges.prizes.fund', $other), ['publish' => 1])->assertSessionHasNoErrors();
        $fund = $other->prizeFund()->first();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.knowledge.read-earn.index'))->assertOk()->assertSee('Best SIWES tips');
        $this->post(route('admin.knowledge.read-earn.escrow.settle', $fund))->assertSessionHasErrors('fund');
        AcademicChallenge::withoutGlobalScopes()->whereKey($other->id)->update(['status' => 'cancelled']);
        $this->post(route('admin.knowledge.read-earn.escrow.settle', $fund))->assertSessionHasNoErrors();
        $this->assertSame('settled', $fund->fresh()->status);
        $this->actingAs($creator)->post(route('admin.knowledge.read-earn.escrow.settle', $fund))->assertForbidden();
    }

    public function test_community_writing_entries_must_be_the_entrants_own_guide(): void
    {
        $creator = User::factory()->create();
        $this->actingAs($creator)->post(route('knowledge.challenges.store'), [
            'title' => 'Free writing jam', 'description' => 'No prize.', 'challenge_type' => 'writing', 'visibility' => 'public',
            'participation_mode' => 'individual', 'status' => 'published', 'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'submission_deadline' => now()->addDays(7)->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(10)->format('Y-m-d\TH:i'), 'judging_criteria' => ['Quality'],
        ])->assertSessionHasNoErrors();
        $challenge = AcademicChallenge::withoutGlobalScopes()->where('title', 'Free writing jam')->firstOrFail();
        $this->assertSame('published', $challenge->status);
        $this->assertNull($challenge->prizeFund()->first());

        $student = User::factory()->create();
        $other = User::factory()->create();
        $type = DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id');
        $make = fn (User $u, string $t) => app(PublicationService::class)->createDraft(['title' => $t, 'body' => '<p>Body text here.</p>', 'publication_type_id' => $type, 'access_type' => 'free', 'visibility' => 'public', 'excerpt' => 'x', 'ownership_declaration' => true], $u);
        $this->actingAs($student); $own = $make($student, 'My guide');
        $this->actingAs($other); $theirs = $make($other, 'Their guide');
        $this->actingAs($student)->post(route('knowledge.challenges.entries.store', $challenge), ['title' => 'Stolen', 'knowledge_publication_id' => $theirs->id, 'is_final' => 1])->assertForbidden();
        $this->post(route('knowledge.challenges.entries.store', $challenge), ['title' => 'Mine', 'knowledge_publication_id' => $own->id, 'is_final' => 1])->assertSessionHasNoErrors();
        $this->assertSame($own->id, $challenge->entries()->value('knowledge_publication_id'));
    }
}
