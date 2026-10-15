<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateSubmission extends Model
{
    protected $fillable = [
        'submission_id',
        'submission_task_id',
        'submitted_at',
        'deadline_at',
        'minutes_late',
        'penalty_applied_percent',
        'score_before_penalty',
        'score_after_penalty',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'deadline_at' => 'datetime',
            'minutes_late' => 'integer',
            'penalty_applied_percent' => 'decimal:2',
            'score_before_penalty' => 'decimal:2',
            'score_after_penalty' => 'decimal:2',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(SubmissionTask::class, 'submission_task_id');
    }
}
