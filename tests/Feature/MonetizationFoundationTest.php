<?php

namespace Tests\Feature;

use App\Models\CommercialAccount;
use App\Models\FeatureEntitlement;
use App\Models\University;
use App\Models\LedgerPosting;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use App\Services\Commerce\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonetizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_uses_separate_minor_unit_balances_and_balanced_journals(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->creditSpending($user, 10_000, 'wallet_funding');
        $wallets->debitSpending($user, 2_500, 'marketplace_purchase');
        $wallets->creditPendingEarnings($user, 4_000, 'sale_earning');
        $wallets->releasePending($user, 1_500);

        $wallet = $wallets->account($user)->fresh();
        $this->assertSame(7_500, $wallet->spending_balance_minor);
        $this->assertSame(2_500, $wallet->pending_earnings_minor);
        $this->assertSame(1_500, $wallet->available_earnings_minor);
        $this->assertSame(4_000, $wallet->lifetime_earnings_minor);

        foreach (LedgerPosting::query()->select('ledger_journal_id')->distinct()->pluck('ledger_journal_id') as $journalId) {
            $debits = (int) LedgerPosting::query()->where('ledger_journal_id', $journalId)->where('direction', 'debit')->sum('amount_minor');
            $credits = (int) LedgerPosting::query()->where('ledger_journal_id', $journalId)->where('direction', 'credit')->sum('amount_minor');
            $this->assertSame($debits, $credits);
        }
    }

    public function test_refund_shortfall_becomes_recovery_debt_and_future_earnings_repay_it(): void
    {
        $user = User::factory()->create();
        $wallets = app(WalletService::class);

        $wallets->creditPendingEarnings($user, 1_000, 'sale_earning');
        $wallets->releasePending($user, 1_000);
        $wallets->debitAvailableEarnings($user, 1_500, 'refund_reversal', null, 'Refund after prior withdrawal.', [], true);

        $wallet = $wallets->account($user)->fresh();
        $this->assertSame(0, $wallet->available_earnings_minor);
        $this->assertSame(500, $wallet->recovery_debt_minor);

        $wallets->creditPendingEarnings($user, 800, 'sale_earning');
        $wallet = $wallets->account($user)->fresh();
        $this->assertSame(300, $wallet->pending_earnings_minor);
        $this->assertSame(0, $wallet->recovery_debt_minor);
        $this->assertSame(1_800, $wallet->lifetime_earnings_minor);

        foreach (LedgerPosting::query()->select('ledger_journal_id')->distinct()->pluck('ledger_journal_id') as $journalId) {
            $debits = (int) LedgerPosting::query()->where('ledger_journal_id', $journalId)->where('direction', 'debit')->sum('amount_minor');
            $credits = (int) LedgerPosting::query()->where('ledger_journal_id', $journalId)->where('direction', 'credit')->sum('amount_minor');
            $this->assertSame($debits, $credits);
        }
    }

    public function test_feature_is_free_until_admin_enables_a_paid_rule_then_entitlement_controls_access(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->hasFeature('advanced_export'));

        PricingRule::query()->create([
            'key' => 'feature.advanced_export',
            'name' => 'Advanced export',
            'scope_type' => 'global',
            'currency' => 'NGN',
            'unit_amount_minor' => 5000,
            'enabled' => true,
        ]);
        $this->assertFalse($user->fresh()->hasFeature('advanced_export'));

        FeatureEntitlement::query()->create([
            'user_id' => $user->id,
            'feature' => 'advanced_export',
            'access_type' => 'granted',
            'status' => 'active',
        ]);
        $this->assertTrue($user->fresh()->hasFeature('advanced_export'));
    }
    public function test_active_institution_commercial_account_can_sponsor_a_paid_feature_for_verified_member(): void
    {
        $university = University::factory()->create([
            'adoption_status' => 'active',
            'adopted_at' => now(),
        ]);
        $user = User::factory()->create([
            'university_id' => $university->id,
            'role' => 'student',
        ]);

        PricingRule::query()->create([
            'key' => 'feature.attendance_tracking',
            'name' => 'Attendance Tracking',
            'scope_type' => 'global',
            'currency' => 'NGN',
            'unit_amount_minor' => 5000,
            'enabled' => true,
        ]);
        $this->assertFalse($user->fresh()->hasFeature('attendance_tracking'));

        CommercialAccount::query()->create([
            'university_id' => $university->id,
            'name' => $university->name.' Commercial Account',
            'currency' => 'NGN',
            'prepaid_balance_minor' => 100000,
            'status' => 'active',
            'metadata' => ['sponsored_features' => ['attendance_tracking']],
        ]);

        // Merely selecting an institution must not unlock institution-paid
        // benefits. The affiliation must be institution-verified first.
        $this->assertFalse($user->fresh()->hasFeature('attendance_tracking'));

        UserInstitutionAffiliation::query()->create([
            'user_id' => $user->id,
            'university_id' => $university->id,
            'affiliation_type' => 'student',
            'status' => 'verified',
            'verification_method' => 'admin_review',
            'source' => 'test_fixture',
            'verified_at' => now(),
        ]);

        $this->assertTrue($user->fresh()->hasFeature('attendance_tracking'));
    }

}
