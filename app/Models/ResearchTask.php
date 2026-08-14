<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchTask extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'research_section_id',
        'assigned_by',
        'assigned_to',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'metadata' => 'array',
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
