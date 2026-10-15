<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'knowledge_community_id',
        'research_project_id',
        'course_id',
        'semester_id',
        'name',
        'description',
        'group_type',
        'visibility',
        'membership_mode',
        'leader_id',
        'cover_media_id',
        'status',
        'is_locked',
        'max_members',
        'formed_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'formed_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->uuid)) {
                $group->uuid = Str::uuid()->toString();
            }
        });
    }

    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function community(): BelongsTo { return $this->belongsTo(KnowledgeCommunity::class, 'knowledge_community_id'); }
    public function researchProject(): BelongsTo { return $this->belongsTo(ResearchProject::class); }
    public function coverMedia(): BelongsTo { return $this->belongsTo(MediaAsset::class, 'cover_media_id'); }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function joinRequests(): HasMany { return $this->hasMany(GroupJoinRequest::class); }
    public function invitations(): HasMany { return $this->hasMany(GroupInvitation::class); }
    public function tasks(): HasMany { return $this->hasMany(GroupTask::class); }
    public function resources(): HasMany { return $this->hasMany(GroupResource::class); }
    public function events(): HasMany { return $this->hasMany(AcademicEvent::class); }
    public function challenges(): HasMany { return $this->hasMany(AcademicChallenge::class); }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
