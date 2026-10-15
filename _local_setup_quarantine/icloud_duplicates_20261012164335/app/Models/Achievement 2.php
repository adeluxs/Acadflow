<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Achievement extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'key',
        'name',
        'description',
        'icon',
        'category',
        'criteria',
        'points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'points' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function users(): BelongsToMany { return $this->belongsToMany(User::class)->withPivot(['evidence', 'awarded_at', 'awarded_by']); }
}
