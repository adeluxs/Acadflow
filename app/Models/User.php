<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Models\LecturerCourseAssignment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (empty($user->uuid)) $user->uuid = (string) Str::uuid();
            if ($user->email) $user->email = Str::lower(trim($user->email));
            if ($user->username) $user->username = Str::lower(trim($user->username));
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'research_interests' => 'array',
            'skills' => 'array',
            'topic_interests' => 'array',
            'event_interests' => 'array',
            'community_interests' => 'array',
            'notification_preferences' => 'array',
            'two_factor_recovery_codes' => 'array',
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

        if ($this->isLecturer() && $course->lecturerAssignments()->where('user_id', $this->id)->exists()) {
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
        if (! $this->hasPermission(Permission::GRADE_SUBMISSION)) {
            return false;
        }

        return $this->canAccessCourse($submission->course);
    }

    public function canViewCourseSubmissions($course): bool
    {
        if (! $this->hasPermission(Permission::VIEW_COURSE_SUBMISSIONS)) {
            return false;
        }

        return $this->canAccessCourse($course);
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
        'username',
        'account_type',
        'password',
        'phone',
        'country_code',
        'location',
        'avatar',
        'avatar_media_id',
        'faculty_id',
        'programme',
        'academic_level',
        'research_interests',
        'skills',
        'topic_interests',
        'event_interests',
        'community_interests',
        'profile_visibility',
        'notification_preferences',
        'onboarding_completed_at',
        'onboarding_version',
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

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'avatar_media_id');
    }

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function researchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class, 'owner_id');
    }

    public function supervisedResearchProjects(): HasMany
    {
        return $this->hasMany(ResearchProject::class, 'supervisor_id');
    }

    public function researchMemberships(): HasMany
    {
        return $this->hasMany(ResearchProjectMember::class);
    }

    public function contentDocuments(): HasMany
    {
        return $this->hasMany(ContentDocument::class, 'owner_id');
    }

    public function academicReferences(): HasMany
    {
        return $this->hasMany(AcademicReference::class, 'owner_id');
    }

    public function knowledgePublications(): HasMany
    {
        return $this->hasMany(KnowledgePublication::class, 'creator_id');
    }

    public function knowledgeBookmarks(): HasMany
    {
        return $this->hasMany(KnowledgeBookmark::class);
    }


    public function creatorProfile(): HasOne
    {
        return $this->hasOne(CreatorProfile::class);
    }

    public function onboardingState(): HasOne
    {
        return $this->hasOne(UserOnboardingState::class);
    }

    public function reputationProfile(): HasOne
    {
        return $this->hasOne(ReputationProfile::class);
    }

    public function walletAccount(): HasOne
    {
        return $this->hasOne(WalletAccount::class);
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class);
    }

    public function commerceEntitlements(): HasMany
    {
        return $this->hasMany(CommerceEntitlement::class);
    }

    public function readingLists(): HasMany
    {
        return $this->hasMany(ReadingList::class, 'owner_id');
    }

    public function learningEnrollments(): HasMany
    {
        return $this->hasMany(LearningEnrollment::class);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function organizedEvents(): HasMany
    {
        return $this->hasMany(AcademicEvent::class, 'organizer_id');
    }

    public function organizedChallenges(): HasMany
    {
        return $this->hasMany(AcademicChallenge::class, 'organizer_id');
    }

    public function communityMemberships(): HasMany
    {
        return $this->hasMany(KnowledgeCommunityMember::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }

    public function hasEntitlement(Model|string $target, ?int $targetId = null): bool
    {
        $type = is_object($target) ? $target::class : $target;
        $id = is_object($target) ? (int) $target->getKey() : (int) $targetId;

        return $this->commerceEntitlements()
            ->where('entitled_type', $type)
            ->where('entitled_id', $id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function featureEntitlements(): HasMany
    {
        return $this->hasMany(FeatureEntitlement::class);
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
        $currentSemester = app(\App\Services\AcademicContextService::class)->activeSemesterForUser($this);
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
     * Commercial feature access is independent of subscriptions.
     * Features are free by default until an enabled pricing rule explicitly
     * requires an entitlement.
     */
    public function hasFeature(string $feature): bool
    {
        return app(\App\Services\Commerce\EntitlementService::class)->has($this, $feature);
    }
}
