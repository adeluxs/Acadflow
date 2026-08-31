<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommerceOrder extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'buyer_id',
        'order_number',
        'currency',
        'subtotal',
        'subtotal_minor',
        'discount_amount',
        'discount_amount_minor',
        'tax_amount',
        'tax_amount_minor',
        'total_amount',
        'total_amount_minor',
        'status',
        'payment_status',
        'transaction_id',
        'billing_details',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'subtotal_minor' => 'integer',
            'discount_amount_minor' => 'integer',
            'tax_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'billing_details' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function buyer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function university(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(University::class); }
    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Transaction::class); }
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CommerceOrderItem::class); }
    public function refunds(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CommerceRefund::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
