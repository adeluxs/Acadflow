<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = [
        'uuid',
        'department_id',
        'code',
        'name',
        'description',
        'credit_hours',
        'level',
        'semester',
        'type',
        'max_capacity',
        'submission_types',
        'pass_mark',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'submission_types' => 'array',
            'pass_mark' => 'integer',
            'credit_hours' => 'integer',
            'max_capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function lecturerAssignments(): HasMany
    {
        return $this->hasMany(LecturerCourseAssignment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function submissionTasks(): HasMany
    {
        return $this->hasMany(SubmissionTask::class);
    }

    public function submissionRubrics(): HasMany
    {
        return $this->hasMany(SubmissionRubric::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    /**
     * Get route key name
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
