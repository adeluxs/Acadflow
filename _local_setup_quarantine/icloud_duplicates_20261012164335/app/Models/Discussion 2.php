<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Discussion extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'course_id',
        'semester_id',
        'user_id',
        'material_id',
        'title',
        'content',
        'status',
        'priority',
        'is_pinned',
        'resolved_at',
        'resolved_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $discussion) {
            if (empty($discussion->uuid)) {
                $discussion->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'material_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class);
    }

    /** Legacy replies are read-only after the shared-engagement migration. */
    public function engagementThread(): HasOne
    {
        return $this->hasOne(EngagementThread::class, 'target_id')->where('target_type', $this->getMorphClass());
    }

    public function tags()
    {
        return $this->belongsToMany(DiscussionTag::class, 'discussion_tag_discussion');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved' && $this->resolved_at !== null;
    }
}
