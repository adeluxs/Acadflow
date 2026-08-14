<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AcademicChallengeEntry extends Model
{
    protected $fillable = [
        'uuid',
        'academic_challenge_id',
        'user_id',
        'team_name',
        'content_document_id',
        'knowledge_publication_id',
        'title',
        'submission_url',
        'status',
        'is_final',
        'score',
        'rank',
        'vote_count',
        'metadata',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'rank' => 'integer',
            'vote_count' => 'integer',
            'is_final' => 'boolean',
            'metadata' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function challenge(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(AcademicChallenge::class, 'academic_challenge_id'); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function publication(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id'); }
    public function document(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ContentDocument::class, 'content_document_id'); }
    public function teamMembers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(User::class, 'academic_challenge_team_members')->withPivot(['role','status'])->withTimestamps(); }
    public function scores(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicChallengeScore::class); }
    public function votes(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(AcademicChallengeVote::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
