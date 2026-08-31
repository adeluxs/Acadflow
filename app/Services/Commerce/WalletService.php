<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Support\Money;
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wallet domain service.
 *
 * New money movement is integer-minor-unit only. Legacy decimal wallet fields
 * are mirrored for backwards-compatible reports/UI while the migration is in
 * progress; they are not used as the authoritative balance.
 */
class WalletService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function account(User $user, ?string $currency = null): WalletAccount
    {
        $resolvedCurrency=strtoupper((string)($currency ?: SettingService::get('currency','NGN',$user->university_id)));
        return WalletAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => $resolvedCurrency, 'status' => 'active']
        );
    }

    public function creditSpending(User $user, int $amountMinor, string $entryType, ?Model $reference = null, ?Transaction $transaction = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        return $this->mutate($user, 'spending', $amountMinor, 'credit', $entryType, $reference, $transaction, $description, $metadata);
    }

    public function debitSpending(User $user, int $amountMinor, string $entryType, ?Model $reference = null, ?Transaction $transaction = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        return $this->mutate($user, 'spending', $amountMinor, 'debit', $entryType, $reference, $transaction, $description, $metadata);
    }

    public function creditPendingEarnings(User $user, int $amountMinor, string $entryType, ?Model $reference = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        return $this->mutate($user, 'pending_earnings', $amountMinor, 'credit', $entryType, $reference, null, $description, $metadata, true);
    }

    public function creditAvailableEarnings(User $user, int $amountMinor, string $entryType, ?Model $reference = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        return $this->mutate($user, 'available_earnings', $amountMinor, 'credit', $entryType, $reference, null, $description, $metadata);
    }

    public function debitPendingEarnings(User $user, int $amountMinor, string $entryType, ?Model $reference = null, string $description = '', array $metadata = [], bool $allowRecoveryBalance = false): WalletLedgerEntry
    {
        return $this->mutate($user, 'pending_earnings', $amountMinor, 'debit', $entryType, $reference, null, $description, $metadata, false, $allowRecoveryBalance);
    }

    public function debitAvailableEarnings(User $user, int $amountMinor, string $entryType, ?Model $reference = null, string $description = '', array $metadata = [], bool $allowRecoveryBalance = false): WalletLedgerEntry
    {
        return $this->mutate($user, 'available_earnings', $amountMinor, 'debit', $entryType, $reference, null, $description, $metadata, false, $allowRecoveryBalance);
    }

    public function releasePending(User $user, int $amountMinor, ?Model $reference = null, string $description = ''): WalletLedgerEntry
    {
        $this->assertPositive($amountMinor);

        return DB::transaction(function () use ($user, $amountMinor, $reference, $description) {
            $wallet = $this->lockedAccount($user);
            if ((int) $wallet->pending_earnings_minor < $amountMinor) {
                throw ValidationException::withMessages(['amount' => 'Pending earnings balance is insufficient.']);
            }

            $wallet->pending_earnings_minor = (int) $wallet->pending_earnings_minor - $amountMinor;
            $wallet->available_earnings_minor = (int) $wallet->available_earnings_minor + $amountMinor;
            $this->syncLegacyBalances($wallet);
            $wallet->save();

            $entry = $this->legacyEntry($wallet, 'available_earnings', $amountMinor, 'credit', 'earning_release', $reference, null, $description ?: 'Pending earnings released.');

            $ref = 'earning-release:'.($reference?->getMorphClass() ?? 'wallet').':'.($reference?->getKey() ?? $entry->id).':'.$wallet->id;
            $this->ledger->post($ref, 'earning_release', $wallet->currency, [
                ['wallet_account_id'=>$wallet->id,'account_code'=>'user_pending_earnings_liability','direction'=>'debit','amount_minor'=>$amountMinor],
                ['wallet_account_id'=>$wallet->id,'account_code'=>'user_available_earnings_liability','direction'=>'credit','amount_minor'=>$amountMinor],
            ], $user, ['reference_type'=>$reference?->getMorphClass(),'reference_id'=>$reference?->getKey()]);

            return $entry;
        }, 3);
    }

    public function totalSpendableMinor(User $user): int
    {
        $wallet = $this->account($user);
        return (int) $wallet->spending_balance_minor;
    }

    private function mutate(User $user, string $bucket, int $amountMinor, string $direction, string $entryType, ?Model $reference, ?Transaction $transaction, string $description, array $metadata, bool $lifetimeEarning = false, bool $allowRecoveryBalance = false): WalletLedgerEntry
    {
        $this->assertPositive($amountMinor);

        return DB::transaction(function () use ($user,$bucket,$amountMinor,$direction,$entryType,$reference,$transaction,$description,$metadata,$lifetimeEarning,$allowRecoveryBalance) {
            $wallet = $this->lockedAccount($user);
            $column = match ($bucket) {
                'spending' => 'spending_balance_minor',
                'pending_earnings' => 'pending_earnings_minor',
                'available_earnings' => 'available_earnings_minor',
                default => throw new \InvalidArgumentException('Unsupported wallet balance bucket.'),
            };

            $current = (int) $wallet->{$column};
            if ($direction === 'debit' && ! $allowRecoveryBalance && $current < $amountMinor) {
                throw ValidationException::withMessages(['amount' => match ($bucket) {
                    'spending' => 'Your spending balance is too low for this action.',
                    'available_earnings' => 'Available earnings are insufficient.',
                    default => 'Wallet balance is insufficient.',
                }]);
            }

            $isEarningsBucket = in_array($bucket, ['pending_earnings', 'available_earnings'], true);
            $debtApplied = 0;
            $recoveryShortfall = 0;
            $visibleMovement = $amountMinor;

            if ($direction === 'credit') {
                // Recovery debt represents creator earnings that were already withdrawn
                // before a later refund/reversal. Future creator earnings automatically
                // repay that receivable before becoming withdrawable again.
                if ($isEarningsBucket && (int) $wallet->recovery_debt_minor > 0) {
                    $debtApplied = min((int) $wallet->recovery_debt_minor, $amountMinor);
                    $wallet->recovery_debt_minor = (int) $wallet->recovery_debt_minor - $debtApplied;
                }
                $visibleMovement = $amountMinor - $debtApplied;
                $wallet->{$column} = $current + $visibleMovement;
            } else {
                $visibleMovement = min($current, $amountMinor);
                $recoveryShortfall = $allowRecoveryBalance ? max(0, $amountMinor - $current) : 0;
                $wallet->{$column} = $current - $visibleMovement;
                if ($recoveryShortfall > 0) {
                    $wallet->recovery_debt_minor = (int) $wallet->recovery_debt_minor + $recoveryShortfall;
                }
            }

            if ($lifetimeEarning) {
                // Lifetime earnings tracks gross legitimate earnings even when part of
                // the current earning is applied to an outstanding recovery debt.
                $wallet->lifetime_earnings_minor = (int) $wallet->lifetime_earnings_minor + $amountMinor;
            }
            $this->syncLegacyBalances($wallet);
            $wallet->save();

            $entryMetadata = $metadata;
            if ($debtApplied > 0) $entryMetadata['recovery_debt_applied_minor'] = $debtApplied;
            if ($recoveryShortfall > 0) $entryMetadata['recovery_debt_created_minor'] = $recoveryShortfall;
            $entry = $this->legacyEntry($wallet, $bucket, $amountMinor, $direction, $entryType, $reference, $transaction, $description, $entryMetadata);

            [$debitCode,$creditCode] = $this->ledgerAccounts($bucket,$direction,$entryType);
            $postings = [];
            if ($direction === 'credit') {
                $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>$debitCode,'direction'=>'debit','amount_minor'=>$amountMinor];
                if ($debtApplied > 0) {
                    $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>'creator_recovery_receivable','direction'=>'credit','amount_minor'=>$debtApplied];
                }
                if ($visibleMovement > 0) {
                    $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>$creditCode,'direction'=>'credit','amount_minor'=>$visibleMovement];
                }
            } else {
                if ($visibleMovement > 0) {
                    $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>$debitCode,'direction'=>'debit','amount_minor'=>$visibleMovement];
                }
                if ($recoveryShortfall > 0) {
                    $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>'creator_recovery_receivable','direction'=>'debit','amount_minor'=>$recoveryShortfall];
                }
                $postings[] = ['wallet_account_id'=>$wallet->id,'account_code'=>$creditCode,'direction'=>'credit','amount_minor'=>$amountMinor];
            }

            $ref = 'wallet-entry:'.$entry->uuid;
            $this->ledger->post($ref, $entryType, $wallet->currency, $postings, $user, [
                'wallet_entry_uuid'=>$entry->uuid,
                'reference_type'=>$reference?->getMorphClass(),
                'reference_id'=>$reference?->getKey(),
                'recovery_debt_applied_minor'=>$debtApplied,
                'recovery_debt_created_minor'=>$recoveryShortfall,
            ] + $metadata);

            return $entry;
        }, 3);
    }

    private function lockedAccount(User $user): WalletAccount
    {
        $wallet = WalletAccount::query()->where('user_id',$user->id)->lockForUpdate()->first();
        if (! $wallet) {
            $wallet = $this->account($user);
            $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }
        abort_if($wallet->status !== 'active', 423, 'Wallet access is restricted.');
        return $wallet;
    }

    private function legacyEntry(WalletAccount $wallet, string $bucket, int $amountMinor, string $direction, string $entryType, ?Model $reference, ?Transaction $transaction, string $description, array $metadata = []): WalletLedgerEntry
    {
        $balanceMinor = match($bucket){
            'spending'=>(int)$wallet->spending_balance_minor,
            'pending_earnings'=>(int)$wallet->pending_earnings_minor,
            default=>(int)$wallet->available_earnings_minor,
        };
        return WalletLedgerEntry::create([
            'wallet_account_id'=>$wallet->id,'transaction_id'=>$transaction?->id,'entry_type'=>$entryType,'direction'=>$direction,
            'balance_bucket'=>$bucket,'amount_minor'=>$amountMinor,'balance_after_minor'=>$balanceMinor,
            'amount'=>Money::fromMinor($amountMinor),'balance_after'=>Money::fromMinor($balanceMinor),
            'reference_type'=>$reference?->getMorphClass(),'reference_id'=>$reference?->getKey(),'status'=>'posted',
            'description'=>$description,'metadata'=>$metadata,'posted_at'=>now(),
        ]);
    }

    private function syncLegacyBalances(WalletAccount $wallet): void
    {
        $wallet->available_balance = Money::fromMinor((int)$wallet->spending_balance_minor + (int)$wallet->available_earnings_minor);
        $wallet->pending_balance = Money::fromMinor((int)$wallet->pending_earnings_minor);
        $wallet->lifetime_earnings = Money::fromMinor((int)$wallet->lifetime_earnings_minor);
    }

    /** @return array{0:string,1:string} */
    private function ledgerAccounts(string $bucket,string $direction,string $entryType): array
    {
        $liability = match($bucket){
            'spending'=>'user_spending_liability','pending_earnings'=>'user_pending_earnings_liability',default=>'user_available_earnings_liability'
        };
        $counter = match(true){
            str_contains($entryType,'fund')=>'payment_clearing',
            str_contains($entryType,'purchase')=>'marketplace_clearing',
            str_contains($entryType,'withdraw')=>'payout_clearing',
            str_contains($entryType,'refund')=>'refund_clearing',
            str_contains($entryType,'ai_')=>'ai_revenue_clearing',
            str_contains($entryType,'earning') || str_contains($entryType,'sale')=>'marketplace_clearing',
            default=>'wallet_clearing',
        };
        return $direction==='credit' ? [$counter,$liability] : [$liability,$counter];
    }

    private function assertPositive(int $amountMinor): void
    {
        if($amountMinor<=0) throw ValidationException::withMessages(['amount'=>'Amount must be greater than zero.']);
    }
}
