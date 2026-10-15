<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudyReflection extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'prompt', 'reflection', 'ai_feedback'];

    protected static function booted(): void
    {
        static::creating(function (self $reflection): void {
            if (empty($reflection->uuid)) $reflection->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function getRouteKeyName(): string { return 'uuid'; }
}
