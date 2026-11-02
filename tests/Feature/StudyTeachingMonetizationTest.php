<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Contracts\AiResponse;
use App\Models\FeatureEntitlement;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Commerce\AiUsageBillingService;
use App\Services\Commerce\PricingCatalogService;
use App\Services\Commerce\WalletService;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudyTeachingMonetizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_and_teaching_workspaces_remain_free_until_pricing_is_enabled(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);

        $this->actingAs($student)->get(route('study.index'))->assertOk();
        $this->actingAs($lecturer)->get(route('teaching.index'))->assertOk();

        $this->assertSame('fixed_access', config('monetization.pricing_features.student_study_tools.pricing_type'));
        $this->assertSame('fixed_access', config('monetization.pricing_features.lecturer_teaching_tools.pricing_type'));
        $this->assertNotNull(config('monetization.ai_features.practice_generator'));
        $this->assertNotNull(config('monetization.ai_features.lecturer_assistant'));
    }

    public function test_active_prices_lock_only_the_matching_role_workspace(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $lecturer = User::factory()->create(['role' => 'lecturer', 'account_type' => 'lecturer']);
        $this->featureRule('student_study_tools', 5_000);
        $this->featureRule('lecturer_teaching_tools', 7_500);

        $studentActivation = route('commerce.features', ['feature' => 'student_study_tools']).'#feature-student_study_tools';
        $lecturerActivation = route('commerce.features', ['feature' => 'lecturer_teaching_tools']).'#feature-lecturer_teaching_tools';

        $this->actingAs($student)->get(route('study.index'))->assertRedirect($studentActivation);
        $this->getJson(route('study.index'))->assertPaymentRequired()
            ->assertJsonPath('feature', 'student_study_tools')
            ->assertJsonPath('activation_url', $studentActivation);
        $this->get(route('courses.index'))->assertOk();
        $this->get(route('commerce.features', ['feature' => 'student_study_tools']))
            ->assertOk()->assertSee('Student Study Tools')
            ->assertSee('Choose the tools and AI allowance that fit your work')
            ->assertSee('Only after both are finished can a successful new AI request be charged to your wallet.')
            ->assertViewHas('featureRows', fn ($rows) => $rows->pluck('key')->contains('student_study_tools')
                && ! $rows->pluck('key')->contains('lecturer_teaching_tools'));
        $this->get(route('dashboard'))->assertOk()->assertSee('View access options');

        $this->actingAs($lecturer)->get(route('teaching.index'))->assertRedirect($lecturerActivation);
        $this->get(route('commerce.features', ['feature' => 'lecturer_teaching_tools']))
            ->assertOk()->assertSee('Lecturer Teaching Studio')
            ->assertViewHas('featureRows', fn ($rows) => $rows->pluck('key')->contains('lecturer_teaching_tools')
                && ! $rows->pluck('key')->contains('student_study_tools'));
        $this->get(route('dashboard'))->assertOk()->assertSee('View access options');
    }

    public function test_student_can_purchase_study_tools_from_wallet_and_unlock_access(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $wallets = app(WalletService::class);
        $wallets->creditSpending($student, 10_000, 'wallet_funding');
        $this->featureRule('student_study_tools', 4_000);

        $this->actingAs($student)->post(route('commerce.features.purchase'), [
            'feature' => 'student_study_tools',
            'request_id' => 'study-tools-purchase-1',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(6_000, $wallets->account($student)->fresh()->spending_balance_minor);
        $this->assertDatabaseHas('feature_entitlements', [
            'user_id' => $student->id,
            'feature' => 'student_study_tools',
            'access_type' => 'purchased',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('feature_usage_charges', [
            'request_id' => 'study-tools-purchase-1',
            'total_amount_minor' => 4_000,
            'status' => 'completed',
        ]);
        $this->get(route('study.index'))->assertOk();
    }

    public function test_role_specific_packages_cannot_be_purchased_by_another_role(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $this->featureRule('lecturer_teaching_tools', 7_500);

        $this->actingAs($student)->post(route('commerce.features.purchase'), [
            'feature' => 'lecturer_teaching_tools',
            'request_id' => 'wrong-role-purchase-1',
        ])->assertSessionHasErrors('feature');

        $this->assertFalse(FeatureEntitlement::query()->where('user_id', $student->id)->exists());
        $this->assertDatabaseMissing('feature_usage_charges', ['request_id' => 'wrong-role-purchase-1']);
    }

    public function test_semester_package_includes_ai_requests_that_are_restored_when_generation_fails(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $wallets = app(WalletService::class);
        $wallets->creditSpending($student, 20_000, 'wallet_funding');
        $this->featureRule('student_study_tools', 4_000, [
            'semester_amount_minor' => 10_000,
            'semester_duration_days' => 120,
            'semester_included_ai_requests' => 50,
        ]);

        $this->actingAs($student)->post(route('commerce.features.purchase'), [
            'feature' => 'student_study_tools',
            'billing_period' => 'semester',
            'request_id' => 'semester-study-tools-1',
        ])->assertSessionHasNoErrors();

        $entitlement = FeatureEntitlement::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame(50, $entitlement->remaining_units);
        $this->assertSame('semester', data_get($entitlement->metadata, 'billing_period'));
        $this->assertTrue($entitlement->expires_at->isSameDay(now()->addDays(120)));
        $this->assertSame(10_000, $wallets->account($student)->fresh()->spending_balance_minor);

        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $student->id);
        SettingService::set('ai_free_daily_requests', 0, 'integer', null, $student->id);
        SettingService::set('ai_request_charge_amount', '25', 'decimal', null, $student->id);
        $billing = app(AiUsageBillingService::class);
        $reservation = $billing->reserve($student, 'practice_generator', 'included-ai-1');
        $this->assertSame('package_allowance', $reservation['source']);
        $this->assertSame(0, $reservation['charge_minor']);
        $this->assertSame(49, $entitlement->fresh()->remaining_units);

        $billing->release($student, $reservation);
        $this->assertSame(50, $entitlement->fresh()->remaining_units);

        $reservation = $billing->reserve($student, 'practice_generator', 'included-ai-2');
        $billing->settle($student, 'practice_generator', new AiResponse(
            source: 'test', feature: 'practice_generator', success: true, summary: 'Quiz ready', requestId: 'included-ai-2',
            provider: 'test', model: 'test', inputTokens: 10, outputTokens: 10,
        ), $reservation);
        $this->assertSame(49, $entitlement->fresh()->remaining_units);
        $this->assertDatabaseHas('ai_usage_charges', ['request_id' => 'included-ai-2', 'user_charge_minor' => 0, 'status' => 'completed']);
    }

    public function test_trial_is_one_time_and_expired_package_has_read_only_grace(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $this->featureRule('student_study_tools', 4_000, [
            'trial_days' => 7,
            'trial_included_ai_requests' => 3,
            'grace_days' => 2,
        ]);

        $this->actingAs($student)->post(route('commerce.features.trial'), ['feature' => 'student_study_tools'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('feature_entitlements', [
            'user_id' => $student->id, 'feature' => 'student_study_tools', 'access_type' => 'trial', 'remaining_units' => 3,
        ]);
        $this->post(route('commerce.features.trial'), ['feature' => 'student_study_tools'])->assertSessionHasErrors('feature');

        $this->travel(8)->days();
        $this->get(route('study.index'))->assertOk();
        $this->post(route('study.notes.import'), [])->assertRedirect(route('commerce.features', ['feature' => 'student_study_tools']).'#feature-student_study_tools');

        $this->travel(2)->days();
        $this->get(route('study.index'))->assertRedirect(route('commerce.features', ['feature' => 'student_study_tools']).'#feature-student_study_tools');
    }

    public function test_ai_monthly_spending_limit_blocks_wallet_overage_before_debit(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $wallet = app(WalletService::class)->account($student);
        app(WalletService::class)->creditSpending($student, 10_000, 'wallet_funding');
        $wallet->update(['ai_monthly_spending_limit_minor' => 500]);
        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $student->id);
        SettingService::set('ai_free_daily_requests', 0, 'integer', null, $student->id);
        SettingService::set('ai_request_charge_amount', '10', 'decimal', null, $student->id);

        try {
            app(AiUsageBillingService::class)->reserve($student, 'practice_generator', 'limited-ai-1');
            $this->fail('Expected the monthly AI spending limit to block the charge.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('monthly AI spending limit', $exception->validator->errors()->first('ai'));
        }

        $this->assertSame(10_000, $wallet->fresh()->spending_balance_minor);
        $this->assertDatabaseMissing('ai_usage_charges', ['request_id' => 'limited-ai-1']);
    }

    public function test_admin_can_publish_complete_package_terms_as_one_immutable_rule(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);

        $this->actingAs($admin)->post(route('admin.monetization.pricing-rules.store'), [
            'target' => 'feature:student_study_tools',
            'amount' => '4000',
            'duration_days' => 30,
            'semester_amount' => '10000',
            'semester_duration_days' => 120,
            'included_ai_requests' => 20,
            'semester_included_ai_requests' => 75,
            'trial_days' => 7,
            'trial_included_ai_requests' => 3,
            'grace_days' => 5,
            'enabled' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $rule = PricingRule::query()->where('key', 'feature.student_study_tools')->firstOrFail();
        $this->assertSame(400_000, $rule->unit_amount_minor);
        $this->assertSame(1_000_000, data_get($rule->metadata, 'semester_amount_minor'));
        $this->assertSame(75, data_get($rule->metadata, 'semester_included_ai_requests'));
        $this->assertSame(7, data_get($rule->metadata, 'trial_days'));
        $this->assertSame(5, data_get($rule->metadata, 'grace_days'));
        $this->assertTrue((bool) data_get($rule->metadata, 'package'));
    }

    public function test_ai_plus_is_available_to_students_lecturers_and_other_members_alongside_role_packages(): void
    {
        $this->featureRule('academic_ai_plus', 3_000, ['included_ai_requests' => 12]);
        $this->featureRule('student_study_tools', 4_000, ['included_ai_requests' => 5]);
        $wallets = app(WalletService::class);

        foreach ([
            'student' => ['account_type' => 'student', 'ai_feature' => 'writing_assistant'],
            'lecturer' => ['account_type' => 'lecturer', 'ai_feature' => 'research_assistant'],
            'member' => ['account_type' => 'researcher', 'ai_feature' => 'citation_assistant'],
        ] as $role => $case) {
            $user = User::factory()->create(['role' => $role, 'account_type' => $case['account_type']]);
            $wallets->creditSpending($user, 10_000, 'wallet_funding');
            SettingService::set('ai_monetization_enabled', true, 'boolean', null, $user->id);
            SettingService::set('ai_free_daily_requests', 0, 'integer', null, $user->id);

            $response = $this->actingAs($user)->get(route('commerce.features'));
            $response->assertOk()->assertSee('Academic AI Plus')->assertSee('Included in AI Plus')
                ->assertViewHas('featureRows', fn ($rows) => $rows->pluck('key')->contains('academic_ai_plus')
                    && ($rows->pluck('key')->contains('student_study_tools') === ($role === 'student'))
                    && ($rows->pluck('key')->contains('lecturer_teaching_tools') === ($role === 'lecturer')));

            $this->post(route('commerce.features.purchase'), [
                'feature' => 'academic_ai_plus',
                'request_id' => 'ai-plus-'.$role,
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertDatabaseHas('feature_entitlements', [
                'user_id' => $user->id,
                'feature' => 'academic_ai_plus',
                'status' => 'active',
                'remaining_units' => 12,
            ]);

            $reservation = app(AiUsageBillingService::class)->reserve($user, $case['ai_feature'], 'ai-plus-use-'.$role);
            $this->assertSame('package_allowance', $reservation['source']);
            $this->assertSame(0, $reservation['charge_minor']);
        }
    }

    public function test_ai_plus_does_not_consume_allowance_for_role_specific_ai_tools(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $this->featureRule('academic_ai_plus', 3_000, ['included_ai_requests' => 10]);
        $wallets = app(WalletService::class);
        $wallets->creditSpending($student, 10_000, 'wallet_funding');
        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $student->id);
        SettingService::set('ai_free_daily_requests', 0, 'integer', null, $student->id);
        $this->actingAs($student)->post(route('commerce.features.purchase'), [
            'feature' => 'academic_ai_plus',
            'request_id' => 'ai-plus-boundary-purchase',
        ])->assertSessionHasNoErrors();

        SettingService::set('ai_request_charge_amount', '25', 'decimal', null, $student->id);
        $reservation = app(AiUsageBillingService::class)->reserve($student, 'practice_generator', 'ai-plus-boundary-use');

        $this->assertSame('user_wallet', $reservation['source']);
        $this->assertSame(2_500, $reservation['charge_minor']);
        $this->assertSame(10, FeatureEntitlement::query()->where('user_id', $student->id)->where('feature', 'academic_ai_plus')->value('remaining_units'));
    }

    public function test_ai_plus_cannot_be_sold_while_ai_overage_charging_is_disabled(): void
    {
        $member = User::factory()->create(['role' => 'member', 'account_type' => 'independent_professional']);
        SettingService::set('ai_monetization_enabled', false, 'boolean', null, $member->id);
        $this->featureRule('academic_ai_plus', 3_000, ['included_ai_requests' => 10, 'trial_days' => 3]);
        app(WalletService::class)->creditSpending($member, 10_000, 'wallet_funding');

        $this->actingAs($member)->get(route('commerce.features'))
            ->assertOk()->assertSee('Purchases paused')->assertSee('allowance package is not needed');
        $this->post(route('commerce.features.purchase'), [
            'feature' => 'academic_ai_plus',
            'request_id' => 'disabled-ai-plus-purchase',
        ])->assertSessionHasErrors('feature');
        $this->post(route('commerce.features.trial'), ['feature' => 'academic_ai_plus'])
            ->assertSessionHasErrors('feature');

        $this->assertDatabaseMissing('feature_entitlements', ['user_id' => $member->id, 'feature' => 'academic_ai_plus']);
        $this->assertSame(10_000, app(WalletService::class)->account($member)->spending_balance_minor);
    }

    public function test_general_free_plan_is_visible_to_every_role_and_admin_can_remove_its_allowance(): void
    {
        $member = User::factory()->create(['role' => 'member', 'account_type' => 'author_publisher']);
        app(WalletService::class)->creditSpending($member, 10_000, 'wallet_funding');
        SettingService::set('general_free_plan_enabled', true, 'boolean', null, $member->id);
        SettingService::set('ai_monetization_enabled', true, 'boolean', null, $member->id);
        SettingService::set('ai_free_daily_requests', 1, 'integer', null, $member->id);
        SettingService::set('ai_request_charge_amount', '25', 'decimal', null, $member->id);

        $this->actingAs($member)->get(route('commerce.features'))
            ->assertOk()->assertSee('General Free')->assertSee('1 free successful AI request');
        $free = app(AiUsageBillingService::class)->reserve($member, 'writing_assistant', 'general-free-1');
        $this->assertSame('free_allowance', $free['source']);

        SettingService::set('general_free_plan_enabled', false, 'boolean', null, $member->id);
        $this->get(route('commerce.features'))->assertOk()->assertDontSee('Your included plan');
        $paid = app(AiUsageBillingService::class)->reserve($member, 'citation_assistant', 'general-free-disabled-1');
        $this->assertSame('user_wallet', $paid['source']);
        $this->assertSame(2_500, $paid['charge_minor']);
    }

    public function test_low_balance_sends_user_to_prefilled_wallet_and_preserves_purchase_intent(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_type' => 'student']);
        $wallets = app(WalletService::class);
        $wallets->creditSpending($student, 1_000, 'wallet_funding');
        $this->featureRule('student_study_tools', 4_000, ['included_ai_requests' => 5]);

        $walletUrl = route('commerce.wallet', ['resume_package' => 1]).'#fund-wallet';
        $this->actingAs($student)->post(route('commerce.features.purchase'), [
            'feature' => 'student_study_tools',
            'billing_period' => 'monthly',
            'request_id' => 'low-balance-plan-1',
        ])->assertRedirect($walletUrl)->assertSessionHas('commerce.package_purchase_intent', fn (array $intent): bool => $intent['feature'] === 'student_study_tools' && $intent['required_amount_minor'] === 4_000
        );

        $this->get(route('commerce.wallet', ['resume_package' => 1]))
            ->assertOk()->assertSee('Finish buying Study Pro')
            ->assertViewHas('suggestedFunding', '500.00');

        $wallets->creditSpending($student, 3_000, 'wallet_funding');
        $this->post(route('commerce.features.purchase'), [
            'feature' => 'student_study_tools',
            'billing_period' => 'monthly',
            'request_id' => 'resumed-plan-1',
        ])->assertRedirect()->assertSessionMissing('commerce.package_purchase_intent');
        $this->assertDatabaseHas('feature_entitlements', ['user_id' => $student->id, 'feature' => 'student_study_tools', 'status' => 'active']);
    }

    public function test_admin_can_disable_and_reenable_latest_package_price_without_rewriting_history(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'account_type' => 'super_admin']);
        $first = $this->featureRule('student_study_tools', 4_000, ['included_ai_requests' => 5]);

        $this->actingAs($admin)->patch(route('admin.monetization.pricing-rules.status', $first), ['enabled' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $disabled = PricingRule::query()->where('key', $first->key)->latest('version')->firstOrFail();
        $this->assertFalse($disabled->enabled);
        $this->assertSame(2, $disabled->version);
        $this->assertSame($first->id, $disabled->supersedes_id);
        $this->assertNull(app(PricingCatalogService::class)->featureRule('student_study_tools'));

        $this->patch(route('admin.monetization.pricing-rules.status', $disabled), ['enabled' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $enabled = PricingRule::query()->where('key', $first->key)->latest('version')->firstOrFail();
        $this->assertTrue($enabled->enabled);
        $this->assertSame(3, $enabled->version);
        $this->assertSame(4_000, $enabled->unit_amount_minor);
        $this->assertSame(5, data_get($enabled->metadata, 'included_ai_requests'));

        $this->get(route('admin.monetization'))->assertOk()->assertSee('Disable pricing');
    }

    private function featureRule(string $feature, int $amountMinor, array $metadata = []): PricingRule
    {
        return PricingRule::query()->create([
            'key' => 'feature.'.$feature,
            'name' => str($feature)->replace('_', ' ')->title()->toString(),
            'scope_type' => 'global',
            'scope_id' => 0,
            'version' => 1,
            'currency' => 'NGN',
            'unit_amount_minor' => $amountMinor,
            'enabled' => true,
            'metadata' => array_merge([
                'pricing_type' => 'fixed_access',
                'feature' => $feature,
                'duration_days' => 30,
                'unit_label' => 'access period',
            ], $metadata),
            'starts_at' => now(),
        ]);
    }
}
