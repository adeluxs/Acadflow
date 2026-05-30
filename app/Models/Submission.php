<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'course_id',
        'semester_id',
        'group_id',
        'submission_task_id',
        'type',
        'title',
        'description',
        'status',
        'version',
        'due_date',
        'open_at',
        'close_at',
        'is_late',
        'extension_until',
        'submitted_at',
        'graded_at',
        'resubmission_count',
        'last_resubmitted_at',
        'instructions_acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'open_at' => 'datetime',
            'close_at' => 'datetime',
            'extension_until' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'last_resubmitted_at' => 'datetime',
            'instructions_acknowledged_at' => 'datetime',
            'version' => 'integer',
            'is_late' => 'boolean',
            'resubmission_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(SubmissionTask::class, 'submission_task_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SubmissionVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class);
    }

    public function grade(): HasOne
    {
        return $this->hasOne(SubmissionGrade::class);
    }

    public function lateSubmissionRecord(): HasOne
    {
        return $this->hasOne(LateSubmission::class);
    }

    /**
     * Check if this submission is late based on task deadline
     */
    public function checkIfLate(): bool
    {
        if (! $this->task || ! $this->task->close_at) {
            return false;
        }

        if (! $this->submitted_at) {
            return false;
        }

        return $this->submitted_at > $this->task->close_at;
    }

    /**
     * Get the effective deadline for this submission (considering extensions)
     */
    public function getEffectiveDeadline(): ?\DateTime
    {
        if (! $this->task) {
            return $this->close_at ?? $this->due_date;
        }

        // Check if student has an extension
        $extension = SubmissionExtension::where('submission_task_id', $this->task->id)
            ->where('student_id', $this->user_id)
            ->where('status', 'approved')
            ->latest()
            ->first();

        if ($extension) {
            return $extension->extended_deadline;
        }

        return $this->close_at ?? $this->task->close_at;
    }

    /**
     * Check if this submission can still be resubmitted
     */
    public function canBeResubmitted(): bool
    {
        if (! $this->task) {
            return $this->status === 'correction_requested';
        }

        if ($this->status !== 'correction_requested' && $this->status !== 'under_review') {
            return false;
        }

        if ($this->task->max_resubmissions && $this->resubmission_count >= $this->task->max_resubmissions) {
            return false;
        }

        return true;
    }

    /**
     * Calculate score penalty for late submission
     */
    public function getLatePenalty(): float
    {
        if (! $this->is_late || ! $this->task) {
            return 0;
        }

        return (float) ($this->task->late_submission_penalty_percent ?? 0);
    }

    /**
     * Mark this submission as acknowledged (student read instructions)
     */
    public function acknowledgeInstructions(): bool
    {
        return $this->update(['instructions_acknowledged_at' => now()]);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
