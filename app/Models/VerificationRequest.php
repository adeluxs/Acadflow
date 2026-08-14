<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VerificationRequest extends Model
{
    protected $fillable = [
        'uuid',
        'university_id',
        'user_id',
        'verification_type',
        'statement',
        'evidence',
        'workflow_instance_id',
        'status',
        'reviewed_by',
        'review_note',
        'reviewed_at',
        'suspended_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid());
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function workflowInstance(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(WorkflowInstance::class); }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
