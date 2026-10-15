<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A student-owned study note. Content is sanitized on save.
 */
class StudyNote extends Model
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AI_ANSWER = 'ai_answer';
    public const SOURCE_MATERIAL_SUMMARY = 'material_summary';

    protected $fillable = ['uuid', 'user_id', 'course_id', 'conversation_id', 'title', 'content', 'source'];

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            if (empty($note->uuid)) {
                $note->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(StudyConversation::class, 'conversation_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
