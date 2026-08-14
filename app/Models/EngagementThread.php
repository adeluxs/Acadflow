<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EngagementThread extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'target_type',
        'target_id',
        'title',
        'visibility',
        'status',
        'is_locked',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'settings' => 'array',
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

    public function comments(): HasMany { return $this->hasMany(EngagementComment::class); }
    public function subscriptions(): HasMany { return $this->hasMany(EngagementSubscription::class, 'subscribable_id')->where('subscribable_type', self::class); }
}
