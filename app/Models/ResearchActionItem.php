<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ResearchActionItem extends Model
{
    protected $fillable = [
        'uuid',
        'research_meeting_id',
        'assigned_to',
        'title',
        'description',
        'due_at',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function meeting(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ResearchMeeting::class, 'research_meeting_id'); }
    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
