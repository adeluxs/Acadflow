<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FeatureEntitlement;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Commerce\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function featureRule(string $feature, int $amountMinor): PricingRule
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
            'metadata' => [
                'pricing_type' => 'fixed_access',
                'feature' => $feature,
                'duration_days' => 30,
                'unit_label' => 'access period',
            ],
            'starts_at' => now(),
        ]);
    }
}
