<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KnowledgeReadingChallenge extends Model
{
    public const STATUSES = ['draft', 'active', 'completed', 'cancelled'];

    protected $fillable = ['uuid', 'title', 'description', 'starts_at', 'ends_at', 'prize_pool_minor', 'prizes', 'min_passed_reads', 'university_id', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'prizes' => 'array', 'prize_pool_minor' => 'integer', 'min_passed_reads' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $challenge) => $challenge->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function university(): BelongsTo { return $this->belongsTo(University::class); }

    public function isRunning(): bool
    {
        return $this->status === 'active' && now()->between($this->starts_at, $this->ends_at);
    }

    public function scopeKey(): string { return 'reading_challenge:'.$this->id; }
}
