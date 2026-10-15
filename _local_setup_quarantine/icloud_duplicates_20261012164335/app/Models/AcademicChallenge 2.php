<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AcademicChallenge extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'organizer_id',
        'knowledge_community_id',
        'group_id',
        'category_id',
        'cover_media_id',
        'title',
        'slug',
        'description',
        'challenge_type',
        'visibility',
        'participation_mode',
        'status',
        'starts_at',
        'ends_at',
        'submission_deadline',
        'rules',
        'eligibility_rules',
        'max_team_members',
        'judging_criteria',
        'rewards',
        'public_voting_enabled',
        'ai_assistance_enabled',
        'requires_moderation',
        'published_at',
        'results_published_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'submission_deadline' => 'datetime',
            'published_at' => 'datetime',
            'results_published_at' => 'datetime',
            'rules' => 'array',
            'eligibility_rules' => 'array',
            'max_team_members' => 'integer',
            'judging_criteria' => 'array',
            'rewards' => 'array',
            'public_voting_enabled' => 'boolean',
            'ai_assistance_enabled' => 'boolean',
            'requires_moderation' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function university(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(University::class); }
    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Department::class); }
    public function community(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCommunity::class, 'knowledge_community_id'); }
    public function group(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Group::class); }
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgeCategory::class); }
    public function coverMedia(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(MediaAsset::class, 'cover_media_id'); }
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(KnowledgeTag::class, 'academic_challenge_tag')->withTimestamps(); }
    public function organizer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function judges(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(User::class, 'academic_challenge_judges')->withPivot(['status','invited_by','accepted_at'])->withTimestamps(); }
    public function entries(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicChallengeEntry::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
