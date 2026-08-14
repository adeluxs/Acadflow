<?php

namespace App\Services\Commerce;

use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function account(User $user, string $currency = 'NGN'): WalletAccount
    {
        return WalletAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => strtoupper($currency), 'status' => 'active']
        );
    }

    public function credit(User $user, float $amount, string $entryType, ?Model $reference = null, ?Transaction $transaction = null, string $description = '', bool $pending = false, array $metadata = []): WalletLedgerEntry
    {
        return $this->post($user, abs($amount), 'credit', $entryType, $reference, $transaction, $description, $pending, $metadata);
    }

    public function debit(User $user, float $amount, string $entryType, ?Model $reference = null, ?Transaction $transaction = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        return $this->post($user, abs($amount), 'debit', $entryType, $reference, $transaction, $description, false, $metadata);
    }

    public function debitReversal(User $user, float $amount, string $entryType, ?Model $reference = null, string $description = '', array $metadata = []): WalletLedgerEntry
    {
        $amount = round(abs($amount), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($user, $amount, $entryType, $reference, $description, $metadata) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = $this->account($user);
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }
            abort_if($wallet->status !== 'active', 423, 'Wallet access is restricted.');
            $wallet->available_balance = round((float) $wallet->available_balance - $amount, 2);
            $wallet->save();

            return WalletLedgerEntry::create([
                'wallet_account_id' => $wallet->id,
                'entry_type' => $entryType,
                'direction' => 'debit',
                'amount' => $amount,
                'balance_after' => $wallet->available_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'status' => 'posted',
                'description' => $description,
                'metadata' => array_merge($metadata, ['may_create_recovery_balance' => true]),
                'posted_at' => now(),
            ]);
        });
    }

    public function releasePending(User $user, float $amount, ?Model $reference = null, string $description = ''): WalletLedgerEntry
    {
        return DB::transaction(function () use ($user, $amount, $reference, $description) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $amount = round(abs($amount), 2);
            if ((float) $wallet->pending_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Pending wallet balance is insufficient.']);
            }
            $wallet->pending_balance = round((float) $wallet->pending_balance - $amount, 2);
            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->save();

            return WalletLedgerEntry::create([
                'wallet_account_id' => $wallet->id,
                'entry_type' => 'earning_release',
                'direction' => 'credit',
                'amount' => $amount,
                'balance_after' => $wallet->available_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'status' => 'posted',
                'description' => $description ?: 'Pending earnings released.',
                'posted_at' => now(),
            ]);
        });
    }

    private function post(User $user, float $amount, string $direction, string $entryType, ?Model $reference, ?Transaction $transaction, string $description, bool $pending, array $metadata): WalletLedgerEntry
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($user, $amount, $direction, $entryType, $reference, $transaction, $description, $pending, $metadata) {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = $this->account($user);
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }
            abort_if($wallet->status !== 'active', 423, 'Wallet access is restricted.');

            if ($direction === 'debit') {
                if ((float) $wallet->available_balance < $amount) {
                    throw ValidationException::withMessages(['amount' => 'Available wallet balance is insufficient.']);
                }
                $wallet->available_balance = round((float) $wallet->available_balance - $amount, 2);
            } elseif ($pending) {
                $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
                $wallet->lifetime_earnings = round((float) $wallet->lifetime_earnings + $amount, 2);
            } else {
                $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
                if (str_contains($entryType, 'earning') || str_contains($entryType, 'sale')) {
                    $wallet->lifetime_earnings = round((float) $wallet->lifetime_earnings + $amount, 2);
                }
            }
            $wallet->save();

            return WalletLedgerEntry::create([
                'wallet_account_id' => $wallet->id,
                'transaction_id' => $transaction?->id,
                'entry_type' => $entryType,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $wallet->available_balance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'status' => $pending ? 'pending' : 'posted',
                'description' => $description,
                'metadata' => $metadata,
                'posted_at' => now(),
            ]);
        });
    }
}
