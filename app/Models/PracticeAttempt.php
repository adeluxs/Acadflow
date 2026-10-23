<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PracticeAttempt extends Model
{
    protected $fillable = ['uuid', 'quiz_id', 'user_id', 'score', 'total', 'answers', 'started_at', 'completed_at', 'submission_token'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            if (empty($attempt->uuid)) $attempt->uuid = (string) Str::uuid();
        });
    }

    public function quiz(): BelongsTo { return $this->belongsTo(PracticeQuiz::class, 'quiz_id'); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
