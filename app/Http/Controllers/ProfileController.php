<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\CourseInvitation;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Role-aware authenticated user profile overview.
 *
 * Presents identity, institution membership, academic session, role and
 * permissions, course enrollment status, join invitations and account-level
 * access restrictions. All data is loaded from existing models and relationships
 * and is scoped to the authenticated user only.
 */
class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $contextUser=$request->user();
        // Directory identity is personal profile information, not an access grant.
        $user=User::withoutGlobalScopes()->findOrFail($contextUser->id);

        $user->load([
            'university:id,name,short_name,code,timezone,adoption_status',
            'faculty:id,name,short_name,code',
            'department:id,name,short_name,code,faculty_id',
            'avatarMedia',
        ]);

        $activeSemester = app(AcademicContextService::class)->activeSemesterForUniversity($user->university_id);
        $academicSession = $activeSemester?->academicSession;

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,uuid,code,name,department_id,access_mode',
                'course.department:id,name,faculty_id',
                'semester:id,name,academic_session_id',
                'semester.academicSession:id,name',
            ])
            ->latest('enrolled_at')
            ->get();

        $activeEnrollments = $enrollments->where('status', 'enrolled')->values();
        $inactiveEnrollments = $enrollments->where('status', '!=', 'enrolled')->values();

        $invitations = CourseInvitation::query()
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>=', now())
            ->with('course:id,uuid,code,name')
            ->latest('id')
            ->get();

        $teachingCourses = ($user->isLecturer() || $user->isAdmin())
            ? $user->lecturerAssignments()
                ->with(['course:id,uuid,code,name', 'semester:id,name'])
                ->get()
            : collect();

        $permissions = collect(Permission::forRole($contextUser->role))
            ->map(fn (Permission $permission): string => Str::headline(str_replace('_', ' ', $permission->value)))
            ->sort()
            ->values();

        $affiliation = $user->currentInstitutionAffiliation();
        $membershipStatus = $this->membershipStatus($user, $affiliation);

        return view('profile.show', [
            'user' => $user,
            'university' => $user->university,
            'faculty' => $user->faculty,
            'department' => $user->department,
            'affiliation' => $affiliation,
            'membershipStatus' => $membershipStatus,
            'activeSemester' => $activeSemester,
            'academicSession' => $academicSession,
            'activeEnrollments' => $activeEnrollments,
            'inactiveEnrollments' => $inactiveEnrollments,
            'invitations' => $invitations,
            'teachingCourses' => $teachingCourses,
            'permissions' => $permissions,
            'restrictions' => $this->accessRestrictions($user),
            'departmentRestricted' => (bool) SettingService::get('restrict_course_membership_to_department', true, $user->university_id),
        ]);
    }

    private function membershipStatus(User $user, $affiliation): array
    {
        if (! $user->university_id) {
            return [
                'is_member' => false,
                'label' => 'Independent (no institution)',
                'tone' => 'slate',
                'detail' => 'You are not attached to an institution. Institutional course enrollment is limited; independent classrooms remain available.',
            ];
        }

        $verified = $affiliation?->isVerified() ?? false;

        return [
            'is_member' => true,
            'label' => 'Member of '.($user->university?->short_name ?: $user->university?->name),
            'tone' => $verified ? 'emerald' : 'amber',
            'detail' => $verified
                ? 'Your institution affiliation is verified.'
                : 'Your institution is set. Affiliation verification is '.($affiliation?->status ?: 'pending').'.',
        ];
    }

    /** @return list<string> */
    private function accessRestrictions(User $user): array
    {
        $restrictions = [];

        if (! $user->is_active) {
            $restrictions[] = 'Your account is inactive. Contact your administrator to restore access.';
        }
        if (! $user->hasRequiredEmailVerification()) {
            $restrictions[] = 'Your email address is not verified. Some features remain limited until it is confirmed.';
        }
        if (! $user->onboarding_completed_at) {
            $restrictions[] = 'Onboarding is incomplete. Complete it to unlock your full workspace.';
        }
        if (! $user->university_id) {
            $restrictions[] = 'No institution on your profile: institutional courses require an institution; you can still join independent classrooms.';
        } elseif (! $user->department_id && (bool) SettingService::get('restrict_course_membership_to_department', true, $user->university_id)) {
            $restrictions[] = 'Your department is not set: department-restricted courses may deny enrollment until it is updated.';
        }

        return $restrictions;
    }
}
