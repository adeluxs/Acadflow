<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WalletLedgerEntry extends Model
{
    protected $fillable = [
        'uuid',
        'wallet_account_id',
        'transaction_id',
        'entry_type',
        'direction',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'status',
        'description',
        'metadata',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'metadata' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function wallet(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WalletAccount::class, 'wallet_account_id'); }
    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Transaction::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
