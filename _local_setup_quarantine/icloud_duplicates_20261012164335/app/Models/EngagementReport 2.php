<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class EngagementReport extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'reporter_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'reviewed_by',
        'resolution',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reporter_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function reportable(): MorphTo { return $this->morphTo(); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
