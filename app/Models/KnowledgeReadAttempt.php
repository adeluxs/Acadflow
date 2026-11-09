<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KnowledgeReadAttempt extends Model
{
    public const STATUS_COUNTED = 'counted';
    public const STATUS_NOT_COUNTED = 'not_counted';
    public const STATUS_FLAGGED = 'flagged';
    public const STATUS_REJECTED = 'rejected';
    /** Passed without a Reader Pass: points wait until the reader gets one. */
    public const STATUS_HELD = 'held';

    protected $fillable = [
        'uuid', 'user_id', 'knowledge_publication_id', 'creator_id', 'period', 'question_count', 'correct_count', 'score_percent',
        'passed', 'reflection', 'reflection_words', 'points', 'credit_minor', 'had_pass', 'counts_for_creator', 'status', 'reason',
        'read_seconds', 'ip_hash', 'answers', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean', 'had_pass' => 'boolean', 'counts_for_creator' => 'boolean', 'answers' => 'array',
            'question_count' => 'integer', 'correct_count' => 'integer', 'score_percent' => 'integer', 'reflection_words' => 'integer',
            'points' => 'integer', 'credit_minor' => 'integer', 'read_seconds' => 'integer', 'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $attempt) => $attempt->uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'creator_id'); }
    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
}
