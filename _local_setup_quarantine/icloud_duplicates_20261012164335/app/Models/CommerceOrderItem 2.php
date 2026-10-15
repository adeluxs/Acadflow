<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceOrderItem extends Model
{
    protected $fillable = [
        'commerce_order_id',
        'purchasable_type',
        'purchasable_id',
        'seller_id',
        'title',
        'quantity',
        'unit_price',
        'unit_price_minor',
        'total_price',
        'total_price_minor',
        'metadata',
    ];

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommerceOrder::class, 'commerce_order_id'); }
    public function seller(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function purchasable(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
    public function entitlements(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CommerceEntitlement::class); }
    public function revenueAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(CommerceRevenueAllocation::class); }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'unit_price_minor' => 'integer',
            'total_price_minor' => 'integer',
            'total_price' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
