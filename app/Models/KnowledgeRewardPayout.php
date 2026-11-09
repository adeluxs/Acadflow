<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KnowledgeRewardPayout extends Model
{
    public const KINDS = [
        'reader_pool' => 'Monthly reader pool',
        'creator_pool' => 'Monthly creator pool',
        'reading_challenge' => 'Reading Challenge prize',
        'writing_challenge' => 'Writing Challenge prize',
    ];

    protected $fillable = ['uuid', 'user_id', 'kind', 'scope_key', 'rank', 'points', 'amount_minor', 'status', 'note', 'metadata', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'paid_at' => 'datetime', 'rank' => 'integer', 'points' => 'integer', 'amount_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $payout) => $payout->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
