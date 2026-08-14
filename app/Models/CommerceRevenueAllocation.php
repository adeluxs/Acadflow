<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommerceRevenueAllocation extends Model
{
    protected $fillable = [
        'commerce_order_item_id',
        'beneficiary_user_id',
        'beneficiary_university_id',
        'allocation_type',
        'percentage',
        'amount',
        'status',
        'released_at',
        'metadata',
    ];

    public function orderItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommerceOrderItem::class, 'commerce_order_item_id'); }
    public function beneficiaryUser(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'beneficiary_user_id'); }
    public function beneficiaryUniversity(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(University::class, 'beneficiary_university_id'); }

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
