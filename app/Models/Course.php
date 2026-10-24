<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\AcademicOrganisationBoundary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsToRelation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    public const LEVEL_MAX_LENGTH = 30;

    public const SEMESTER_MAX_LENGTH = 30;

    public const MAX_CAPACITY = 1000000;

    protected $fillable = [
        'uuid',
        'department_id',
        'access_mode',
        'owner_user_id',
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
        'catalog_source',
        'is_catalog_template',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new AcademicOrganisationBoundary);
        static::creating(function (self $course) {
            if (empty($course->uuid)) {
                $course->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'submission_types' => 'array',
            'pass_mark' => 'integer',
            'credit_hours' => 'integer',
            'max_capacity' => 'integer',
            'is_active' => 'boolean',
            'is_catalog_template' => 'boolean',
            'owner_user_id' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function owner(): BelongsToRelation
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isIndependent(): bool
    {
        return $this->access_mode === 'independent';
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
