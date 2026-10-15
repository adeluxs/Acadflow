<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeCommunityPost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'knowledge_community_id',
        'author_id',
        'content_document_id',
        'post_type',
        'title',
        'status',
        'is_pinned',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCommunity::class, 'knowledge_community_id'); }
    public function author(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function pollOptions(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(KnowledgePollOption::class)->orderBy('position'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
