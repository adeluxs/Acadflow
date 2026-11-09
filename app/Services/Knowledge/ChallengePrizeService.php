<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\AcademicChallenge;
use App\Models\AuditLog;
use App\Models\ChallengePrizeAward;
use App\Models\ChallengePrizeFund;
use App\Models\User;
use App\Models\WalletAccount;
use App\Services\Commerce\WalletService;
use App\Services\NotificationService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creator-funded prizes for community challenges. The creator pays the prize
 * (plus the platform fee) into AcadSyntra escrow before the challenge goes
 * live; after results the creator (or AcadSyntra, automatically) pays the
 * ranked winners; anything unused goes back to the creator. Cash prizes can
 * only be funded with real money (withdrawable earnings or wallet top-ups),
 * never with study credit, so credit can't be turned into cash.
 */
class ChallengePrizeService
{
    public const MAX_PRIZES = 10;

    public function __construct(
        private readonly ReadEarnSettings $settings,
        private readonly WalletService $wallets,
        private readonly NotificationService $notifications,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->bool('knowledge_challenge_prizes_enabled');
    }

    /** @return array{total:int,fee:int,charge:int,fee_percent:int} */
    public function quote(array $prizesMinor): array
    {
        $total = (int) array_sum($prizesMinor);
        $percent = $this->settings->int('knowledge_challenge_prize_fee_percent');
        $fee = (int) (intdiv($total * $percent + 99, 100));

        return ['total' => $total, 'fee' => $fee, 'charge' => $total + $fee, 'fee_percent' => $percent];
    }

    /**
     * Saves (or clears) the prize plan while it is not yet funded.
     *
     * @param list<int> $prizesMinor
     */
    public function configure(AcademicChallenge $challenge, User $actor, ?string $type, array $prizesMinor): ?ChallengePrizeFund
    {
        $fund = ChallengePrizeFund::query()->where('academic_challenge_id', $challenge->id)->first();
        if ($fund && $fund->status !== 'unfunded') {
            $same = $fund->prize_type === $type && array_map('intval', (array) $fund->prizes) === array_map('intval', $prizesMinor);
            if (! $same && ($type || $prizesMinor)) {
                throw ValidationException::withMessages(['prize_type' => 'Prizes are already funded and locked. They can no longer be changed.']);
            }

            return $fund;
        }
        if (! $type || $type === 'none' || $prizesMinor === []) {
            $fund?->delete();

            return null;
        }
        if (! $this->enabled()) {
            throw ValidationException::withMessages(['prize_type' => 'Funded prizes are switched off right now.']);
        }
        if (! array_key_exists($type, ChallengePrizeFund::TYPES)) {
            throw ValidationException::withMessages(['prize_type' => 'Choose cash or study credit.']);
        }
        if (count($prizesMinor) > self::MAX_PRIZES) {
            throw ValidationException::withMessages(['prizes' => 'Up to '.self::MAX_PRIZES.' prizes.']);
        }
        $min = $this->settings->minor('knowledge_challenge_prize_min');
        foreach ($prizesMinor as $amount) {
            if ($amount < $min) {
                throw ValidationException::withMessages(['prizes' => 'Each prize must be at least NGN '.Money::fromMinor($min).'.']);
            }
        }
        $quote = $this->quote($prizesMinor);

        return ChallengePrizeFund::updateOrCreate(['academic_challenge_id' => $challenge->id], [
            'funder_id' => $actor->id, 'prize_type' => $type, 'prizes' => array_values($prizesMinor), 'total_minor' => $quote['total'],
            'fee_percent' => $quote['fee_percent'], 'fee_minor' => $quote['fee'], 'status' => 'unfunded',
        ]);
    }

