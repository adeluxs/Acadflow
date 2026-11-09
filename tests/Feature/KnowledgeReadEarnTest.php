<?php

namespace Tests\Feature;

use App\Models\AcademicChallenge;
use App\Models\CreatorMonetizationApplication;
use App\Models\KnowledgePublication;
use App\Models\KnowledgeQuizQuestion;
use App\Models\KnowledgeReadAttempt;
use App\Models\KnowledgeReadingChallenge;
use App\Models\KnowledgeRewardPayout;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Commerce\WalletService;
use App\Services\FeatureAccessService;
use App\Services\Knowledge\PublicationService;
use App\Services\Knowledge\RewardPoolService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeReadEarnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flush();
        FeatureAccessService::setStatus('knowledge_read_to_earn', 'enabled');
        $this->flush();
    }

    private function flush(): void
    {
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function setting(string $key, mixed $value, string $type): void
    {
        SettingService::set($key, $value, $type);
        $this->flush();
    }

    private function body(): string
    {
        return '<p>'.trim(str_repeat('Normalisation removes redundancy by splitting tables so every fact lives in one place. ', 12)).'</p>';
    }

    private function publication(User $creator, string $title, int $questions = 3): KnowledgePublication
    {
        $this->actingAs($creator);
        $type = DB::table('knowledge_publication_types')->where('slug', 'study_guide')->value('id') ?? DB::table('knowledge_publication_types')->where('is_active', true)->value('id');
        $publication = app(PublicationService::class)->createDraft(['title' => $title, 'body' => $this->body(), 'publication_type_id' => $type,
            'access_type' => 'free', 'visibility' => 'public', 'ownership_declaration' => true], $creator);
        $publication->forceFill(['status' => 'published', 'visibility' => 'public', 'published_at' => now()])->save();
        $publication->document?->forceFill(['status' => 'published', 'visibility' => 'public'])->save();
        for ($i = 0; $i < $questions; $i++) {
            KnowledgeQuizQuestion::create(['knowledge_publication_id' => $publication->id, 'question' => 'Question '.$i, 'options' => ['Right', 'Wrong'], 'correct_option' => 0, 'sort_order' => $i]);
        }
        auth()->logout();

        return $publication->fresh();
    }

    private function answers(KnowledgePublication $publication, int $wrong = 0): array
    {
        // Each reader sees their own shuffled draw, so answer by the option position they were shown.
        $answers = [];
        foreach (app(\App\Services\Knowledge\ReadToEarnService::class)->drawFor(auth()->user(), $publication) as $index => $question) {
            $right = array_search($question->correct_option, $question->display_order, true);
            $answers[$question->id] = $index < $wrong ? ($right === 0 ? 1 : 0) : $right;
        }

        return $answers;
    }

    private function buyPass(User $user): void
    {
        app(WalletService::class)->creditSpending($user, 100000, 'wallet_fund_test');
        $this->actingAs($user)->post(route('commerce.features.purchase'), ['feature' => 'knowledge_reader_pass', 'billing_period' => 'monthly', 'request_id' => (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    public function test_free_reads_then_prompt_then_paywall_then_pass_unlocks(): void
    {
        $this->setting('knowledge_reader_pass_enabled', true, 'boolean');
        $creator = User::factory()->create();
        $pubs = collect(range(1, 4))->map(fn ($i) => $this->publication($creator, 'Guide '.$i));
        $reader = User::factory()->create();

        $this->actingAs($reader)->get(route('knowledge.show', $pubs[0]))->assertOk()->assertSee('Normalisation removes redundancy')->assertDontSee('Read more, and get rewarded');
        $this->get(route('knowledge.show', $pubs[1]))->assertOk()->assertSee('Read more, and get rewarded');
        $this->get(route('knowledge.show', $pubs[2]))->assertOk()->assertSee('Normalisation removes redundancy')->assertDontSee('Read more, and get rewarded');
        $this->get(route('knowledge.show', $pubs[3]))->assertOk()->assertSee('used your 3 free reads')->assertDontSee('Normalisation removes redundancy');
        // Guides already opened this month stay open.
        $this->get(route('knowledge.show', $pubs[0]))->assertOk()->assertSee('Normalisation removes redundancy');

        $this->buyPass($reader);
        $this->assertSame(100000 - 50000, (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor'));
        $this->get(route('knowledge.show', $pubs[3]))->assertOk()->assertSee('Normalisation removes redundancy');
    }

    public function test_nothing_is_metered_while_the_pass_setting_or_feature_is_off(): void
    {
        $creator = User::factory()->create();
        $pubs = collect(range(1, 5))->map(fn ($i) => $this->publication($creator, 'Open guide '.$i));
        $reader = User::factory()->create();
        foreach ($pubs as $publication) {
            $this->actingAs($reader)->get(route('knowledge.show', $publication))->assertOk()->assertSee('Normalisation removes redundancy');
        }

        $this->setting('knowledge_reader_pass_enabled', true, 'boolean');
        FeatureAccessService::setStatus('knowledge_read_to_earn', 'disabled');
        $this->flush();
        $this->actingAs(User::factory()->create());
        foreach ($pubs as $publication) {
            $this->get(route('knowledge.show', $publication))->assertOk()->assertSee('Normalisation removes redundancy')->assertDontSee('What did you learn?');
        }
    }

    public function test_passing_the_quiz_earns_points_and_capped_study_credit_once(): void
    {
        $creator = User::factory()->create();
        $publication = $this->publication($creator, 'Earning guide');
        $reader = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->buyPass($reader);
        $before = (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor');

        $this->actingAs($reader)->get(route('knowledge.show', $publication))->assertOk()->assertSee('What did you learn?');
        $this->travel(3)->minutes();
        $reflection = 'I learned that normalisation removes repeated data so that updates happen in one place and the database stays consistent for every report we run.';
        $this->post(route('knowledge.rewards.quiz.submit', $publication), ['answers' => $this->answers($publication), 'reflection' => $reflection])
            ->assertRedirect()->assertSessionHas('success');

        $attempt = KnowledgeReadAttempt::firstOrFail();
        $this->assertSame('counted', $attempt->status);
        $this->assertSame(100, $attempt->score_percent);
        $this->assertSame(12, $attempt->points); // 10 for a perfect score + 2 reflection bonus
        $this->assertSame(1200, $attempt->credit_minor); // NGN 1 per point
        $this->assertTrue($attempt->counts_for_creator);
        $this->assertSame($before + 1200, (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor'));

        $this->post(route('knowledge.rewards.quiz.submit', $publication), ['answers' => $this->answers($publication)])->assertSessionHasErrors('answers');
        $this->assertSame(1, KnowledgeReadAttempt::count());
        $this->get(route('knowledge.show', $publication))->assertSee('You scored 100%');
        $this->get(route('knowledge.rewards.index'))->assertOk()->assertSee('Points this month')->assertSee('12');

        // Monthly credit cap.
        $this->setting('knowledge_read_earn_monthly_credit_cap', '15', 'decimal');
        $second = $this->publication($creator, 'Second earning guide');
        $this->actingAs($reader)->get(route('knowledge.show', $second));
        $this->travel(3)->minutes();
        $this->post(route('knowledge.rewards.quiz.submit', $second), ['answers' => $this->answers($second)]);
        $this->assertSame(300, KnowledgeReadAttempt::where('knowledge_publication_id', $second->id)->value('credit_minor'));
    }

    public function test_readers_without_a_pass_can_answer_and_collect_saved_points_with_a_pass(): void
    {
        $creator = User::factory()->create();
        $reader = User::factory()->create(['created_at' => now()->subDays(30)]);
        $first = $this->publication($creator, 'First guide');
        $late = $this->publication($creator, 'Old guide');

        // First read: the questions are open straight away, no pay button in their place.
        $this->actingAs($reader)->get(route('knowledge.show', $first))->assertOk()->assertSee('Submit answers')->assertSee('No Reader Pass yet? Answer anyway')->assertDontSee('Earning is for Reader Pass holders');
        $this->get(route('knowledge.show', $late));
        $this->travel(3)->minutes();
        $this->post(route('knowledge.rewards.quiz.submit', $late), ['answers' => $this->answers($late)])->assertSessionHas('success');
        $this->travel(8)->days();
        $this->get(route('knowledge.show', $first));
        $this->travel(3)->minutes();
        $this->post(route('knowledge.rewards.quiz.submit', $first), ['answers' => $this->answers($first)])->assertSessionHas('success');

        $saved = KnowledgeReadAttempt::where('knowledge_publication_id', $first->id)->firstOrFail();
        $this->assertSame(['held', 10, 0], [$saved->status, $saved->points, $saved->credit_minor]);
        $this->get(route('knowledge.show', $first))->assertSee('+10 points saved for you');
        $this->get(route('knowledge.rewards.index'))->assertOk()->assertSee('10 points saved for you');
        $this->assertSame(0, (int) KnowledgeReadAttempt::where('status', 'counted')->count());

        // Getting the pass pays the saved points; the ones saved more than 7 days ago have expired.
        $this->buyPass($reader);
        $before = (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor');
        $this->get(route('knowledge.rewards.index'))->assertOk();
        $this->assertSame(['counted', 1000], [$saved->fresh()->status, $saved->fresh()->credit_minor]);
        $this->assertSame($before + 1000, (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor'));
        $this->assertSame(['not_counted', 'pass_too_late', 0], [KnowledgeReadAttempt::where('knowledge_publication_id', $late->id)->value('status'), KnowledgeReadAttempt::where('knowledge_publication_id', $late->id)->value('reason'), (int) KnowledgeReadAttempt::where('knowledge_publication_id', $late->id)->value('points')]);
        // Paid once only.
        $this->get(route('knowledge.rewards.index'));
        $this->assertSame($before + 1000, (int) WalletAccount::where('user_id', $reader->id)->value('spending_balance_minor'));
    }

    public function test_reads_that_do_not_qualify_earn_nothing(): void
    {
        $creator = User::factory()->create();
        $reader = User::factory()->create(['created_at' => now()->subDays(30)]);

        // No Reader Pass and saving points switched off: the quiz still opens, but earns nothing.
        $this->setting('knowledge_read_earn_hold_days', 0, 'integer');
        $noPass = $this->publication($creator, 'No pass guide');
        $this->actingAs($reader)->get(route('knowledge.show', $noPass))->assertSee('Submit answers')->assertSee('Points and study credit are for Reader Pass holders');
        $this->travel(3)->minutes();
        $this->post(route('knowledge.rewards.quiz.submit', $noPass), ['answers' => $this->answers($noPass)]);
        $this->assertSame('no_reader_pass', KnowledgeReadAttempt::where('knowledge_publication_id', $noPass->id)->value('reason'));
        $this->setting('knowledge_read_earn_hold_days', 7, 'integer');

        $this->buyPass($reader);
        // Too fast.
        $fast = $this->publication($creator, 'Fast guide');
        $this->actingAs($reader)->get(route('knowledge.show', $fast));
        $this->post(route('knowledge.rewards.quiz.submit', $fast), ['answers' => $this->answers($fast)]);
        $this->assertSame('too_fast', KnowledgeReadAttempt::where('knowledge_publication_id', $fast->id)->value('reason'));

        // Below the pass mark (1 of 3 correct = 33%).
        $hard = $this->publication($creator, 'Hard guide');
        $this->actingAs($reader)->get(route('knowledge.show', $hard));
        $this->travel(3)->minutes();
        $this->post(route('knowledge.rewards.quiz.submit', $hard), ['answers' => $this->answers($hard, 2)]);
        $failed = KnowledgeReadAttempt::where('knowledge_publication_id', $hard->id)->firstOrFail();
        $this->assertSame([33, 'below_pass_mark', 0], [$failed->score_percent, $failed->reason, $failed->points]);

        // Creators cannot quiz their own guide; incomplete quizzes are rejected.
        $this->actingAs($creator)->post(route('knowledge.rewards.quiz.submit', $hard), ['answers' => $this->answers($hard)])->assertSessionHasErrors('answers');
        $short = $this->publication($creator, 'Two question guide', 2);
        $this->actingAs($reader)->post(route('knowledge.rewards.quiz.submit', $short), ['answers' => $this->answers($short)])->assertSessionHasErrors('answers');
        $this->assertSame(0, (int) KnowledgeReadAttempt::where('status', 'counted')->sum('credit_minor'));
    }

    public function test_daily_caps_and_shared_networks_are_held_back(): void
    {
        $this->setting('knowledge_read_earn_daily_cap', 2, 'integer');
        $this->setting('knowledge_read_earn_creator_daily_cap', 5, 'integer');
        $creator = User::factory()->create();
        $reader = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->buyPass($reader);
        $pubs = collect(range(1, 3))->map(fn ($i) => $this->publication($creator, 'Cap guide '.$i));
        foreach ($pubs as $publication) {
            $this->actingAs($reader)->get(route('knowledge.show', $publication));
        }
        $this->travel(3)->minutes();
        foreach ($pubs as $publication) {
            $this->actingAs($reader)->post(route('knowledge.rewards.quiz.submit', $publication), ['answers' => $this->answers($publication)]);
        }
        $this->assertSame(['counted', 'counted', 'not_counted'], KnowledgeReadAttempt::orderBy('id')->pluck('status')->all());
        $this->assertSame('daily_limit', KnowledgeReadAttempt::orderByDesc('id')->value('reason'));

        // Four accounts on one network: the fourth is held for review, then approved by an admin.
        KnowledgeReadAttempt::query()->delete();
        $this->setting('knowledge_read_earn_daily_cap', 5, 'integer');
        $this->setting('knowledge_read_earn_max_accounts_per_ip', 3, 'integer');
        $shared = $this->publication($creator, 'Shared network guide');
        $readers = collect(range(1, 4))->map(function () { $user = User::factory()->create(['created_at' => now()->subDays(30)]); $this->buyPass($user); return $user; });
        foreach ($readers as $user) {
            $this->actingAs($user)->get(route('knowledge.show', $shared));
        }
        $this->travel(3)->minutes();
        foreach ($readers as $user) {
            $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '102.89.1.1'])->post(route('knowledge.rewards.quiz.submit', $shared), ['answers' => $this->answers($shared)]);
        }
        $flagged = KnowledgeReadAttempt::where('status', 'flagged')->firstOrFail();
        $this->assertSame($readers[3]->id, $flagged->user_id);
        $this->assertSame(0, $flagged->credit_minor);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.knowledge.read-earn.attempts.review', $flagged), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(['counted', 1000], [$flagged->fresh()->status, $flagged->fresh()->credit_minor]);
    }

    private function attempt(User $reader, User $creator, string $period, int $points, int $score = 100, array $extra = []): KnowledgeReadAttempt
    {
        $publication = KnowledgePublication::query()->where('creator_id', $creator->id)->whereDoesntHave('readAttempts', fn ($q) => $q->where('user_id', $reader->id))->first()
            ?? $this->publication($creator, 'Pool guide '.Str::random(6), 0);

        $attempt = new KnowledgeReadAttempt;
        $attempt->forceFill($extra + [
            'user_id' => $reader->id, 'knowledge_publication_id' => $publication->id, 'creator_id' => $creator->id, 'period' => $period,
            'question_count' => 3, 'correct_count' => 3, 'score_percent' => $score, 'passed' => true, 'points' => $points, 'had_pass' => true,
            'counts_for_creator' => true, 'status' => 'counted', 'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(5),
        ])->save();

        return $attempt;
    }

    public function test_reader_pool_is_shared_by_points_within_the_budget_and_paid_once(): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $this->setting('knowledge_reader_pool_min_passed_reads', 2, 'integer');
        $this->setting('knowledge_reader_pool_min_creators', 2, 'integer');
        $this->setting('knowledge_reader_pool_max_share', 50, 'integer');
        $creators = User::factory()->count(2)->create();
        $old = ['created_at' => now()->subDays(90)];
        [$big, $small, $newbie, $oneCreator] = [User::factory()->create($old), User::factory()->create($old), User::factory()->create(), User::factory()->create($old)];
        foreach ($creators as $creator) {
            $this->attempt($big, $creator, $period, 40);
            $this->attempt($small, $creator, $period, 10);
            $this->attempt($newbie, $creator, $period, 10);
        }
        $this->attempt($oneCreator, $creators[0], $period, 10);
        $this->attempt($oneCreator, $creators[0], $period, 10);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.pools.calculate', 'reader_pool'), ['period' => $period])->assertSessionHasNoErrors();
        $drafts = KnowledgeRewardPayout::where('scope_key', 'reader_pool:'.$period)->get()->keyBy('user_id');
        $this->assertEqualsCanonicalizing([$big->id, $small->id], $drafts->keys()->all());
        // 80 vs 20 points of NGN 50,000 with a 50% cap: the big reader is capped at 25,000.
        $this->assertSame(2500000, $drafts[$big->id]->amount_minor);
        $this->assertSame(2500000, $drafts[$small->id]->amount_minor);

        $this->setting('knowledge_reader_pool_max_share', 100, 'integer');
        $this->actingAs($admin)->patch(route('admin.knowledge.read-earn.payouts.exclude', $drafts[$small->id]), ['excluded' => 1, 'note' => 'Duplicate device']);
        $this->post(route('admin.knowledge.read-earn.pools.calculate', 'reader_pool'), ['period' => $period]);
        $this->assertSame(5000000, KnowledgeRewardPayout::where('scope_key', 'reader_pool:'.$period)->where('user_id', $big->id)->value('amount_minor'));

        $this->post(route('admin.knowledge.read-earn.pay'), ['scope_key' => 'reader_pool:'.$period])->assertSessionHasNoErrors();
        $this->post(route('admin.knowledge.read-earn.pay'), ['scope_key' => 'reader_pool:'.$period]);
        $this->assertSame(5000000, (int) WalletAccount::where('user_id', $big->id)->value('available_earnings_minor'));
        $this->assertSame(0, (int) WalletAccount::where('user_id', $small->id)->value('available_earnings_minor'));
        $this->assertSame('paid', KnowledgeRewardPayout::where('user_id', $big->id)->value('status'));

        // The month must be over before a pool is calculated.
        $this->post(route('admin.knowledge.read-earn.pools.calculate', 'reader_pool'), ['period' => now()->format('Y-m')])->assertSessionHasErrors('period');
    }

    public function test_creator_pool_needs_the_verified_read_target_and_approval(): void
    {
        $period = now()->subMonthNoOverflow()->format('Y-m');
        $this->setting('knowledge_creator_pool_min_verified_reads', 3, 'integer');
        [$approved, $unapproved, $tooFew] = User::factory()->count(3)->create()->all();
        foreach ([$approved, $tooFew] as $creator) {
            CreatorMonetizationApplication::create(['user_id' => $creator->id, 'status' => 'approved', 'statement' => 'I publish my own original study guides for students.', 'ownership_declaration' => true, 'terms_accepted' => true, 'submitted_at' => now(), 'reviewed_at' => now()]);
        }
        foreach (range(1, 3) as $i) {
            $reader = User::factory()->create(['created_at' => now()->subDays(90)]);
            $this->attempt($reader, $approved, $period, 10);
            $this->attempt($reader, $unapproved, $period, 10);
            if ($i < 3) $this->attempt($reader, $tooFew, $period, 10);
        }
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.pools.calculate', 'creator_pool'), ['period' => $period])->assertSessionHasNoErrors();
        $rows = KnowledgeRewardPayout::where('scope_key', 'creator_pool:'.$period)->get();
        $this->assertSame([$approved->id], $rows->pluck('user_id')->all());
        $this->assertSame(1000000, $rows->first()->amount_minor); // capped at 20% of NGN 50,000
    }

    public function test_reading_challenge_prizes(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $creator = User::factory()->create();
        $start = now()->subDays(10);
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.challenges.store'), [
            'title' => 'November Reading Challenge', 'starts_at' => $start->format('Y-m-d H:i'), 'ends_at' => now()->subDay()->format('Y-m-d H:i'),
            'prizes' => '30000, 20000', 'min_passed_reads' => 2, 'status' => 'active',
        ])->assertSessionHasNoErrors();
        $challenge = KnowledgeReadingChallenge::firstOrFail();
        $this->assertSame([3000000, 2000000], $challenge->prizes);

        [$first, $second, $third] = User::factory()->count(3)->create()->all();
        $at = ['created_at' => $start->copy()->addDays(2)];
        foreach ([[$first, 30], [$second, 20], [$third, 10]] as [$reader, $points]) {
            $this->attempt($reader, $creator, now()->format('Y-m'), $points, 100, $at);
            $this->attempt($reader, $creator, now()->format('Y-m'), $points, 100, $at);
        }
        $this->actingAs($first)->get(route('knowledge.rewards.challenges.show', $challenge))->assertOk()->assertSee('November Reading Challenge');
        $this->actingAs($admin)->post(route('admin.knowledge.read-earn.challenges.calculate', $challenge))->assertSessionHasNoErrors();
        $this->assertSame([$first->id => 3000000, $second->id => 2000000], KnowledgeRewardPayout::where('scope_key', $challenge->scopeKey())->orderBy('rank')->pluck('amount_minor', 'user_id')->all());
        $this->post(route('admin.knowledge.read-earn.pay'), ['scope_key' => $challenge->scopeKey()])->assertSessionHasNoErrors();
        $this->assertSame(3000000, (int) WalletAccount::where('user_id', $first->id)->value('available_earnings_minor'));

    }

    public function test_admin_controls_and_creator_question_editor(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('admin.knowledge.read-earn.index'))->assertForbidden();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.knowledge.read-earn.index'))->assertOk()->assertSee('Rules and budgets')->assertSee('Monthly reader pool (NGN)');
        $payload = [];
        foreach (\App\Services\Knowledge\ReadEarnSettings::DEFINITIONS as $key => [$type, $default]) {
            $payload[$key] = $type === 'boolean' ? ($default ? 1 : 0) : $default;
        }
        $payload['knowledge_reader_pool_amount'] = '75000';
        $payload['knowledge_reader_pass_enabled'] = 1;
        $this->put(route('admin.knowledge.read-earn.settings'), $payload)->assertSessionHasNoErrors();
        $this->flush();
        $this->assertEquals(75000, (float) SettingService::getGlobal('knowledge_reader_pool_amount'));
        $this->assertTrue((bool) SettingService::getGlobal('knowledge_reader_pass_enabled'));

        $creator = User::factory()->create();
        $publication = $this->publication($creator, 'Question editor guide', 0);
        $this->actingAs($student)->get(route('knowledge.rewards.questions.edit', $publication))->assertForbidden();
        $this->actingAs($creator)->get(route('knowledge.rewards.questions.edit', $publication))->assertOk();
        $this->put(route('knowledge.rewards.questions.update', $publication), ['questions' => [
            ['question' => 'What does 1NF remove?', 'options' => ['Repeating groups', '', 'Foreign keys', ''], 'correct' => 0],
            ['question' => 'Which form removes partial dependencies?', 'options' => ['1NF', '', '2NF', '3NF'], 'correct' => 2],
            ['question' => '', 'options' => ['', '', '', '']],
        ]])->assertSessionHasNoErrors();
        $questions = $publication->quizQuestions()->get();
        $this->assertCount(2, $questions);
        $this->assertSame(['1NF', '2NF', '3NF'], $questions[1]->options);
        $this->assertSame(1, $questions[1]->correct_option);
        $this->put(route('knowledge.rewards.questions.update', $publication), ['questions' => [['question' => 'No answer ticked', 'options' => ['A', 'B']]]])->assertSessionHasErrors();
    }

    public function test_guests_get_a_few_free_reads_then_sign_in(): void
    {
        $this->setting('knowledge_reader_pass_enabled', true, 'boolean');
        $creator = User::factory()->create();
        $pubs = collect(range(1, 3))->map(fn ($i) => $this->publication($creator, 'Guest guide '.$i));
        $this->get(route('knowledge.show', $pubs[0]))->assertOk()->assertSee('Normalisation removes redundancy');
        $this->get(route('knowledge.show', $pubs[1]))->assertOk()->assertSee('Normalisation removes redundancy');
        $this->get(route('knowledge.show', $pubs[2]))->assertOk()->assertSee('Sign in to keep reading')->assertDontSee('Normalisation removes redundancy');
    }

    public function test_pool_split_never_exceeds_the_budget(): void
    {
        $split = app(RewardPoolService::class)->split(5000000, [1 => 900, 2 => 50, 3 => 30, 4 => 20], 40);
        $this->assertSame(2000000, $split[1]);
        $this->assertLessThanOrEqual(5000000, array_sum($split));
        $this->assertSame(0, array_sum($split) % 100);
        $this->assertGreaterThan($split[3], $split[2]);
    }
}
