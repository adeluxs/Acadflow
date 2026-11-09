<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KnowledgeWritingEntry extends Model
{
    protected $fillable = ['uuid', 'knowledge_writing_challenge_id', 'user_id', 'knowledge_publication_id', 'track_category_id', 'status', 'judge_score',
        'verified_reads', 'reader_score', 'final_score', 'rank', 'award', 'note'];

    protected function casts(): array
    {
        return ['judge_score' => 'float', 'reader_score' => 'float', 'final_score' => 'float', 'verified_reads' => 'integer', 'rank' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $entry) => $entry->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function challenge(): BelongsTo { return $this->belongsTo(KnowledgeWritingChallenge::class, 'knowledge_writing_challenge_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function publication(): BelongsTo { return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id')->withoutGlobalScopes(); }
    public function track(): BelongsTo { return $this->belongsTo(KnowledgeCategory::class, 'track_category_id'); }
    public function scores(): HasMany { return $this->hasMany(KnowledgeWritingScore::class); }
}