    /** How much of the user's wallet balance came from real top-ups and is still free to fund cash prizes. */
    public function cashTopUpAllowance(User $user): int
    {
        $account = WalletAccount::query()->where('user_id', $user->id)->first();
        if (! $account) {
            return 0;
        }
        $topUps = (int) round(100 * (float) DB::table('wallet_ledger_entries')->where('wallet_account_id', $account->id)
            ->where('entry_type', 'wallet_funding')->where('direction', 'credit')->where('status', 'posted')->sum('amount'));
        $used = 0;
        ChallengePrizeFund::query()->where('funder_id', $user->id)->where('prize_type', 'cash')->whereIn('status', ['funded', 'settled'])
            ->get(['from_spending_minor', 'from_earnings_minor', 'refunded_minor'])
            ->each(function (ChallengePrizeFund $fund) use (&$used): void {
                $refundedToSpending = max(0, $fund->refunded_minor - $fund->from_earnings_minor);
                $used += $fund->from_spending_minor - $refundedToSpending;
            });

        return max(0, $topUps - $used);
    }

    /** @return array{charge:int,from_earnings:int,from_spending:int,shortfall:int} */
    public function fundingPlan(ChallengePrizeFund $fund, User $user): array
    {
        $account = $this->wallets->account($user);
        $charge = $fund->total_minor + $fund->fee_minor;
        $earnings = max(0, (int) $account->available_earnings_minor);
        $spending = max(0, (int) $account->spending_balance_minor);
        if ($fund->prize_type === 'cash') {
            $fromEarnings = min($earnings, $charge);
            $fromSpending = min($charge - $fromEarnings, $spending, $this->cashTopUpAllowance($user));
        } else {
            $fromSpending = min($spending, $charge);
            $fromEarnings = min($earnings, $charge - $fromSpending);
        }

        return ['charge' => $charge, 'from_earnings' => $fromEarnings, 'from_spending' => $fromSpending, 'shortfall' => max(0, $charge - $fromEarnings - $fromSpending)];
    }

    public function fund(AcademicChallenge $challenge, User $user): ChallengePrizeFund
    {
        abort_unless($challenge->organizer_id === $user->id, 403, 'Only the challenge creator can fund its prizes.');

        $fund = DB::transaction(function () use ($challenge, $user): ChallengePrizeFund {
            $fund = ChallengePrizeFund::query()->where('academic_challenge_id', $challenge->id)->lockForUpdate()->first();
            if (! $fund || $fund->status !== 'unfunded') {
                throw ValidationException::withMessages(['fund' => 'There is nothing to fund.']);
            }
            // Re-quote with the current fee so the creator pays what they were shown.
            $quote = $this->quote(array_map('intval', (array) $fund->prizes));
            $fund->forceFill(['fee_percent' => $quote['fee_percent'], 'fee_minor' => $quote['fee'], 'total_minor' => $quote['total']]);
            $plan = $this->fundingPlan($fund, $user);
            if ($plan['shortfall'] > 0) {
                throw ValidationException::withMessages(['fund' => ($fund->prize_type === 'cash'
                    ? 'Cash prizes must be paid with money you added to your wallet or withdrawable earnings (not study credit). '
                    : '').'Add NGN '.Money::fromMinor($plan['shortfall']).' to your wallet, then try again.']);
            }
            $label = 'Prize escrow: '.$challenge->title;
            if ($plan['from_earnings'] > 0) {
                $this->wallets->debitAvailableEarnings($user, $plan['from_earnings'], 'challenge_prize_escrow', $fund, $label, ['challenge_id' => $challenge->id]);
            }
            if ($plan['from_spending'] > 0) {
                $this->wallets->debitSpending($user, $plan['from_spending'], 'challenge_prize_escrow', $fund, null, $label, ['challenge_id' => $challenge->id]);
            }
            $fund->forceFill(['from_earnings_minor' => $plan['from_earnings'], 'from_spending_minor' => $plan['from_spending'], 'status' => 'funded', 'funded_at' => now(), 'funder_id' => $user->id])->save();
            AuditLog::log('challenge.prize.funded', $user->id, ChallengePrizeFund::class, $fund->id, null, ['charge_minor' => $plan['charge'], 'fee_minor' => $fund->fee_minor]);

            return $fund;
        }, 3);

        $this->notifications->send($user, 'challenge_prize_funded', 'Prize funded: '.$challenge->title,
            'NGN '.Money::fromMinor($fund->total_minor).' is now held safely by AcadSyntra. Entrants will see a "Prize guaranteed" badge.',
            ['url' => route('knowledge.challenges.show', $challenge), 'event_key' => 'prize-funded:'.$fund->id]);

        return $fund;
    }

