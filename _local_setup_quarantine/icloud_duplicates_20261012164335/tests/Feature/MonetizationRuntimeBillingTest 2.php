<?php

namespace Tests\Feature;

use App\Ai\Contracts\AiResponse;
use App\Models\AiUsageCharge;
use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Models\FeatureUsageCharge;
use App\Models\PricingRule;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Services\Commerce\AiUsageBillingService;
use App\Services\Commerce\FeatureBillingService;
use App\Services\Commerce\WalletService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationRuntimeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_per_use_reserves_settles_and_refunds_without_double_charging(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);
        $wallets->creditSpending($user, 10_000, 'wallet_funding');
        $this->featureRule('attendance_tracking', 2_000, 'pay_per_use');

        $billing = app(FeatureBillingService::class);
        $reservation = $billing->begin($user, 'attendance_tracking', 'attendance-runtime-1');
        $this->assertSame(8_000, $wallets->account($user)->fresh()->spending_balance_minor);

        $billing->release($user, $reservation);
        $this->assertSame(10_000, $wallets->account($user)->fresh()->spending_balance_minor);
        $this->assertSame('released', FeatureUsageCharge::where('request_id', 'attendance-runtime-1')->value('status'));

        $reservation = $billing->begin($user, 'attendance_tracking', 'attendance-runtime-1');
        $this->assertSame(8_000, $wallets->account($user)->fresh()->spending_balance_minor);
        $billing->complete($user, $reservation);
        $this->assertSame('completed', FeatureUsageCharge::where('request_id', 'attendance-runtime-1')->value('status'));

        // Same logical operation cannot be replayed for free after completion.
        try {
            $billing->begin($user, 'attendance_tracking', 'attendance-runtime-1');
            $this->fail('Expected a duplicate paid operation to be blocked.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertStringContainsString('already completed', $exception->validator->errors()->first('feature'));
        }
        $this->assertSame(8_000, $wallets->account($user)->fresh()->spending_balance_minor);
    }

    public function test_fixed_access_purchase_deducts_wallet_and_creates_entitlement(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);
        $wallets->creditSpending($user, 10_000, 'wallet_funding');
        $this->featureRule('knowledge_hub_premium', 3_000, 'fixed_access', ['duration_days' => 30]);

        $entitlement = app(FeatureBillingService::class)->purchaseFixedAccess($user, 'knowledge_hub_premium', 'fixed-runtime-1');

        $this->assertSame(7_000, $wallets->account($user)->fresh()->spending_balance_minor);
        $this->assertSame('purchased', $entitlement->access_type);
        $this->assertNotNull($entitlement->expires_at);
        $this->assertTrue($user->fresh()->hasFeature('knowledge_hub_premium'));
        $this->assertDatabaseHas('feature_usage_charges', ['request_id' => 'fixed-runtime-1', 'status' => 'completed', 'total_amount_minor' => 3_000]);
    }

    public function test_verified_institution_sponsorship_pays_for_pay_per_use_feature(): void
    {
        $university = University::factory()->create(['adoption_status' => 'active', 'adopted_at' => now()]);
        $user = User::factory()->create(['university_id' => $university->id, 'role' => 'student']);
        UserInstitutionAffiliation::query()->create([
            'user_id' => $user->id,
            'university_id' => $university->id,
            'affiliation_type' => 'student',
            'status' => 'verified',
            'verification_method' => 'admin_review',
            'source' => 'test_fixture',
            'verified_at' => now(),
        ]);
        $wallets = app(WalletService::class);
        $wallets->creditSpending($user, 5_000, 'wallet_funding');
        $account = CommercialAccount::query()->create([
            'university_id' => $university->id,
            'name' => 'Sponsored account',
            'currency' => 'NGN',
            'prepaid_balance_minor' => 10_000,
            'status' => 'active',
            'metadata' => ['sponsored_features' => ['attendance_tracking']],
        ]);
        $this->featureRule('attendance_tracking', 2_000, 'pay_per_use');

        $reservation = app(FeatureBillingService::class)->begin($user, 'attendance_tracking', 'institution-feature-1');
        app(FeatureBillingService::class)->complete($user, $reservation);

        $this->assertSame(8_000, $account->fresh()->prepaid_balance_minor);
        $this->assertSame(5_000, $wallets->account($user)->fresh()->spending_balance_minor);
        $this->assertDatabaseHas('feature_usage_charges', ['request_id' => 'institution-feature-1', 'funding_source' => 'institution', 'status' => 'completed']);
    }

    public function test_ai_usage_deducts_after_free_allowance_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);
        $wallets->creditSpending($user, 10_000, 'wallet_funding');
        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $user->id);
        SettingService::set('ai_free_daily_requests', 0, 'integer', null, $user->id);
        SettingService::set('ai_request_charge_amount', '25', 'decimal', null, $user->id);
        SettingService::set('ai_local_currency_per_usd_reporting', '1500', 'decimal', null, $user->id);

        $billing = app(AiUsageBillingService::class);
        $reservation = $billing->reserve($user, 'study_assistant', 'ai-runtime-1');
        $this->assertSame(7_500, $wallets->account($user)->fresh()->spending_balance_minor);

        $response = new AiResponse(
            source: 'gemini', feature: 'study_assistant', success: true,
            summary: 'Answer', requestId: 'ai-runtime-1', provider: 'gemini', model: 'gemini-test',
            inputTokens: 100, outputTokens: 50, metadata: ['provider_cost_micro_usd' => 0],
        );
        $billing->settle($user, 'study_assistant', $response, $reservation);

        $this->assertDatabaseHas('ai_usage_charges', ['request_id' => 'ai-runtime-1', 'status' => 'completed', 'user_charge_minor' => 2_500]);
        $duplicate = $billing->reserve($user, 'study_assistant', 'ai-runtime-1');
        $this->assertTrue((bool) ($duplicate['already_completed'] ?? false));
        $this->assertSame(7_500, $wallets->account($user)->fresh()->spending_balance_minor);
    }

    public function test_assistant_specific_ai_rule_overrides_global_price_and_allowance(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);
        $wallets->creditSpending($user, 10_000, 'wallet_funding');
        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $user->id);
        SettingService::set('ai_free_daily_requests', 99, 'integer', null, $user->id);
        SettingService::set('ai_request_charge_amount', '25', 'decimal', null, $user->id);

        PricingRule::query()->create([
            'key' => 'ai.study_assistant',
            'name' => 'Student / Study Assistant',
            'scope_type' => 'global',
            'scope_id' => 0,
            'currency' => 'NGN',
            'unit_amount_minor' => 1_000,
            'enabled' => true,
            'metadata' => ['pricing_type' => 'ai_usage', 'ai_feature' => 'study_assistant', 'free_daily_requests' => 0],
            'starts_at' => now(),
        ]);

        $reservation = app(AiUsageBillingService::class)->reserve($user, 'study_assistant', 'ai-specific-1');
        $this->assertSame(1_000, $reservation['charge_minor']);
        $this->assertSame(9_000, $wallets->account($user)->fresh()->spending_balance_minor);
    }

    public function test_runtime_monetization_admin_settings_save_to_global_scope(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);

        $this->actingAs($admin)->put(route('admin.monetization.update'), [
            'wallet_minimum_funding_amount' => '600',
            'minimum_withdrawal_amount' => '1200',
            'withdrawal_fee_percentage' => '2.5',
            'knowledge_platform_commission_percentage' => '15',
            'knowledge_institution_revenue_percentage' => '5',
            'creator_earnings_hold_days' => 4,
            'ai_monetization_enabled' => '1',
            'ai_free_daily_requests' => 2,
            'ai_request_charge_amount' => '30',
            'ai_local_currency_per_usd_reporting' => '1600',
        ])->assertRedirect();

        $this->assertTrue((bool) SettingService::getGlobal('ai_monetization_enabled', false));
        $this->assertSame(2, (int) SettingService::getGlobal('ai_free_daily_requests', 0));
        $this->assertSame('30', (string) SettingService::getGlobal('ai_request_charge_amount', ''));
        $this->assertSame('600', (string) SettingService::getGlobal('wallet_minimum_funding_amount', ''));
    }

    private function featureRule(string $feature, int $amountMinor, string $pricingType, array $metadata = []): PricingRule
    {
        return PricingRule::query()->create([
            'key' => 'feature.'.$feature,
            'name' => str($feature)->replace('_', ' ')->title()->toString(),
            'scope_type' => 'global',
            'scope_id' => 0,
            'currency' => 'NGN',
            'unit_amount_minor' => $amountMinor,
            'enabled' => true,
            'metadata' => array_merge(['pricing_type' => $pricingType, 'feature' => $feature], $metadata),
            'starts_at' => now(),
        ]);
    }
}
