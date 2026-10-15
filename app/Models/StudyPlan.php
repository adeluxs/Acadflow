<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudyPlan extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'user_id', 'course_id', 'title', 'status', 'items',
        'provider', 'model', 'error_code', 'meta', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'meta' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            if (empty($plan->uuid)) $plan->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function course(): BelongsTo { return $this->belongsTo(Course::class); }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function itemCount(): int
    {
        return is_array($this->items) ? count($this->items) : 0;
    }
}
