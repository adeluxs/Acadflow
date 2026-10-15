<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail of every grade recorded for a submission, so a
 * regression in grading can be traced and reviewed.
 */
class SubmissionGradeHistory extends Model
{
    protected $fillable = [
        'submission_id',
        'graded_by',
        'score',
        'max_score',
        'criteria_scores',
        'feedback',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'criteria_scores' => 'array',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
