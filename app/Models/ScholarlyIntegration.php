<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScholarlyIntegration extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'provider',
        'name',
        'is_active',
        'is_default',
        'credentials',
        'settings',
        'last_checked_at',
        'health_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'last_checked_at' => 'datetime',
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
}
