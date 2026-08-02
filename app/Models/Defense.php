<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Defense extends Model
{
    protected $fillable = [
        'submission_id',
        'course_id',
        'lecturer_id',
        'scheduled_at',
        'duration_minutes',
        'venue',
        'status',
        'notes',
        'score',
        'feedback',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $defense) {
            if (empty($defense->uuid)) {
                $defense->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