    /**
     * Pays the ranked winners, refunds anything unused and closes the fund.
     * $actor null means the automatic safety payout.
     */
    public function disburse(ChallengePrizeFund $fund, ?User $actor): ChallengePrizeFund
    {
        $challenge = AcademicChallenge::withoutGlobalScopes()->findOrFail($fund->academic_challenge_id);
        if ($actor) {
            abort_unless($actor->id === $challenge->organizer_id || $actor->isSuperAdmin(), 403);
        }
        if ($challenge->status !== 'completed' || ! $challenge->results_published_at) {
            throw ValidationException::withMessages(['fund' => 'Publish the ranked results before paying the winners.']);
        }

        $paid = DB::transaction(function () use ($fund, $challenge, $actor): array {
            $locked = ChallengePrizeFund::query()->whereKey($fund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'funded') {
                throw ValidationException::withMessages(['fund' => 'These prizes have already been settled.']);
            }
            $entries = $challenge->entries()->where('is_final', true)->whereNotNull('rank')->where('user_id', '!=', $challenge->organizer_id)
                ->orderBy('rank')->orderBy('submitted_at')->get(['id', 'user_id', 'rank'])->unique('user_id')->values();
            $awards = [];
            foreach (array_values((array) $locked->prizes) as $index => $amount) {
                $entry = $entries->get($index);
                $winner = $entry ? User::withoutGlobalScopes()->where('is_active', true)->find($entry->user_id) : null;
                if (! $winner || (int) $amount <= 0) {
                    continue;
                }
                $award = ChallengePrizeAward::create([
                    'challenge_prize_fund_id' => $locked->id, 'academic_challenge_entry_id' => $entry->id, 'user_id' => $winner->id, 'rank' => $index + 1,
                    'amount_minor' => (int) $amount, 'prize_type' => $locked->prize_type, 'paid_by' => $actor?->id, 'paid_at' => now(),
                ]);
                $label = 'Prize: '.$challenge->title.' (#'.($index + 1).')';
                $locked->prize_type === 'cash'
                    ? $this->wallets->creditAvailableEarnings($winner, (int) $amount, 'challenge_prize_cash', $award, $label, ['challenge_id' => $challenge->id])
                    : $this->wallets->creditSpending($winner, (int) $amount, 'challenge_prize_credit', $award, null, $label, ['challenge_id' => $challenge->id]);
                $awards[] = $award;
            }
            $disbursed = (int) array_sum(array_map(fn ($a) => $a->amount_minor, $awards));
            $locked->forceFill(['disbursed_minor' => $disbursed])->save();
            $this->refundUnused($locked, $challenge);
            AuditLog::log('challenge.prize.disbursed', $actor?->id, ChallengePrizeFund::class, $locked->id, null, ['winners' => count($awards), 'disbursed_minor' => $disbursed, 'automatic' => $actor === null]);

            return $awards;
        }, 3);

        foreach ($paid as $award) {
            $winner = User::withoutGlobalScopes()->find($award->user_id);
            if ($winner) {
                $this->notifications->send($winner, 'challenge_prize_won', '🏆 You won a prize in '.$challenge->title,
                    'NGN '.Money::fromMinor($award->amount_minor).($award->prize_type === 'cash' ? ' cash is now in your withdrawable earnings.' : ' study credit is now in your wallet.'),
                    ['url' => route('commerce.wallet'), 'event_key' => 'prize-won:'.$award->id]);
            }
        }

        return $fund->fresh();
    }

