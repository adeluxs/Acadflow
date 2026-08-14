<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiGroundingSession extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'user_id',
        'feature',
        'subject_type',
        'subject_id',
        'question',
        'answer',
        'status',
        'provider',
        'confidence',
        'human_review_required',
        'metadata',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'human_review_required' => 'boolean',
            'metadata' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function sources(): HasMany { return $this->hasMany(AiGroundingSource::class); }
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function subject(): \Illuminate\Database\Eloquent\Relations\MorphTo { return $this->morphTo(); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
