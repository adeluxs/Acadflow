<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentInstitutionAffiliation;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Support\Facades\DB;

class DepartmentAffiliationService
{
    public function request(User $actor, University $institution, Department $department, array $policy, string $billingOwner = 'department', ?string $note = null): DepartmentInstitutionAffiliation
    {
        abort_unless($actor->isSuperAdmin() || ($actor->isUniversityAdmin() && (int) $actor->university_id === (int) $institution->id), 403);
        app(AcademicOrganisationService::class)->validateHierarchy($institution, $department);
        abort_unless($institution->adoption_status === 'active', 422, 'Institution onboarding and activation must be completed first.');
        abort_unless(in_array($billingOwner, ['department', 'institution'], true), 422);

        return DB::transaction(function () use ($actor, $institution, $department, $policy, $billingOwner, $note) {
            $department = Department::query()->lockForUpdate()->findOrFail($department->id);
            abort_unless($department->adoption_status === 'independent_active', 422, 'Only independently adopted departments can be invited to affiliate.');
            abort_if($department->institutionAffiliations()->whereIn('status', ['pending_department', 'pending_verification', 'affiliated'])->exists(), 422, 'An affiliation is already active or under review.');
            $affiliation = DepartmentInstitutionAffiliation::create([
                'department_id' => $department->id, 'university_id' => $institution->id, 'requested_by' => $actor->id, 'status' => 'pending_department',
                'billing_owner' => $billingOwner, 'policy' => ['share_academic_data' => (bool) ($policy['share_academic_data'] ?? false), 'inherit_features' => (bool) ($policy['inherit_features'] ?? true)], 'note' => $note,
            ]);
            $this->notifyDepartment($department, 'department_affiliation_requested', 'Institution affiliation requested', $institution->name.' invites your department to affiliate. Review the proposed access and billing terms.', $affiliation->id);
            $this->audit('department_affiliation_requested', $actor, $affiliation);

            return $affiliation;
        }, 3);
    }

    public function respond(User $actor, DepartmentInstitutionAffiliation $affiliation, string $action, ?string $note = null): DepartmentInstitutionAffiliation
    {
        return DB::transaction(function () use ($actor, $affiliation, $action, $note) {
            $affiliation = DepartmentInstitutionAffiliation::query()->lockForUpdate()->findOrFail($affiliation->id);
            $department = Department::query()->lockForUpdate()->findOrFail($affiliation->department_id);
            $departmentAdmin = $actor->isDepartmentAdmin() && (int) $actor->department_id === (int) $department->id;
            $institutionAdmin = $actor->isUniversityAdmin() && (int) $actor->university_id === (int) $affiliation->university_id;
            if ($action === 'accept' || $action === 'decline') {
                // Even Platform Admin cannot supply the department's consent.
                abort_unless($departmentAdmin, 403);
                abort_unless($affiliation->status === 'pending_department', 422, 'This proposal is no longer awaiting a department decision.');
                $affiliation->fill(['status' => $action === 'accept' ? 'pending_verification' : 'declined', 'department_approved_by' => $action === 'accept' ? $actor->id : null, 'department_approved_at' => $action === 'accept' ? now() : null]);
            } elseif ($action === 'verify') {
                abort_unless($actor->isSuperAdmin(), 403);
                abort_unless($affiliation->status === 'pending_verification' && $affiliation->department_approved_at, 422, 'Both parties must approve before verification.');
                abort_unless($affiliation->university->adoption_status === 'active' && $department->adoption_status === 'independent_active', 422, 'Both organisations must be active.');
                // A representative who lost their scoped authority cannot leave
                // behind an approval that is silently acted on later.
                $consent = UserInstitutionAffiliation::query()->approved()->where('user_id', $affiliation->department_approved_by)->where('department_id', $department->id)->where('affiliation_type', 'department_admin')->exists();
                abort_unless($consent, 422, 'The department must provide current administrator approval.');
                $requester = User::withoutGlobalScopes()->findOrFail($affiliation->requested_by);
                $institutionConsent = $requester->getRawOriginal('role') === 'super_admin' || UserInstitutionAffiliation::query()->approved()->where('user_id', $requester->id)->where('university_id', $affiliation->university_id)->whereNull('department_id')->where('affiliation_type', 'university_admin')->exists();
                abort_unless($institutionConsent, 422, 'The institution must provide current administrator approval.');
                $affiliation->fill(['status' => 'affiliated', 'verified_by' => $actor->id, 'verified_at' => now()]);
                $department->forceFill(['adoption_status' => 'institution_affiliated', 'adoption_settings' => array_merge($department->adoption_settings ?? [], [
                    'institution_data_access' => (bool) data_get($affiliation->policy, 'share_academic_data', false), 'inherit_institution_features' => (bool) data_get($affiliation->policy, 'inherit_features', true), 'billing_owner' => $affiliation->billing_owner,
                ])])->save();
            } elseif ($action === 'withdraw') {
                abort_unless($institutionAdmin || $actor->isSuperAdmin(), 403);
                abort_unless(in_array($affiliation->status, ['pending_department', 'pending_verification'], true), 422);
                $affiliation->status = 'withdrawn';
            } elseif ($action === 'end') {
                abort_unless($departmentAdmin || $institutionAdmin || $actor->isSuperAdmin(), 403);
                abort_unless($affiliation->status === 'affiliated', 422, 'This department is not currently affiliated.');
                $affiliation->fill(['status' => 'ended', 'ended_at' => now()]);
                $department->forceFill(['adoption_status' => 'independent_active', 'adoption_settings' => array_merge($department->adoption_settings ?? [], ['institution_data_access' => false, 'inherit_institution_features' => false, 'billing_owner' => 'department'])])->save();
            } else {
                abort(422, 'Unsupported affiliation action.');
            }
            if ($note !== null) {
                $affiliation->note = $note;
            }
            $affiliation->save();
            $this->audit('department_affiliation_'.$action, $actor, $affiliation);
            $this->notifyDepartment($department, 'department_affiliation_updated', 'Department affiliation updated', 'Affiliation status: '.str_replace('_', ' ', $affiliation->status).'. Existing accounts, academic records and commercial balances have been preserved.', $affiliation->id);
            $institutionAdmins = UserInstitutionAffiliation::query()->approved()->where('university_id', $affiliation->university_id)->whereNull('department_id')->where('affiliation_type', 'university_admin')->with('user')->get();
            foreach ($institutionAdmins as $membership) {
                app(SocialNotificationService::class)->send($membership->user, 'department_affiliation_updated', 'Department affiliation updated', $department->name.': '.str_replace('_', ' ', $affiliation->status), ['affiliation_id' => $affiliation->id, 'route' => 'academic.organisations']);
            }

            return $affiliation;
        }, 3);
    }

    private function notifyDepartment(Department $department, string $type, string $title, string $message, int $affiliationId): void
    {
        $members = UserInstitutionAffiliation::query()->approved()->where('department_id', $department->id)->where('affiliation_type', 'department_admin')->with('user')->get();
        foreach ($members as $member) {
            app(SocialNotificationService::class)->send($member->user, $type, $title, $message, ['affiliation_id' => $affiliationId, 'department_id' => $department->id, 'route' => 'academic.organisations']);
        }
    }

    private function audit(string $action, User $actor, DepartmentInstitutionAffiliation $affiliation): void
    {
        AuditLog::log($action, $actor->id, $affiliation::class, $affiliation->id, null, ['status' => $affiliation->status, 'department_id' => $affiliation->department_id, 'billing_owner' => $affiliation->billing_owner, 'policy' => $affiliation->policy], request()?->ip(), request()?->userAgent());
    }
}
