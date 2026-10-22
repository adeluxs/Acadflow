<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicMembershipInvitation;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademicMembershipService
{
    public function invite(User $actor, University $institution, ?Department $department, string $role, string $email): array
    {
        abort_unless(in_array($role, ['student', 'lecturer', 'researcher', 'staff', 'department_admin', 'university_admin'], true), 422);
        $organisations = app(AcademicOrganisationService::class);
        $organisations->validateHierarchy($institution, $department);
        $this->assertInviter($actor, $institution, $department, $role);
        abort_unless($organisations->available($institution, $department), 422, 'Complete adoption before inviting members.');
        $token = Str::random(64);
        $invitation = AcademicMembershipInvitation::create(['university_id' => $institution->id, 'department_id' => $department?->id, 'invited_by' => $actor->id, 'email' => Str::lower(trim($email)), 'role' => $role, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays((int) config('academic_adoption.invitation_days', 7))]);
        $url = route('academic.invitations.accept', ['token' => $token]);
        $recipient = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$invitation->email])->first();
        if ($recipient) {
            app(SocialNotificationService::class)->send($recipient, 'academic_membership_invitation', 'Academic membership invitation', 'You have been invited to join '.($department?->name ?? $institution->name).' using your existing account.', ['url' => $url]);
        }
        $this->audit('academic_invitation_created', $actor, $invitation);

        return ['invitation' => $invitation, 'url' => $url];
    }

    public function accept(User $user, string $token): UserInstitutionAffiliation
    {
        return DB::transaction(function () use ($user, $token) {
            $invitation = AcademicMembershipInvitation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->firstOrFail();
            abort_unless(! $invitation->accepted_at && ! $invitation->revoked_at && $invitation->expires_at->isFuture(), 410, 'This invitation has expired or has already been used.');
            abort_unless(hash_equals($invitation->email, Str::lower($user->email)) && $user->hasVerifiedEmail(), 403, 'Sign in with the verified email address named in the invitation.');
            $actor = User::withoutGlobalScopes()->findOrFail($invitation->invited_by);
            if ($actor->getRawOriginal('role') !== 'super_admin') {
                $membership = $actor->institutionAffiliations()->approved()->where('university_id', $invitation->university_id)
                    ->where(fn ($q) => $q->where(fn ($d) => $d->where('department_id', $invitation->department_id)->where('affiliation_type', 'department_admin'))->orWhere(fn ($u) => $u->whereNull('department_id')->where('affiliation_type', 'university_admin')))->lockForUpdate()->first();
                abort_unless($membership, 403, 'The inviting administrator no longer has authority.');
                $actor->useAcademicAffiliation($membership);
            }
            $organisations = app(AcademicOrganisationService::class);
            abort_unless($organisations->available($invitation->university, $invitation->department), 422, 'This academic organisation is not active.');
            $this->assertInviter($actor, $invitation->university, $invitation->department, $invitation->role);
            if (in_array($invitation->role, ['department_admin', 'university_admin'], true) && ! $actor->isSuperAdmin()) {
                app(InstitutionAdoptionService::class)->requestAffiliationVerification($user, $invitation->university, $invitation->department, $invitation->role, null, 'secure_invitation');
                $membership = $user->institutionAffiliations()->where('university_id', $invitation->university_id)->where('department_id', $invitation->department_id)->where('affiliation_type', $invitation->role)->firstOrFail();
            } else {
                $membership = app(InstitutionAdoptionService::class)->verifyAffiliation($user, $invitation->university, $invitation->department, $invitation->role, $actor, 'secure_invitation');
            }
            $invitation->forceFill(['accepted_by' => $user->id, 'accepted_at' => now()])->save();
            $this->audit('academic_invitation_accepted', $user, $invitation);

            return $membership;
        }, 3);
    }

    public function end(User $actor, UserInstitutionAffiliation $membership, string $status, string $note): void
    {
        abort_unless(in_array($status, ['revoked', 'graduated', 'transferred'], true), 422);
        DB::transaction(function () use ($actor, $membership, $status, $note): void {
            // Lock organisation first: concurrent removals cannot both conclude
            // that another active department administrator will remain.
            if ($membership->department_id) {
                Department::query()->whereKey($membership->department_id)->lockForUpdate()->firstOrFail();
            } else {
                University::query()->whereKey($membership->university_id)->lockForUpdate()->firstOrFail();
            }
            $membership = UserInstitutionAffiliation::query()->lockForUpdate()->findOrFail($membership->id);
            if ((int) $actor->id !== (int) $membership->user_id) {
                app(AcademicOrganisationService::class)->assertReviewer($actor, $membership->university, $membership->department, $membership->affiliation_type);
            }
            abort_unless($membership->isVerified(), 422, 'Only an approved membership can be ended.');
            if (in_array($membership->affiliation_type, ['department_admin', 'university_admin'], true) && ! $actor->isSuperAdmin()) {
                $other = UserInstitutionAffiliation::query()->approved()->whereKeyNot($membership->id)->where('university_id', $membership->university_id)->where('department_id', $membership->department_id)->where('affiliation_type', $membership->affiliation_type)->exists();
                abort_unless($other, 422, 'Assign another verified administrator before ending the final administrator membership.');
            }
            $membership->forceFill(['status' => $status, 'revoked_at' => now(), 'review_note' => $note])->save();
            $this->audit('academic_membership_'.$status, $actor, $membership);
            app(SocialNotificationService::class)->send($membership->user, 'academic_membership_ended', 'Academic membership updated', 'Membership status: '.$status.'. Your account and personal work remain available.', ['affiliation_id' => $membership->id]);
        }, 3);
    }

    public function revokeInvitation(User $actor, AcademicMembershipInvitation $invitation): void
    {
        $this->assertInviter($actor, $invitation->university, $invitation->department, $invitation->role);
        abort_if($invitation->accepted_at, 422, 'This invitation was already accepted. Manage its membership instead.');
        $invitation->update(['revoked_at' => now()]);
        $this->audit('academic_invitation_revoked', $actor, $invitation);
    }

    private function assertInviter(User $actor, University $institution, ?Department $department, string $role): void
    {
        app(AcademicOrganisationService::class)->validateHierarchy($institution, $department);
        abort_if($role === 'department_admin' && ! $department, 422, 'Select a department.');
        abort_if($role === 'university_admin' && $department, 422, 'Institution administrators use institution scope.');
        if (in_array($role, ['department_admin', 'university_admin'], true)) {
            if ($actor->isSuperAdmin()) {
                return;
            }
            abort_unless($role === 'department_admin' && $actor->isUniversityAdmin() && $department && app(AcademicOrganisationService::class)->canManageDepartment($actor, $department), 403);
        } else {
            app(AcademicOrganisationService::class)->assertReviewer($actor, $institution, $department, $role);
        }
    }

    private function audit(string $action, User $actor, object $subject): void
    {
        AuditLog::log($action, $actor->id, $subject::class, $subject->id, null, ['status' => $subject->status ?? null], request()?->ip(), request()?->userAgent());
    }
}
