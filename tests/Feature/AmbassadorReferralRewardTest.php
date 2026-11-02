<?php

namespace Tests\Feature;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReward;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\AmbassadorQualificationService;
use App\Services\AmbassadorService;
use App\Services\Commerce\CommerceService;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Automatic ₦200 withdrawable + ₦300 study credit per qualified referral. */
class AmbassadorReferralRewardTest extends TestCase
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
        return AmbassadorApplication::forceCreate([
            'user_id' => User::factory()->create()->id, 'status' => 'approved',
            'institution' => 'Campus One', 'department' => 'Computer Science', 'study_level' => '200',
            'leadership_role' => 'course_rep', 'phone' => '+2348000000000', 'community_size' => 50,
            'statement' => 'I will demonstrate useful study tools to my classmates.', 'terms_accepted_at' => now(),
        ]);
    }

    private function qualifiableReferral(AmbassadorApplication $application): AmbassadorReferral
    {
        $user = User::factory()->create();
        $request = Request::create('/register', 'POST', ['referral_code' => $application->referral_code], [], [], ['REMOTE_ADDR' => '192.0.2.'.random_int(1, 200)]);
        $request->attributes->set('ambassador_browser', Str::random(64));
        app(AmbassadorService::class)->attributeRegistration($user, $request);
        $referral = AmbassadorReferral::where('user_id', $user->id)->firstOrFail();
        foreach ([0, 1, 7] as $day) {
            $this->travelTo($referral->registered_at->copy()->addDays($day));
            app(AmbassadorQualificationService::class)->recordStudyAction($user, 'study_note',
                'Today I compared insertion sort with merge sort and wrote down the time complexity of each step. Session '.$day);
        }
        return $referral;
    }

    private function review(AmbassadorReferral $referral, string $status = 'qualified')
    {
        $service = app(AmbassadorQualificationService::class);
        return $this->patch(route('admin.ambassadors.referrals.review', $referral), [
            'evidence_signature' => $service->assessment($service->evidence(AmbassadorReferral::whereKey($referral->id))->firstOrFail())['signature'],
            'status' => $status, 'review_note' => 'Confirmed a separate student through an independent campus contact.', 'acknowledge_risks' => true,
        ]);
    }

    private function wallet(AmbassadorApplication $application): WalletAccount
    {
        return WalletAccount::where('user_id', $application->user_id)->firstOrFail();
    }

    public function test_qualifying_a_referral_pays_withdrawable_cash_and_study_credit_once(): void
    {
        $application = $this->application();
        $referral = $this->qualifiableReferral($application);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $this->review($referral)->assertRedirect()
            ->assertSessionHas('success', fn ($message) => str_contains($message, '₦200 withdrawable cash') && str_contains($message, '₦300 study wallet credit'));

        $this->assertSame(2, AmbassadorReward::where('referral_id', $referral->id)->where('status', 'fulfilled')->count());
        $this->assertSame(20000, (int) $this->wallet($application)->available_earnings_minor);
        $this->assertSame(30000, (int) $this->wallet($application)->spending_balance_minor);
        $this->assertDatabaseHas('wallet_ledger_entries', ['entry_type' => 'ambassador_reward', 'amount_minor' => 20000]);
        $this->assertDatabaseHas('wallet_ledger_entries', ['entry_type' => 'ambassador_reward', 'amount_minor' => 30000]);

        // Disqualify and qualify again: nothing is paid twice.
        $this->review($referral, 'disqualified')->assertRedirect();
        $this->review($referral)->assertRedirect();
        $this->assertSame(2, AmbassadorReward::count());
        $this->assertSame(20000, (int) $this->wallet($application)->fresh()->available_earnings_minor);
        $this->assertSame(30000, (int) $this->wallet($application)->fresh()->spending_balance_minor);

        // A manual extra reward of the same kind cannot reuse the referral.
        $this->postJson(route('admin.ambassadors.rewards.store', $application), ['award_key' => 'extra', 'kind' => 'referral_cash', 'title' => 'Again',
            'reason' => 'Trying to pay this referral twice.', 'amount' => '200', 'currency' => 'NGN', 'referral_id' => $referral->id])->assertUnprocessable();

        // The ambassador sees both payments.
        $this->actingAs($application->user)->get(route('ambassadors.index'))->assertOk()
            ->assertSee('How you earn')->assertSee('Referral reward (withdrawable)')->assertSee('Referral study wallet credit');
    }

    public function test_only_the_cash_part_can_be_withdrawn(): void
    {
        SettingService::set('minimum_withdrawal_amount', '100', 'string');
        SettingService::flushRequestCache();
        $application = $this->application();
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($this->qualifiableReferral($application))->assertRedirect();
        $ambassador = $application->user;
        $payout = PayoutAccount::create(['uuid' => (string) Str::uuid(), 'user_id' => $ambassador->id, 'provider' => 'bank', 'account_name' => 'Amb',
            'account_number' => '0123456789', 'bank_code' => '058', 'bank_name' => 'Test Bank', 'currency' => 'NGN', 'is_verified' => true]);

        // ₦500 total in the wallet, but only the ₦200 cash is withdrawable.
        $refused = false;
        try { app(CommerceService::class)->requestWithdrawal($ambassador, $payout->id, '300', (string) Str::uuid()); }
        catch (\Throwable) { $refused = true; }
        $this->assertTrue($refused, 'Study credit must not be withdrawable.');
        $withdrawal = app(CommerceService::class)->requestWithdrawal($ambassador, $payout->id, '200', (string) Str::uuid());
        $this->assertSame(20000, (int) $withdrawal->amount_minor);
        $this->assertSame(0, (int) $this->wallet($application)->fresh()->available_earnings_minor);
        $this->assertSame(30000, (int) $this->wallet($application)->fresh()->spending_balance_minor);
    }

    public function test_rule_can_be_changed_or_switched_off_by_super_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'university_admin']))
            ->patch(route('admin.ambassadors.reward-rule.update'), ['enabled' => 1, 'cash' => '5000', 'credit' => '0'])->assertForbidden();

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin)->patch(route('admin.ambassadors.reward-rule.update'), ['enabled' => 1, 'cash' => '250', 'credit' => '0'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'ambassador.reward_rule.updated', 'user_id' => $admin->id]);
        $rule = AmbassadorService::referralRewardRule();
        $this->assertSame(25000, $rule['cash_minor']);
        $this->assertSame(0, $rule['credit_minor']);

        $application = $this->application();
        $this->review($this->qualifiableReferral($application))->assertRedirect();
        $this->assertSame(['referral_cash'], AmbassadorReward::pluck('kind')->all());
        // The public explanation follows the edited amount, with a worked example and the withdrawal minimum.
        $this->get(route('public.ambassadors'))->assertSee('How ambassador rewards are paid')->assertSee('₦250 cash per student')
            ->assertSee('₦1,250 cash you can withdraw')->assertSee('withdrawal minimum of ₦1,000')->assertDontSee('study wallet credit per student');
        $this->assertSame(25000, (int) $this->wallet($application)->available_earnings_minor);

        $this->patch(route('admin.ambassadors.reward-rule.update'), ['enabled' => 0, 'cash' => '250', 'credit' => '0'])->assertRedirect();
        $this->assertNull(AmbassadorService::referralRewardRule());
        $this->review($this->qualifiableReferral($application))->assertRedirect();
        $this->assertSame(1, AmbassadorReward::count());
        $this->get(route('public.ambassadors'))->assertSee('There is no fixed commission');
    }

    public function test_signups_and_unqualified_referrals_earn_nothing(): void
    {
        $application = $this->application();
        $referral = $this->qualifiableReferral($application);
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        $this->review($referral, 'pending')->assertRedirect();
        $this->review($referral, 'disqualified')->assertRedirect();
        $this->assertSame(0, AmbassadorReward::count());
        $this->assertNull(WalletAccount::where('user_id', $application->user_id)->first()?->available_earnings_minor ?: null);
    }
}
