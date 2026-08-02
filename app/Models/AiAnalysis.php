<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AiAnalysis extends Model
{
    protected $fillable = [
        'uuid',
        'submission_id',
        'user_id',
        'feature',
        'status',
        'source',
        'score',
        'issue_count',
        'issues',
        'summary',
        'data',
        'attempts',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'issues' => 'array',
            'data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $analysis) {
            if (empty($analysis->uuid)) {
                $analysis->uuid = (string) Str::uuid();
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function markCompleted(string $source, ?float $score, array $issues, ?string $summary, array $data): void
    {
        $this->update([
            'status' => 'completed',
            'source' => $source,
            'score' => $score,
            'issue_count' => count($issues),
            'issues' => $issues,
            'summary' => $summary,
            'data' => $data,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(): void
    {
        $this->update(['status' => 'failed', 'attempts' => $this->attempts + 1]);
    }
}
