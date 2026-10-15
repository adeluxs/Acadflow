<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DiscussionReply extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'discussion_id',
        'user_id',
        'parent_reply_id',
        'content',
        'type',
        'is_accepted',
        'like_count',
        'accepted_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reply) {
            if (empty($reply->uuid)) {
                $reply->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_accepted' => 'boolean',
            'accepted_at' => 'datetime',
            'like_count' => 'integer',
        ];
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscussionReply::class, 'parent_reply_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(DiscussionReply::class, 'parent_reply_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
