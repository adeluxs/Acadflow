<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeCommunity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'category_id',
        'owner_id',
        'cover_media_id',
        'name',
        'slug',
        'description',
        'community_type',
        'visibility',
        'membership_mode',
        'requires_moderation',
        'status',
        'member_count',
        'rules',
        'settings',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'requires_moderation' => 'boolean',
            'member_count' => 'integer',
            'published_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function university(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(University::class); }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Department::class); }
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCategory::class); }
    public function coverMedia(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class, 'cover_media_id'); }
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(KnowledgeTag::class, 'knowledge_community_tag')->withTimestamps(); }
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function members(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(KnowledgeCommunityMember::class); }
    public function posts(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(KnowledgeCommunityPost::class); }
    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(KnowledgeCommunityInvitation::class); }
    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicEvent::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
