<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchMilestone extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'workflow_stage_id',
        'title',
        'description',
        'weight',
        'status',
        'due_at',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
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
