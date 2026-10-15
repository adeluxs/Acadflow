<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SecureDownloadToken extends Model
{
    protected $fillable = [
        'uuid',
        'token_hash',
        'user_id',
        'media_asset_id',
        'commerce_entitlement_id',
        'max_downloads',
        'download_count',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'max_downloads' => 'integer',
            'download_count' => 'integer',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function mediaAsset(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function entitlement(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(CommerceEntitlement::class, 'commerce_entitlement_id'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
