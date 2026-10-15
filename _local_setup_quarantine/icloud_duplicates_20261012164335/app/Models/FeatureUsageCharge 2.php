<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeatureUsageCharge extends Model
{
    protected $fillable = [
        'uuid','request_id','user_id','university_id','pricing_rule_id','commercial_account_id',
        'feature_entitlement_id','feature','pricing_type','quantity','unit_amount_minor',
        'total_amount_minor','currency','funding_source','status','metadata','settled_at','released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'total_amount_minor' => 'integer',
            'metadata' => 'array',
            'settled_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function pricingRule() { return $this->belongsTo(PricingRule::class); }
    public function commercialAccount() { return $this->belongsTo(CommercialAccount::class); }
    public function entitlement() { return $this->belongsTo(FeatureEntitlement::class, 'feature_entitlement_id'); }
}
