<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A saved, course-scoped study companion thread owned by a student.
 */
class StudyConversation extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'title'];

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (empty($conversation->uuid)) {
                $conversation->uuid = (string) Str::uuid();
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

    public function notes(): HasMany
    {
        return $this->hasMany(StudyNote::class, 'conversation_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
