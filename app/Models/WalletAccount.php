<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WalletAccount extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'currency',
        'spending_balance_minor',
        'pending_earnings_minor',
        'available_earnings_minor',
        'lifetime_earnings_minor',
        'recovery_debt_minor',
        'ai_monthly_spending_limit_minor',
        'available_balance',
        'pending_balance',
        'lifetime_earnings',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'spending_balance_minor' => 'integer',
            'pending_earnings_minor' => 'integer',
            'available_earnings_minor' => 'integer',
            'lifetime_earnings_minor' => 'integer',
            'recovery_debt_minor' => 'integer',
            'ai_monthly_spending_limit_minor' => 'integer',
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'lifetime_earnings' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletLedgerEntry::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
