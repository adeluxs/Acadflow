<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommerceEntitlement extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'commerce_order_item_id',
        'entitled_type',
        'entitled_id',
        'access_level',
        'status',
        'starts_at',
        'expires_at',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function orderItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommerceOrderItem::class, 'commerce_order_item_id'); }
    public function entitled(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }
    public function downloadTokens(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(SecureDownloadToken::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
