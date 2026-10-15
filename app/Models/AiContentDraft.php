<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A queued, advisory AI content draft for a lecturer.
 *
 * Status lifecycle: pending -> generating -> ready | failed.
 * A ready draft may then become applied (converted to a course material) or
 * discarded. Drafts are never course content until explicitly applied.
 */
class AiContentDraft extends Model
{
    protected $table = 'ai_content_drafts';

    public const STATUS_PENDING = 'pending';
    public const STATUS_GENERATING = 'generating';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_DISCARDED = 'discarded';

    protected $fillable = [
        'uuid',
        'user_id',
        'university_id',
        'course_id',
        'material_id',
        'feature',
        'action',
        'instruction',
        'content',
        'provider',
        'model',
        'status',
        'error_code',
        'meta',
        'generated_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'generated_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $draft): void {
            if (empty($draft->uuid)) {
                $draft->uuid = (string) Str::uuid();
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

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'material_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && trim((string) $this->content) !== '';
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
