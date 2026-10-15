<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionExtension extends Model
{
    protected $fillable = [
        'submission_task_id',
        'student_id',
        'granted_by',
        'original_deadline',
        'extended_deadline',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_deadline' => 'datetime',
            'extended_deadline' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(SubmissionTask::class, 'submission_task_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function approve(): bool
    {
        return $this->update(['status' => 'approved']);
    }

    public function reject(): bool
    {
        return $this->update(['status' => 'rejected']);
    }
}
