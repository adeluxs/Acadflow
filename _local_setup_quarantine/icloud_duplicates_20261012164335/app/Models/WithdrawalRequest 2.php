<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'uuid',
        'wallet_account_id',
        'payout_account_id',
        'processed_by',
        'amount',
        'amount_minor',
        'fee',
        'fee_minor',
        'status',
        'provider_reference',
        'note',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'fee_minor' => 'integer',
            'fee' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WalletAccount::class, 'wallet_account_id'); }
    public function payoutAccount(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(PayoutAccount::class); }
    public function processor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
