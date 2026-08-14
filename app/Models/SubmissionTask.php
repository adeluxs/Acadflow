<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubmissionTask extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'uuid',
        'course_id',
        'semester_id',
        'created_by',
        'title',
        'description',
        'instructions',
        'type',
        'open_at',
        'close_at',
        'due_date',
        'late_deadline',
        'allow_late_submissions',
        'max_resubmissions',
        'allow_group_submissions',
        'min_group_size',
        'max_group_size',
        'allowed_file_types',
        'max_file_size_mb',
        'max_file_count',
        'min_file_count',
        'rubric_id',
        'max_score',
        'require_approval_before_grading',
        'status',
        'is_visible_to_students',
        'submission_format',
        'max_submissions_per_student',
        'submission_requirements_json',
        'late_submission_penalty_percent',
    ];

    protected function casts(): array
    {
        return [
            'open_at' => 'datetime',
            'close_at' => 'datetime',
            'due_date' => 'datetime',
            'late_deadline' => 'datetime',
            'allow_late_submissions' => 'boolean',
            'allow_group_submissions' => 'boolean',
            'require_approval_before_grading' => 'boolean',
            'is_visible_to_students' => 'boolean',
            'allowed_file_types' => 'array',
            'submission_requirements_json' => 'array',
            'max_file_size_mb' => 'integer',
            'max_file_count' => 'integer',
            'min_file_count' => 'integer',
            'min_group_size' => 'integer',
            'max_group_size' => 'integer',
            'max_score' => 'decimal:2',
            'late_submission_penalty_percent' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task) {
            if (empty($task->uuid)) {
                $task->uuid = (string) Str::uuid();
            }
        });
    }


    // Relationships
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'submission_task_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(SubmissionTaskRequirement::class, 'submission_task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SubmissionTaskAttachment::class, 'submission_task_id');
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(SubmissionExtension::class, 'submission_task_id');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(SubmissionRubric::class, 'rubric_id');
    }

    public function lateSubmissions(): HasMany
    {
        return $this->hasMany(LateSubmission::class, 'submission_task_id');
    }

    // Helper Methods

    /**
     * Check if the task is currently open for new submissions
     */
    public function isOpenForSubmission(): bool
    {
        $now = now();
        
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->open_at && $now->isBefore($this->open_at)) {
            return false;
        }

        if ($this->close_at && $now->isAfter($this->close_at)) {
            return $this->allow_late_submissions
                && (! $this->late_deadline || $now->isBefore($this->late_deadline));
        }

        return true;
    }

    /**
     * Check if the task is still accepting submissions (including late)
     */
    public function acceptsSubmissions(): bool
    {
        $now = now();
        
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->open_at && $now->isBefore($this->open_at)) {
            return false;
        }

        if ($this->late_deadline && $now->isAfter($this->late_deadline)) {
            return false;
        }

        return true;
    }

    /**
     * Check if a given time is late for this task
     */
    public function isLate(\DateTime $submittedAt): bool
    {
        if (! $this->close_at) {
            return false;
        }

        return $submittedAt > $this->close_at;
    }

    /**
     * Get effective deadline for a student (considering extensions)
     */
    public function getEffectiveDeadline(?User $student = null): ?\DateTime
    {
        $deadline = $this->close_at ?? $this->late_deadline;

        if ($student) {
            $extension = $this->extensions()
                ->where('student_id', $student->id)
                ->where('status', 'approved')
                ->latest()
                ->first();

            if ($extension && (! $deadline || $extension->extended_deadline->isAfter($deadline))) {
                return $extension->extended_deadline;
            }
        }

        return $deadline;
    }

    /**
     * Calculate late submission penalty
     */
    public function calculateLatePenalty(int $minutesLate): float
    {
        if (! $this->late_submission_penalty_percent || ! $this->allow_late_submissions) {
            return 0;
        }

        // Can customize this logic - for now, flat percentage
        return (float) $this->late_submission_penalty_percent;
    }

    /**
     * Get submission form format
     */
    public function acceptsFiles(): bool
    {
        return in_array($this->submission_format, ['file', 'both']);
    }

    public function acceptsText(): bool
    {
        return in_array($this->submission_format, ['text', 'both']);
    }

    /**
     * Get route key
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Publish this task (make visible and open for submissions)
     */
    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'is_visible_to_students' => true,
            'open_at' => $this->open_at ?? now(),
        ]);
    }

    /**
     * Close this task (stop accepting new submissions)
     */
    public function close(): bool
    {
        return $this->update([
            'status' => 'closed',
        ]);
    }

    /**
     * Archive this task
     */
    public function archive(): bool
    {
        return $this->update([
            'status' => 'archived',
            'is_visible_to_students' => false,
        ]);
    }
}
