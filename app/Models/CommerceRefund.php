<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommerceRefund extends Model
{
    protected $fillable = [
        'uuid',
        'commerce_order_id',
        'requested_by',
        'processed_by',
        'transaction_id',
        'amount',
        'amount_minor',
        'reason',
        'status',
        'gateway_refund_id',
        'provider_status',
        'provider_payload',
        'processing_started_at',
        'provider_confirmed_at',
        'reconciliation_required',
        'decision_note',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'amount_minor' => 'integer',
            'provider_payload' => 'array',
            'processing_started_at' => 'datetime',
            'provider_confirmed_at' => 'datetime',
            'reconciliation_required' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function requester(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function processor(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Transaction::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
