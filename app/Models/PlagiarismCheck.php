<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlagiarismCheck extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'requested_by',
        'subject_type',
        'subject_id',
        'provider',
        'status',
        'similarity_score',
        'risk_level',
        'summary',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'similarity_score' => 'decimal:2',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function matches(): HasMany { return $this->hasMany(PlagiarismMatch::class)->orderByDesc('similarity_score'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
