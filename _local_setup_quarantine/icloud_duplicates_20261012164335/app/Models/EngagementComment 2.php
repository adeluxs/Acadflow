<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EngagementComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'engagement_thread_id',
        'user_id',
        'parent_id',
        'comment_type',
        'section_key',
        'body',
        'status',
        'is_pinned',
        'is_verified_response',
        'resolved_by',
        'resolved_at',
        'edited_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_verified_response' => 'boolean',
            'resolved_at' => 'datetime',
            'edited_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function thread(): BelongsTo { return $this->belongsTo(EngagementThread::class, 'engagement_thread_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->where('status', '!=', 'hidden')->oldest(); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function reactions(): MorphMany { return $this->morphMany(EngagementReaction::class, 'reactable'); }
}