    /** For a cancelled challenge (or no winners): give the unused prize back to the creator. */
    public function refund(ChallengePrizeFund $fund, ?User $actor): ChallengePrizeFund
    {
        $challenge = AcademicChallenge::withoutGlobalScopes()->findOrFail($fund->academic_challenge_id);
        DB::transaction(function () use ($fund, $challenge, $actor): void {
            $locked = ChallengePrizeFund::query()->whereKey($fund->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'funded') {
                return;
            }
            $this->refundUnused($locked, $challenge);
            AuditLog::log('challenge.prize.refunded', $actor?->id, ChallengePrizeFund::class, $locked->id, null, ['refunded_minor' => $locked->refunded_minor]);
        }, 3);

        return $fund->fresh();
    }

    /** Admin "settle now": pay winners if results are out, refund if the challenge was cancelled. */
    public function settle(ChallengePrizeFund $fund, User $admin): ChallengePrizeFund
    {
        abort_unless($admin->isSuperAdmin(), 403);
        $challenge = AcademicChallenge::withoutGlobalScopes()->findOrFail($fund->academic_challenge_id);

        return match (true) {
            $challenge->status === 'completed' && $challenge->results_published_at !== null => $this->disburse($fund, $admin),
            $challenge->status === 'cancelled' => $this->refund($fund, $admin),
            default => throw ValidationException::withMessages(['fund' => 'The challenge has no published results and is not cancelled yet.']),
        };
    }

    /** Safety net: pays winners automatically N days after results if the creator has not. */
    public function autoDisburse(): int
    {
        $days = $this->settings->int('knowledge_challenge_prize_auto_days');
        if ($days <= 0) {
            return 0;
        }
        $count = 0;
        ChallengePrizeFund::query()->where('status', 'funded')
            ->whereHas('challenge', fn ($q) => $q->where('status', 'completed')->whereNotNull('results_published_at')->where('results_published_at', '<=', now()->subDays($days)))
            ->each(function (ChallengePrizeFund $fund) use (&$count): void {
                try {
                    $this->disburse($fund, null);
                    $count++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $count;
    }

    private function refundUnused(ChallengePrizeFund $fund, AcademicChallenge $challenge): void
    {
        $unused = max(0, $fund->total_minor - $fund->disbursed_minor - $fund->refunded_minor);
        if ($unused > 0) {
            $creator = User::withoutGlobalScopes()->find($fund->funder_id);
            if ($creator) {
                // Money that came from withdrawable earnings goes back there first.
                $toEarnings = min($unused, max(0, $fund->from_earnings_minor - min($fund->refunded_minor, $fund->from_earnings_minor)));
                $toSpending = $unused - $toEarnings;
                $label = 'Unused prize refund: '.$challenge->title;
                if ($toEarnings > 0) {
                    $this->wallets->creditAvailableEarnings($creator, $toEarnings, 'challenge_prize_refund', $fund, $label, ['challenge_id' => $challenge->id]);
                }
                if ($toSpending > 0) {
                    $this->wallets->creditSpending($creator, $toSpending, 'challenge_prize_refund', $fund, null, $label, ['challenge_id' => $challenge->id]);
                }
                $fund->refunded_minor += $unused;
                DB::afterCommit(fn () => $this->notifications->send($creator, 'challenge_prize_refunded', 'Unused prize refunded',
                    'NGN '.Money::fromMinor($unused).' from '.$challenge->title.' is back in your wallet.', ['url' => route('commerce.wallet'), 'event_key' => 'prize-refund:'.$fund->id]));
            }
        }
        $fund->forceFill(['status' => 'settled', 'settled_at' => now(), 'refunded_minor' => $fund->refunded_minor])->save();
    }
}
