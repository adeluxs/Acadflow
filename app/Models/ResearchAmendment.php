<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchAmendment extends Model
{
    protected $fillable = [
        'uuid',
        'research_project_id',
        'research_archive_id',
        'requested_by',
        'workflow_instance_id',
        'reason',
        'requested_changes',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_changes' => 'array',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
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
