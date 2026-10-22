<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\Department;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Database\Eloquent\Builder;

class AcademicOrganisationService
{
    public function directoryInstitution(int $id): ?University
    {
        return University::query()->select(['id', 'name', 'adoption_status'])
            ->where('code', '!=', IndependentAcademicContextService::UNIVERSITY_CODE)
            ->with(['faculties' => fn ($q) => $q->select(['id', 'university_id', 'name'])
                ->where('is_active', true)->orderBy('name')
                ->with(['departments' => fn ($d) => $d->select(['id', 'faculty_id', 'name', 'adoption_status'])
                    ->where('is_active', true)->orderBy('name')])])->find($id);
    }

    public function applyMembershipRole(User $user, int $universityId, ?int $departmentId, string $role): bool
    {
        $membership = $user->institutionAffiliations()->approved()->where('university_id', $universityId)->where('affiliation_type', $role)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))->first();
        if (! $membership || ! $this->contextAvailable($membership)) {
            return false;
        }
        $user->useAcademicAffiliation($membership);

        return true;
    }

    public function restoreCourseActor(User $user, Course $course, string $role): void
    {
        if ($user->getRawOriginal('role') === 'super_admin') {
            return;
        }
        $membership = null;
        if (! $course->isIndependent()) {
            $membership = $user->institutionAffiliations()->approved()->where('university_id', $course->department->faculty->university_id)
                ->where('affiliation_type', $role)->where(fn ($q) => $q->where('department_id', $course->department_id)->orWhereNull('department_id'))->orderByDesc('department_id')->first();
            if ($membership) {
                abort_unless($this->contextAvailable($membership), 403, 'The queued academic context is no longer active.');
            } elseif ($user->institutionAffiliations()->where('department_id', $course->department_id)->exists()) {
                abort(403, 'This academic membership is no longer approved.');
            }
        }
        $user->useAcademicAffiliation($membership);
        abort_unless($user->canAccessCourse($course), 403, 'The queued academic action is no longer authorised.');
    }

    public function departmentIds(User $actor): Builder
    {
        $query = Department::query()->whereHas('faculty', fn ($q) => $q->where('university_id', $actor->university_id));
        if ($actor->isSuperAdmin()) {
            return Department::query();
        }
        if ($actor->isUniversityAdmin()) {
            return $query->where(fn ($q) => $q->where('adoption_status', 'institution_managed')->orWhere(fn ($a) => $a->where('adoption_status', 'institution_affiliated')->where('adoption_settings->institution_data_access', true)));
        }

        return $query->whereKey($actor->department_id ?: 0);
    }

    public function canManageDepartment(User $actor, Department $department): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }
        if (! $department->is_active) {
            return false;
        }
        if ($actor->isDepartmentAdmin()) {
            return (int) $actor->department_id === (int) $department->id;
        }

        return $actor->isUniversityAdmin()
            && (int) $actor->university_id === (int) $department->faculty?->university_id
            && $department->isInstitutionManaged();
    }

    public function assertReviewer(User $actor, University $institution, ?Department $department, string $role): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        // Privileged roles remain platform-reviewed. Ordinary department access
        // is delegated only within the current administrator's approved scope.
        abort_if(in_array($role, ['university_admin', 'department_admin'], true), 403, 'Administrator membership requires Platform Admin verification.');
        if ($department) {
            abort_unless($this->canManageDepartment($actor, $department), 403);
        } else {
            abort_unless($actor->isUniversityAdmin() && (int) $actor->university_id === (int) $institution->id, 403);
        }
    }

    public function available(University $institution, ?Department $department): bool
    {
        if ($institution->code === IndependentAcademicContextService::UNIVERSITY_CODE || ! $institution->is_active) {
            return false;
        }
        if ($department) {
            return $department->is_active && in_array($department->adoption_status, ['independent_active', 'institution_managed', 'institution_affiliated'], true);
        }

        return $institution->adoption_status === 'active';
    }

    public function contextAvailable(UserInstitutionAffiliation $membership): bool
    {
        if (! $membership->isVerified()) {
            return false;
        }
        if ($membership->affiliation_type === 'university_admin' && $membership->university->is_active && in_array($membership->university->adoption_status, ['claim_verified', 'setup_in_progress'], true)) {
            return true;
        }

        return $this->available($membership->university, $membership->department);
    }

    public function validateHierarchy(University $institution, ?Department $department): void
    {
        if ($department) {
            abort_unless((int) $department->faculty?->university_id === (int) $institution->id, 422, 'The selected department does not belong to this institution.');
        }
        abort_if($institution->code === IndependentAcademicContextService::UNIVERSITY_CODE, 422, 'Choose an institution from the academic directory.');
    }

    public function membership(User $user): ?UserInstitutionAffiliation
    {
        if ($user->academicContextApplied()) {
            return $user->academicAffiliation();
        }

        return $user->institutionAffiliations()->approved()
            ->where('university_id', $user->university_id)
            ->where(fn ($q) => $q->where('department_id', $user->department_id)->orWhereNull('department_id'))
            ->orderByDesc('verified_at')->first();
    }

    public function hasInstitutionAccess(User $user, ?int $institutionId = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        $membership = $this->membership($user);
        if (! $membership?->isVerified()) {
            return false;
        }
        if ($institutionId && (int) $membership->university_id !== $institutionId) {
            return false;
        }
        if ($membership->university?->adoption_status !== 'active') {
            return false;
        }
        if (! $membership->department_id) {
            return true;
        }
        $department = $membership->department;

        return $department && ($department->adoption_status === 'institution_managed' || ($department->adoption_status === 'institution_affiliated' && (bool) data_get($department->adoption_settings, 'inherit_institution_features', false)));
    }

    public function canReadRecord(User $actor, object $record): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }
        if (! $record->university_id || (int) $record->university_id !== (int) $actor->university_id) {
            return false;
        }
        $membership = $this->membership($actor);
        if (! $membership?->isVerified()) {
            return false;
        }
        if ($record->department_id && (int) $record->department_id === (int) $membership->department_id) {
            return true;
        }
        if (! $this->hasInstitutionAccess($actor, (int) $record->university_id)) {
            return false;
        }

        return ! $record->department_id || Department::whereKey($record->department_id)->where(fn ($q) => $q->where('adoption_status', 'institution_managed')->orWhere(fn ($a) => $a->where('adoption_status', 'institution_affiliated')->where('adoption_settings->institution_data_access', true)))->exists();
    }

    public function canAdministerRecord(User $actor, object $record): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }
        if (! $actor->isAdmin() || ! $record->university_id || (int) $record->university_id !== (int) $actor->university_id) {
            return false;
        }
        if (! $record->department_id) {
            return $actor->isUniversityAdmin();
        }
        $department = Department::find($record->department_id);

        return $department && $this->canManageDepartment($actor, $department);
    }

    public function notifyAvailable(Department $department): void
    {
        // Notification uniqueness is carried by the existing notification data;
        // this is advisory and never grants a membership or role.
        User::withoutGlobalScopes()->select(User::notificationRecipientColumns())->where('department_id', $department->id)
            ->whereDoesntHave('institutionAffiliations', fn ($q) => $q->approved()->where('department_id', $department->id))
            ->whereDoesntHave('notifications', fn ($q) => $q->where('type', 'department_available')->where('data->department_id', $department->id))
            ->chunkById(200, function ($users) use ($department): void {
                foreach ($users as $user) {
                    app(SocialNotificationService::class)->send($user, 'department_available', 'Your department is now available', $department->name.' now uses AcadSyntra. Request access using your existing account.', ['department_id' => $department->id, 'route' => 'institution.access']);
                }
            });
    }
}
