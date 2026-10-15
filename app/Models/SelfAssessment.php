<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SelfAssessment extends Model
{
    protected $fillable = ['uuid', 'user_id', 'submission_task_id', 'checklist', 'confidence', 'notes'];

    protected function casts(): array
    {
        return ['checklist' => 'array', 'confidence' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assessment): void {
            if (empty($assessment->uuid)) $assessment->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function task(): BelongsTo { return $this->belongsTo(SubmissionTask::class, 'submission_task_id'); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
