<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PracticeQuiz extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'material_id', 'title', 'topic',
        'difficulty', 'status', 'questions', 'provider', 'model', 'error_code',
        'study_session_id', 'next_review_at', 'last_practiced_at', 'review_interval_days',
    ];

    protected function casts(): array
    {
        return ['questions' => 'array', 'next_review_at' => 'datetime', 'last_practiced_at' => 'datetime', 'review_interval_days' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $quiz): void {
            if (empty($quiz->uuid)) $quiz->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function material(): BelongsTo { return $this->belongsTo(CourseMaterial::class, 'material_id'); }

    public function attempts(): HasMany { return $this->hasMany(PracticeAttempt::class, 'quiz_id'); }
    public function studySession(): BelongsTo { return $this->belongsTo(StudentStudySession::class, 'study_session_id'); }

    public function questionCount(): int
    {
        return is_array($this->questions) ? count($this->questions) : 0;
    }

    public function getRouteKeyName(): string { return 'uuid'; }
}
