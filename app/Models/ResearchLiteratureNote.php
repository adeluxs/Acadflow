<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ResearchLiteratureNote extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'academic_reference_id',
        'created_by',
        'summary',
        'methodology',
        'findings',
        'limitations',
        'contradictions',
        'research_gap',
        'keywords',
        'ai_analysis_id',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function reference(): BelongsTo { return $this->belongsTo(AcademicReference::class, 'academic_reference_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
