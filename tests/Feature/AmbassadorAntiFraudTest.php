<?php

namespace Tests\Feature;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReward;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use App\Services\AmbassadorQualificationService;
use App\Services\AmbassadorService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmbassadorAntiFraudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
        $this->travelTo(now()->startOfSecond());
    }

    private function application(): AmbassadorApplication
    {
        return AmbassadorApplication::create([
            'user_id' => User::factory()->create()->id, 'status' => 'approved',
            'institution' => 'Campus One', 'department' => 'Computer Science', 'study_level' => '200',
            'leadership_role' => 'course_rep', 'phone' => '+2348000000000', 'community_size' => 50,
            'statement' => 'I will demonstrate useful study tools to my classmates.', 'terms_accepted_at' => now(),
        ]);
    }

    private function referral(AmbassadorApplication $application, array $attributes = [], ?string $browser = null, string $ip = '192.0.2.1'): AmbassadorReferral
    {
        $user = User::factory()->create($attributes);
        $request = Request::create('/register', 'POST', ['referral_code' => $application->referral_code], [], [], ['REMOTE_ADDR' => $ip]);
        $request->attributes->set('ambassador_browser', $browser ?? Str::random(64));
        app(AmbassadorService::class)->attributeRegistration($user, $request);
        return AmbassadorReferral::where('user_id', $user->id)->firstOrFail();
    }

    private function study(AmbassadorReferral $referral, string $variation): void
    {
        app(AmbassadorQualificationService::class)->recordStudyAction($referral->user, 'study_note',
            'Today I studied how different sorting algorithms work and compared the time complexity of insertion sort with merge sort. '.$variation);
    }

    private function prepareActivity(AmbassadorReferral $referral): void
    {
        foreach ([0, 1, 7] as $day) {
            $this->travelTo($referral->registered_at->copy()->addDays($day));
            $this->study($referral, 'Session '.$day);
        }
    }

    private function review(AmbassadorReferral $referral, string $status = 'qualified', bool $ack = true)
    {
        return $this->patchJson(route('admin.ambassadors.referrals.review', $referral), [
            'evidence_signature' => app(AmbassadorQualificationService::class)->assessment(app(AmbassadorQualificationService::class)->evidence(AmbassadorReferral::whereKey($referral->id))->firstOrFail())['signature'],
            'status' => $status, 'review_note' => 'Confirmed separate student through an independent campus contact.', 'acknowledge_risks' => $ack,
        ]);
    }

    private function awardData(AmbassadorReferral $referral, string $key = 'first-referral'): array
    {
        return ['award_key' => $key, 'kind' => 'performance_bonus', 'title' => 'Qualified referral reward',
            'reason' => 'One independently reviewed qualified referral.', 'amount' => '100', 'currency' => 'NGN', 'referral_id' => $referral->id];
    }

    public function test_web_signups_reuse_the_encrypted_browser_signal_and_reject_unsigned_markers(): void
    {
        \Illuminate\Support\Facades\Event::fake([\Illuminate\Auth\Events\Registered::class]);
        $application = $this->application();
        $token = str_repeat('a', 64);
        foreach (['first', 'second', 'unsigned'] as $case) {
            auth()->logout();
            if ($case === 'unsigned') $this->withUnencryptedCookie('ambassador_browser', $token);
            else $this->withCookie('ambassador_browser', $token);
            $this->post(route('store-register'), [
                'account_type' => 'student', 'first_name' => 'Invited', 'last_name' => 'Student',
                'username' => 'invited_'.Str::lower(Str::random(8)), 'email' => $case.'@example.org',
                'password' => 'Strong!Pass1234', 'password_confirmation' => 'Strong!Pass1234', 'terms' => '1',
                'referral_code' => $application->referral_code,
            ])->assertRedirect()->assertSessionHasNoErrors();
            $referral = AmbassadorReferral::latest('id')->firstOrFail();
            $expected = app(AmbassadorQualificationService::class)->digest('browser', $token);
            if ($case === 'unsigned') $this->assertNotSame($expected, $referral->browser_hash);
            else $this->assertSame($expected, $referral->browser_hash);
        }
        $this->assertDatabaseCount('ambassador_referrals', 3);
    }

    public function test_page_visits_repeated_work_and_same_day_actions_do_not_qualify(): void
    {
        $referral = $this->referral($this->application());
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        app(AmbassadorService::class)->recordActivity($referral->user);
        $this->review($referral)->assertUnprocessable();
        $this->study($referral, 'Session one');
        $this->study($referral, 'A different same-day session');
        $this->travel(1)->days();
        $this->study($referral, 'Session one'); // Exact content replay on a new date.
        $this->assertDatabaseCount('ambassador_referral_days', 1);
        $this->study($referral, 'Session two');
        $this->travel(1)->days();
        $this->study($referral, 'Session three');
        $this->review($referral)->assertUnprocessable(); // Three days but too young.
        $this->travelTo($referral->registered_at->copy()->addDays(7));
        $this->review($referral)->assertUnprocessable(); // Old enough, no late study action.
        $this->study($referral, 'Session after seven days');
        $this->review($referral)->assertRedirect();
        $this->assertSame('qualified', $referral->fresh()->qualification_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.referral.qualified']);
    }

    public function test_email_is_mandatory_even_when_platform_verification_is_optional_and_user_can_verify(): void
    {
        SettingService::set('email_verification_enabled', false, 'boolean');
        Notification::fake();
        $referral = $this->referral($this->application(), ['email_verified_at' => null]);
        $this->prepareActivity($referral);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $this->review($referral)->assertUnprocessable();
        $user = $referral->user;
        $this->actingAs($user)->get(route('ambassadors.index'))->assertOk()->assertSee('Send email verification link');
        $this->post(route('ambassadors.verify-email'))->assertRedirect();
        Notification::assertSentTo($user, QueuedVerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($admin);
        $this->review($referral)->assertRedirect();
    }

    public function test_alias_browser_and_hourly_burst_signals_require_explicit_investigation(): void
    {
        $application = $this->application();
        $service = app(AmbassadorQualificationService::class);
        $application->update(['browser_hash' => $service->digest('browser', 'shared-browser')]);
        $referral = $this->referral($application, ['email' => 'camp.us+one@gmail.com'], 'shared-browser');
        $this->referral($this->application(), ['email' => 'campus+two@googlemail.com'], 'shared-browser');
        for ($i = 0; $i < 3; $i++) $this->referral($application);
        $check = $service->assessment($service->evidence(AmbassadorReferral::whereKey($referral->id))->firstOrFail());
        foreach (['shared_browser', 'shared_mailbox', 'signup_burst', 'ambassador_browser'] as $flag) $this->assertArrayHasKey($flag, $check['flags']);
        $this->assertNotSame($service->emailHash('first.last@school.edu'), $service->emailHash('firstlast@school.edu'));
        $this->prepareActivity($referral);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($referral, ack: false)->assertUnprocessable()->assertJsonValidationErrors('acknowledge_risks');
        $this->get(route('admin.ambassadors.show', $application))->assertOk()->assertSee('Browser shared by')->assertSee('Mailbox or Gmail alias');
        $this->review($referral)->assertRedirect(); // Shared campus hardware/network is not an automatic ban.
        $this->assertSame('qualified', $referral->fresh()->qualification_status);
        $this->assertNotSame('192.0.2.1', $referral->ip_hash);
        $this->assertSame(64, strlen($referral->ip_hash));
        $this->assertArrayNotHasKey('ip_hash', $referral->toArray());
    }

    public function test_reward_requires_owned_qualified_referral_and_cannot_claim_it_twice(): void
    {
        $application = $this->application();
        $referral = $this->referral($application);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $data = $this->awardData($referral);
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $data)->assertUnprocessable();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), array_diff_key($data, ['referral_id' => true]))->assertUnprocessable();
        $this->assertDatabaseCount('ambassador_rewards', 0);
        $this->prepareActivity($referral);
        $this->review($referral)->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.store', $this->application()), $data)->assertUnprocessable();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $data)->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $data)->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $this->awardData($referral, 'another-key'))->assertUnprocessable();
        $reward = AmbassadorReward::firstOrFail();
        $this->post(route('admin.ambassadors.rewards.cancel', $reward))->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $this->awardData($referral, 'replacement'))->assertRedirect();
        $replacement = AmbassadorReward::latest('id')->firstOrFail();
        $this->post(route('admin.ambassadors.rewards.fulfil', $replacement), ['fulfilment_reference' => 'transfer-reviewed-1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), $this->awardData($referral, 'after-payment'))->assertUnprocessable();
        $this->assertDatabaseCount('ambassador_rewards', 2);
    }

    public function test_new_evidence_and_disqualification_block_pending_wallet_reward(): void
    {
        $application = $this->application();
        $referral = $this->referral($application, browser: 'shared-later');
        $this->prepareActivity($referral);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($referral)->assertRedirect();
        $data = array_replace($this->awardData($referral), ['kind' => 'wallet_credit']);
        $this->post(route('admin.ambassadors.rewards.store', $application), $data)->assertRedirect()->assertSessionHasNoErrors();
        $reward = AmbassadorReward::firstOrFail();
        $this->referral($this->application(), browser: 'shared-later');
        $this->get(route('admin.ambassadors.show', $application))->assertOk()->assertSee('Do not pay or deliver this award yet')->assertDontSee('Credit study wallet now');
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($referral)->assertRedirect();
        $this->review($referral, 'disqualified')->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $this->review($referral)->assertRedirect();
        $referral->user->update(['email_verified_at' => null]);
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $referral->user->update(['email_verified_at' => now(), 'is_active' => false]);
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $referral->user->update(['is_active' => true]);
        $this->post(route('admin.ambassadors.rewards.fulfil', $reward))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.ambassadors.rewards.fulfil', $reward))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('wallet_ledger_entries', 1);
    }

    public function test_only_super_admin_reviews_and_private_evidence_stays_private(): void
    {
        $application = $this->application();
        $referral = $this->referral($application);
        $this->prepareActivity($referral);
        foreach ([$application->user, $referral->user, User::factory()->create(['role' => 'university_admin'])] as $user) {
            $this->actingAs($user);
            $this->review($referral)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($referral)->assertRedirect();
        $this->get(route('admin.ambassadors.index', ['status' => 'all', 'work' => 'referrals']))->assertOk();
        $this->actingAs($application->user)->get(route('ambassadors.index'))->assertOk()
            ->assertDontSee($referral->user->email)->assertDontSee('independent campus contact');
        $this->actingAs(User::factory()->create())->post(route('ambassadors.verify-email'))->assertForbidden();
    }

    public function test_study_note_route_counts_only_persisted_substantive_work_and_ignores_expired_window(): void
    {
        $referral = $this->referral($this->application());
        $this->actingAs($referral->user);
        $this->postJson(route('study-notes.store'), ['title' => 'Incomplete'])->assertUnprocessable();
        $this->postJson(route('study-notes.store'), ['title' => 'Tiny', 'content' => 'hello'])->assertOk();
        $this->assertDatabaseCount('ambassador_referral_days', 0);
        $this->postJson(route('study-notes.store'), ['title' => 'Sorting', 'content' => 'Today I studied sorting algorithms and compared the memory requirements and running time for insertion sort and merge sort.'])->assertOk();
        $this->assertDatabaseCount('ambassador_referral_days', 1);
        $this->travel(31)->days();
        $this->study($referral, 'Too late to count');
        $this->assertDatabaseCount('ambassador_referral_days', 1);
    }

    public function test_stale_review_and_legacy_bonus_cannot_bypass_new_checks(): void
    {
        $application = $this->application();
        $referral = $this->referral($application, browser: 'stale-browser');
        $this->prepareActivity($referral);
        $service = app(AmbassadorQualificationService::class);
        $signature = $service->assessment($service->evidence(AmbassadorReferral::whereKey($referral->id))->firstOrFail())['signature'];
        $this->referral($application, browser: 'stale-browser');
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->patchJson(route('admin.ambassadors.referrals.review', $referral), [
            'status' => 'qualified', 'review_note' => 'This review used evidence from an older page.',
            'acknowledge_risks' => true, 'evidence_signature' => $signature,
        ])->assertUnprocessable()->assertJsonValidationErrors('referral');
        $referral->update(['browser_hash' => null, 'email_hash' => null, 'ip_hash' => null]);
        $this->review($referral, ack: false)->assertUnprocessable();
        $this->review($referral)->assertRedirect();
        $legacy = AmbassadorReward::create([
            'ambassador_application_id' => $application->id, 'kind' => 'performance_bonus', 'award_key' => 'legacy-bonus',
            'title' => 'Legacy bonus', 'reason' => 'Created before qualification existed.', 'amount_minor' => 10000, 'currency' => 'NGN',
            'issued_by' => auth()->id(),
        ]);
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $legacy), ['fulfilment_reference' => 'legacy-transfer'])->assertUnprocessable();
        $this->assertSame('pending', $legacy->fresh()->status);
    }

    public function test_review_list_query_count_is_constant_and_ordinary_study_work_has_no_tracking_queries(): void
    {
        $application = $this->application();
        $qualification = app(AmbassadorQualificationService::class);
        foreach ([1, 9] as $size) {
            for ($i = 0; $i < $size; $i++) $this->referral($application);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $rows = $qualification->evidence(AmbassadorReferral::query())->limit(10)->get();
            foreach ($rows as $row) $qualification->assessment($row);
            $this->assertCount(4, DB::getQueryLog()); // Referral evidence + 3 batched relations.
            DB::disableQueryLog();
        }
        $ordinary = User::factory()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $qualification->recordStudyAction($ordinary, 'study_note', str_repeat('A useful study sentence. ', 10));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
