<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Models\LecturerCourseAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function hasPermission(Permission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $permission->belongsTo($this->role);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function canAccessCourse($course): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isUniversityAdmin() && $this->university_id === $course->department->faculty->university_id) {
            return true;
        }

        if ($this->isDepartmentAdmin() && $this->department_id === $course->department_id) {
            return true;
        }

        if ($this->isLecturer() && $course->lecturer_id === $this->id) {
            return true;
        }

        if ($this->isStudent()) {
            return $this->enrollments()
                ->where('course_id', $course->id)
                ->where('status', 'enrolled')
                ->exists();
        }

        return false;
    }

    public function canGradeSubmission(Submission $submission): bool
    {
        if ($this->isSuperAdmin() || $this->isUniversityAdmin() || $this->isDepartmentAdmin()) {
            return true;
        }

        if (! $this->hasPermission(Permission::GRADE_SUBMISSION)) {
            return false;
        }

        return $submission->course->lecturer_id === $this->id;
    }

    public function canViewCourseSubmissions($course): bool
    {
        if ($this->isSuperAdmin() || $this->isUniversityAdmin() || $this->isDepartmentAdmin()) {
            return true;
        }

        return $course->lecturer_id === $this->id;
    }

    public function getAllPermissions(): array
    {
        return Permission::forRole($this->role);
    }

    protected $fillable = [
        'uuid',
        'university_id',
        'department_id',
        'student_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'avatar',
        'role',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function lecturerAssignments(): HasMany
    {
        return $this->hasMany(LecturerCourseAssignment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)->where('status', 'active');
    }

    public function ledGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'leader_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'university_admin', 'department_admin']);
    }

    public function isLecturer(): bool
    {
        return $this->role === 'lecturer';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isUniversityAdmin(): bool
    {
        return $this->role === 'university_admin';
    }

    public function isDepartmentAdmin(): bool
    {
        return $this->role === 'department_admin';
    }

    public function hasPaidCurrentSemester(): bool
    {
        $currentSemester = Semester::where('is_active', true)->first();
        if (! $currentSemester) {
            return true;
        }

        $invoice = $this->invoices()
            ->where('semester_id', $currentSemester->id)
            ->where('status', 'paid')
            ->first();

        return $invoice !== null;
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user has access to a feature based on subscription plan
     */
    public function hasFeature(string $feature): bool
    {
        // Super admins have all features
        if ($this->isSuperAdmin()) {
            return true;
        }

        $subscription = $this->activeSubscription()->first();
        if (! $subscription || ! $subscription->plan) {
            return false;
        }

        $plan = $subscription->plan;

        // Check boolean column if applicable
        $booleanFeatures = [
            'allow_group_submissions',
            'allow_rubrics',
            'allow_attendance_tracking',
            'allow_document_generation',
            'allow_api_access',
            'allow_white_label',
        ];

        if (in_array($feature, $booleanFeatures)) {
            return (bool) ($plan->$feature ?? false);
        }

        // Check features array
        return $plan->hasFeature($feature);
    }
}
