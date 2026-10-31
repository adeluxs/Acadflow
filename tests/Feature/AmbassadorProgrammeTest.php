<?php

namespace Tests\Feature;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReport;
use App\Models\AmbassadorReward;
use App\Models\CommerceOrder;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Services\AmbassadorService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class AmbassadorProgrammeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SettingService::flushRequestCache();
        FeatureAccessService::flushRequestCache();
    }

    private function application(?User $user = null, string $status = 'approved'): AmbassadorApplication
    {
        return AmbassadorApplication::create($this->applicationData() + [
            'user_id' => ($user ?? User::factory()->create())->id,
            'status' => $status, 'terms_accepted_at' => now(),
        ]);
    }

    private function applicationData(): array
    {
        return ['institution' => 'Campus One', 'department' => 'Computer Science', 'study_level' => '200 level',
            'leadership_role' => 'course_rep', 'phone' => '+2349017317245', 'community_size' => 50,
            'statement' => 'I will help my classmates organise a study session and learn the product.'];
    }

    private function registrationData(): array
    {
        return ['account_type' => 'student', 'first_name' => 'Invited', 'last_name' => 'Student',
            'username' => 'invited_'.Str::lower(Str::random(8)), 'email' => Str::lower(Str::random(10)).'@example.org',
            'password' => 'Strong!Pass1234', 'password_confirmation' => 'Strong!Pass1234', 'terms' => '1'];
    }

    private function reportData(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'kind' => 'demo', 'title' => 'First study demo',
            'occurred_on' => now()->toDateString(), 'participants' => 12,
            'summary' => 'We demonstrated study notes and collected useful feedback from the group.'];
    }

    private function awardData(): array
    {
        return ['award_key' => 'october-demo-1', 'kind' => 'wallet_credit', 'title' => 'Study demo reward',
            'reason' => 'For hosting an approved study demonstration.', 'amount' => '1250.50', 'currency' => 'NGN'];
    }

    public function test_public_recruitment_and_application_review_preserve_account_roles(): void
    {
        $this->get(route('public.ambassadors'))->assertOk()->assertSee('Become an AcadSyntra Campus Ambassador');
        $this->get(route('ambassadors.index'))->assertRedirect(route('login'));
        $student = User::factory()->create();
        $this->actingAs($student)->get(route('ambassadors.index'))->assertOk()->assertSee('Tell us about your campus');
        $data = $this->applicationData() + ['terms' => 1, 'status' => 'approved', 'user_id' => 999];
        $this->post(route('ambassadors.apply'), $data)->assertRedirect(route('ambassadors.index'));
        $application = AmbassadorApplication::firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertEquals($student->id, $application->user_id);
        $this->postJson(route('ambassadors.apply'), $data)->assertUnprocessable();
        $this->assertDatabaseCount('ambassador_applications', 1);
        $this->patchJson(route('admin.ambassadors.review', $application), ['status' => 'approved', 'review_note' => 'Verified student leader.'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.ambassadors.index'))->assertOk()->assertSee('Ambassador applications');
        $this->patch(route('admin.ambassadors.review', $application), ['status' => 'approved', 'review_note' => 'Verified student leader.'])->assertRedirect();
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('student', $student->fresh()->role);
        $this->actingAs($student)->get(route('ambassadors.index'))->assertOk()->assertSee('Share on WhatsApp');
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.application.approved', 'user_id' => $admin->id]);
    }

    public function test_rejected_application_can_be_resubmitted_but_suspension_cannot_be_bypassed(): void
    {
        $student = User::factory()->create();
        $application = $this->application($student, 'rejected');
        $this->actingAs($student)->post(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1])->assertRedirect();
        $this->assertSame('pending', $application->fresh()->status);
        $application->update(['status' => 'suspended']);
        $this->postJson(route('ambassadors.apply'), $this->applicationData() + ['terms' => 1])->assertUnprocessable();
        $this->postJson(route('ambassadors.reports.store'), $this->reportData())->assertForbidden();
    }

    public function test_first_referral_survives_navigation_and_attaches_to_actual_web_registration(): void
    {
        Event::fake([\Illuminate\Auth\Events\Registered::class]);
        $first = $this->application();
        $second = $this->application();
        $this->get(route('ambassadors.referral', $first->referral_code))->assertRedirect(route('register'));
        $this->get(route('ambassadors.referral', $second->referral_code))->assertSessionHas('ambassador_referral.code', $first->referral_code);
        $this->get(route('register'))->assertOk()->assertSee('An ambassador invited you');
        $data = $this->registrationData();
        $this->post(route('store-register'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $newUser = User::where('email', $data['email'])->firstOrFail();
        $this->assertDatabaseHas('ambassador_referrals', ['user_id' => $newUser->id, 'ambassador_application_id' => $first->id]);
        $this->assertDatabaseCount('ambassador_referrals', 1);
        $this->assertTrue($newUser->has_ambassador_referral);
        $this->assertSame(64, strlen(AmbassadorReferral::firstOrFail()->browser_hash));
        $this->assertSame(64, strlen(AmbassadorReferral::firstOrFail()->email_hash));
        $this->assertFalse(session()->has('ambassador_referral'));
        $this->get(route('ambassadors.referral', $second->referral_code))->assertRedirect(route('ambassadors.index'));
        $this->assertDatabaseCount('ambassador_referrals', 1);
    }

    public function test_api_registration_accepts_referral_without_granting_ambassador_status(): void
    {
        Event::fake([\Illuminate\Auth\Events\Registered::class]);
        $application = $this->application();
        $data = $this->registrationData() + ['referral_code' => $application->referral_code];
        $this->postJson('/api/v1/auth/register', $data)->assertCreated();
        $newUser = User::where('email', $data['email'])->firstOrFail();
        $this->assertDatabaseHas('ambassador_referrals', ['user_id' => $newUser->id, 'ambassador_application_id' => $application->id]);
        $this->assertDatabaseMissing('ambassador_applications', ['user_id' => $newUser->id]);
    }

    public function test_expired_and_suspended_referrals_do_not_count(): void
    {
        Event::fake([\Illuminate\Auth\Events\Registered::class]);
        $application = $this->application();
        $this->withSession(['ambassador_referral' => ['code' => $application->referral_code, 'expires_at' => now()->subSecond()->timestamp]])
            ->post(route('store-register'), $this->registrationData())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ambassador_referrals', 0);
        auth()->logout();
        $application->update(['status' => 'suspended']);
        // Same response as a valid code (no enumeration oracle), but nothing is remembered.
        $this->flushSession();
        $this->get(route('ambassadors.referral', $application->referral_code))->assertRedirect(route('register'))->assertSessionMissing('ambassador_referral');
        $this->withSession(['ambassador_referral' => ['code' => $application->referral_code, 'expires_at' => now()->addDay()->timestamp]])
            ->post(route('store-register'), $this->registrationData())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ambassador_referrals', 0);
    }

    public function test_private_dashboards_are_owner_scoped_and_institution_admins_cannot_manage_programme(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $application = $this->application($owner);
        $application->reports()->create($this->reportData());
        $this->actingAs($other)->get(route('ambassadors.index'))->assertOk()->assertDontSee('First study demo')->assertDontSee($application->referral_code);
        $this->get(route('admin.ambassadors.show', $application))->assertForbidden();
        $admin = User::factory()->create(['role' => 'university_admin']);
        $this->actingAs($admin)->get(route('admin.ambassadors.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('ambassadors.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_reports_are_owned_deduplicated_and_reviewed_only_once(): void
    {
        $owner = User::factory()->create();
        $application = $this->application($owner);
        $other = $this->application();
        $data = $this->reportData() + ['ambassador_application_id' => $other->id, 'status' => 'approved'];
        $this->actingAs($owner)->post(route('ambassadors.reports.store'), $data)->assertRedirect();
        $this->post(route('ambassadors.reports.store'), $data)->assertRedirect();
        $report = AmbassadorReport::firstOrFail();
        $this->assertDatabaseCount('ambassador_reports', 1);
        $this->assertEquals($application->id, $report->ambassador_application_id);
        $this->assertSame('pending', $report->status);
        $this->patchJson(route('admin.ambassadors.reports.review', $report), ['status' => 'approved', 'review_note' => 'Reviewed activity evidence.'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.ambassadors.reports.review', $report), ['status' => 'approved', 'review_note' => 'Reviewed activity evidence.'])->assertRedirect();
        $this->patchJson(route('admin.ambassadors.reports.review', $report), ['status' => 'rejected', 'review_note' => 'Repeated stale submission.'])->assertConflict();
        $this->actingAs($owner)->postJson(route('ambassadors.reports.store'), array_replace($this->reportData(), ['evidence_url' => 'javascript:alert(1)']))->assertUnprocessable();
    }

    public function test_rewards_credit_the_wallet_once_and_cannot_be_self_awarded_or_cancelled_after_payment(): void
    {
        $owner = User::factory()->create();
        $application = $this->application($owner);
        $data = $this->awardData();
        $this->actingAs($owner)->postJson(route('admin.ambassadors.rewards.store', $application), $data)->assertForbidden();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.ambassadors.rewards.store', $application), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.ambassadors.rewards.store', $application), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ambassador_rewards', 1);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
        $reward = AmbassadorReward::firstOrFail();
        $this->postJson(route('admin.ambassadors.rewards.store', $application), array_replace($data, ['amount' => '9000']))->assertUnprocessable();
        $this->get(route('admin.ambassadors.show', $application))->assertOk()->assertSee('Credit study wallet now');
        $this->post(route('admin.ambassadors.rewards.fulfil', $reward))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.ambassadors.rewards.fulfil', $reward))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(125050, WalletAccount::where('user_id', $owner->id)->value('spending_balance_minor'));
        $this->assertSame(1, WalletLedgerEntry::where('entry_type', 'ambassador_reward')->count());
        $this->assertDatabaseHas('ledger_postings', ['account_code' => 'ambassador_marketing_expense', 'direction' => 'debit', 'amount_minor' => 125050]);
        $this->assertDatabaseHas('ledger_postings', ['account_code' => 'user_spending_liability', 'direction' => 'credit', 'amount_minor' => 125050]);
        $this->assertSame('fulfilled', $reward->fresh()->status);
        $this->postJson(route('admin.ambassadors.rewards.cancel', $reward))->assertConflict();
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.reward.fulfilled', 'user_id' => $admin->id]);
    }

    public function test_external_rewards_require_delivery_evidence_and_suspension_blocks_wallet_credit(): void
    {
        $application = $this->application();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.ambassadors.rewards.store', $application), array_replace($this->awardData(), ['kind' => 'data_allowance']))->assertRedirect();
        $reward = AmbassadorReward::firstOrFail();
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $this->post(route('admin.ambassadors.rewards.fulfil', $reward), ['fulfilment_reference' => 'transfer-123'])->assertRedirect();
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
        $this->post(route('admin.ambassadors.rewards.store', $application), array_replace($this->awardData(), ['award_key' => 'second-award']))->assertRedirect();
        $walletReward = AmbassadorReward::latest('id')->firstOrFail();
        $this->patch(route('admin.ambassadors.review', $application), ['status' => 'suspended', 'review_note' => 'Suspended pending review.'])->assertRedirect();
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $walletReward))->assertConflict();
        $this->post(route('admin.ambassadors.rewards.cancel', $walletReward))->assertRedirect();
        $this->assertSame('cancelled', $walletReward->fresh()->status);
    }

    public function test_wallet_currency_mismatch_rolls_back_fulfilment(): void
    {
        $application = $this->application();
        $admin = User::factory()->create(['role' => 'super_admin']);
        WalletAccount::create(['user_id' => $application->user_id, 'currency' => 'USD', 'status' => 'active']);
        $this->actingAs($admin)->post(route('admin.ambassadors.rewards.store', $application), $this->awardData())->assertRedirect();
        $reward = AmbassadorReward::firstOrFail();
        $this->postJson(route('admin.ambassadors.rewards.fulfil', $reward))->assertUnprocessable();
        $this->assertSame('pending', $reward->fresh()->status);
        $this->assertDatabaseCount('wallet_ledger_entries', 0);
    }

    public function test_metrics_use_real_activity_and_paid_orders_without_exposing_student_details(): void
    {
        $this->travelTo(now()->startOfDay());
        $today = now();
        $application = $this->application();
        foreach (['retained', 'early', 'late', 'incomplete', 'inactive'] as $case) {
            $user = User::factory()->create(['onboarding_completed_at' => $case === 'incomplete' ? null : now()->subDays(40), 'is_active' => $case !== 'inactive']);
            $user->forceFill(['has_ambassador_referral' => true])->save();
            AmbassadorReferral::create(['ambassador_application_id' => $application->id, 'user_id' => $user->id,
                'registered_at' => now()->subDays(40), 'retention_starts_at' => now()->subDays(33), 'retention_ends_at' => now()->subDays(10)]);
            $activityAt = match ($case) { 'early' => now()->subDays(38), 'late' => now()->subDays(2), default => now()->subDays(20) };
            $this->travelTo($activityAt);
            app(\App\Services\MeaningfulActivityService::class)->record($user);
            $this->travelTo($today);
            CommerceOrder::create(['buyer_id' => $user->id, 'order_number' => 'AMB-'.Str::random(12), 'currency' => 'NGN',
                'subtotal' => 100, 'total_amount' => 100, 'total_amount_minor' => 10000, 'status' => 'completed',
                'payment_status' => $case === 'retained' ? 'paid' : 'refunded', 'paid_at' => now()->subDays(15)]);
        }
        $metrics = app(AmbassadorService::class)->metrics($application->id);
        $this->assertSame(['registrations' => 5, 'activated' => 3, 'retained' => 1, 'paying' => 1, 'qualified' => 0], $metrics);
        // Notification retention must not erase historical ambassador results.
        DB::table('user_activity_days')->delete();
        $this->assertSame($metrics, app(AmbassadorService::class)->metrics($application->id));
        $response = $this->actingAs($application->user)->get(route('ambassadors.index'))->assertOk();
        foreach (User::where('id', '!=', $application->user_id)->pluck('email') as $email) $response->assertDontSee($email);
    }

    public function test_metrics_have_constant_query_count_and_unreferred_registration_has_no_tracking_queries(): void
    {
        $application = $this->application();
        $service = app(AmbassadorService::class);
        foreach ([1, 30] as $size) {
            foreach (User::factory()->count($size)->create() as $user) {
                AmbassadorReferral::create(['ambassador_application_id' => $application->id, 'user_id' => $user->id,
                    'registered_at' => now(), 'retention_starts_at' => now()->addDays(7), 'retention_ends_at' => now()->addDays(30)]);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $metrics = $service->metrics($application->id);
            $this->assertCount(1, DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame(31, $metrics['registrations']);
        $user = User::factory()->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->attributeRegistration($user, Request::create('/register', 'POST'));
        $service->recordActivity($user);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_feature_switch_blocks_new_referrals_but_preserves_platform_admin_management(): void
    {
        Event::fake([\Illuminate\Auth\Events\Registered::class]);
        $application = $this->application();
        FeatureAccessService::setStatus('campus_ambassadors', FeatureAccessService::STATUS_DISABLED);
        $this->get(route('public.ambassadors'))->assertForbidden();
        $this->get(route('ambassadors.referral', $application->referral_code))->assertForbidden();
        $this->withSession(['ambassador_referral' => ['code' => $application->referral_code, 'expires_at' => now()->addDay()->timestamp]])
            ->post(route('store-register'), $this->registrationData())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ambassador_referrals', 0);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.ambassadors.index'))->assertOk();
    }

    public function test_retention_starts_after_seven_days_and_milestones_are_written_only_once(): void
    {
        $start = now()->startOfSecond();
        $this->travelTo($start);
        $application = $this->application();
        $user = User::factory()->create(['onboarding_completed_at' => null]);
        $user->forceFill(['has_ambassador_referral' => true])->save();
        $referral = AmbassadorReferral::create(['ambassador_application_id' => $application->id, 'user_id' => $user->id,
            'registered_at' => $start, 'retention_starts_at' => $start->copy()->addDays(7), 'retention_ends_at' => $start->copy()->addDays(30)]);
        $service = app(AmbassadorService::class);
        $service->recordActivity($user);
        $this->assertNull($referral->fresh()->activated_at);
        $user->forceFill(['onboarding_completed_at' => $start])->save();
        $service->recordActivity($user);
        $this->assertTrue($referral->fresh()->activated_at->equalTo($start));
        $this->travelTo($start->copy()->addDays(7)->subSecond());
        $service->recordActivity($user);
        $this->assertNull($referral->fresh()->retained_at);
        $this->travelTo($start->copy()->addDays(7));
        $service->recordActivity($user);
        $retainedAt = $referral->fresh()->retained_at;
        $this->assertTrue($retainedAt->equalTo(now()));
        $this->travelTo($start->copy()->addDays(8));
        $service->recordActivity($user);
        $this->assertTrue($referral->fresh()->retained_at->equalTo($retainedAt));
        $this->assertTrue($referral->fresh()->activated_at->equalTo($start));
    }

    public function test_existing_users_cannot_be_attributed_and_ambassadors_cannot_refer_themselves(): void
    {
        $application = $this->application();
        $request = Request::create('/register', 'POST', ['referral_code' => $application->referral_code]);
        $service = app(AmbassadorService::class);
        $existing = User::factory()->create()->fresh();
        $service->attributeRegistration($existing, $request);
        $owner = $application->user;
        $owner->wasRecentlyCreated = true;
        $service->attributeRegistration($owner, $request);
        $this->assertDatabaseCount('ambassador_referrals', 0);
    }
}
