<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** An official, AcadSyntra-run and AcadSyntra-paid Writing Challenge. */
class KnowledgeWritingChallenge extends Model
{
    public const CRITERIA = [
        'accuracy' => 'Accuracy',
        'clarity' => 'Clarity',
        'usefulness' => 'Usefulness to students',
        'originality' => 'Originality & voice',
    ];

    protected $fillable = ['uuid', 'title', 'theme', 'description', 'starts_at', 'submission_deadline', 'results_at', 'tracks', 'prizes', 'track_prize_minor',
        'min_words', 'min_questions', 'judge_weight', 'finalists', 'status', 'results_published_at', 'announced_at', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'submission_deadline' => 'datetime', 'results_at' => 'datetime', 'results_published_at' => 'datetime', 'announced_at' => 'datetime',
            'tracks' => 'array', 'prizes' => 'array', 'track_prize_minor' => 'integer', 'min_words' => 'integer', 'min_questions' => 'integer', 'judge_weight' => 'integer', 'finalists' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $challenge) => $challenge->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }

    public function entries(): HasMany { return $this->hasMany(KnowledgeWritingEntry::class); }

    /** draft, upcoming, open, judging, results or cancelled. */
    public function phase(): string
    {
        return match (true) {
            $this->status === 'cancelled' => 'cancelled',
            $this->status === 'draft' => 'draft',
            $this->results_published_at !== null => 'results',
            now()->lt($this->starts_at) => 'upcoming',
            now()->lte($this->submission_deadline) => 'open',
            default => 'judging',
        };
    }

    public function isVisible(): bool { return in_array($this->phase(), ['upcoming', 'open', 'judging', 'results'], true); }

    public function prizePoolMinor(): int
    {
        return (int) array_sum((array) $this->prizes) + $this->track_prize_minor * count((array) $this->tracks);
    }

    public function scopeKey(): string { return 'writing_challenge:'.$this->id; }
}
