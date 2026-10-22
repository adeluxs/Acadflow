<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CommercialAccountRequest;
use App\Models\Department;
use App\Models\DepartmentAdoptionRequest;
use App\Models\Faculty;
use App\Models\InstitutionAccessRequest;
use App\Models\InstitutionEmailDomain;
use App\Models\InstitutionOnboardingRequest;
use App\Models\University;
use App\Models\User;
use App\Models\UserInstitutionAffiliation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InstitutionAdoptionService
{
    public function __construct(private readonly SocialNotificationService $notifications) {}

    public function searchInstitutions(string $term, int $limit = 12)
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }
        $normalized = Str::lower($term);
        $pattern = '%'.$normalized.'%';

        return University::query()
            ->where('code', '!=', IndependentAcademicContextService::UNIVERSITY_CODE)
            ->where(function (Builder $q) use ($pattern): void {
                // LOWER(... LIKE ...) keeps partial matching predictable across
                // MySQL, PostgreSQL and SQLite instead of relying on each
                // database's default collation/case-sensitivity behaviour.
                $q->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(short_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(code, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(state, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(regulator, \'\')) LIKE ?', [$pattern]);
            })
            ->withCount(['users', 'faculties'])
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(short_name) = ? THEN 1 ELSE 2 END', [$normalized, $normalized])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function institutionState(University $university): string
    {
        $status = (string) ($university->adoption_status ?: 'unclaimed');
        if ($status === 'active') {
            return 'active';
        }
        if (InstitutionOnboardingRequest::query()->where('university_id', $university->id)->whereIn('status', ['pending', 'in_review'])->exists()) {
            return 'claim_pending';
        }

        return $status;
    }

    public function findPotentialMatch(array $data, ?int $excludeId = null): ?University
    {
        $name = Str::lower(trim((string) ($data['institution_name'] ?? '')));
        $code = Str::lower(trim((string) ($data['code'] ?? '')));
        $short = Str::lower(trim((string) ($data['short_name'] ?? '')));
        $websiteHost = $this->host((string) ($data['website'] ?? ''));
        $emailDomain = $this->emailDomain((string) ($data['email'] ?? ''));

        return University::query()
            ->where('code', '!=', IndependentAcademicContextService::UNIVERSITY_CODE)
            ->when($excludeId, fn (Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($name, $code, $short, $websiteHost, $emailDomain): void {
                if ($name !== '') {
                    $q->orWhereRaw('LOWER(name) = ?', [$name]);
                }
                if ($code !== '') {
                    $q->orWhereRaw('LOWER(code) = ?', [$code]);
                }
                if ($short !== '') {
                    $q->orWhereRaw('LOWER(short_name) = ?', [$short]);
                }
                if ($websiteHost !== '') {
                    $q->orWhere('website', 'like', '%'.$websiteHost.'%');
                }
                if ($emailDomain !== '') {
                    $q->orWhere('email', 'like', '%@'.$emailDomain);
                }
            })->first();
    }

    public function createClaim(User $requester, University $university, array $data): InstitutionOnboardingRequest
    {
        if ($this->institutionState($university) === 'active') {
            throw ValidationException::withMessages(['university_id' => ['This institution already uses AcadSyntra. Request institution access instead.']]);
        }
        if (InstitutionOnboardingRequest::query()->where('university_id', $university->id)->whereIn('status', ['pending', 'in_review'])->exists()) {
            throw ValidationException::withMessages(['university_id' => ['An onboarding claim for this institution is already under review.']]);
        }

        $claim = InstitutionOnboardingRequest::create([
            'requester_user_id' => $requester->id, 'university_id' => $university->id,
            'institution_name' => $university->name, 'short_name' => $university->short_name, 'code' => $university->code,
            'institution_type' => $university->institution_type ?: ($data['institution_type'] ?? 'university'),
            'ownership' => $university->ownership, 'state' => $university->state, 'regulator' => $university->regulator,
            'email' => $data['email'] ?? $university->email, 'phone' => $data['phone'] ?? $university->phone,
            'website' => $data['website'] ?? $university->website, 'address' => $university->address,
            'representative_title' => $data['representative_title'], 'request_type' => 'claim_existing', 'status' => 'pending',
            'metadata' => ['evidence' => $data['evidence'] ?? null],
        ]);
        $university->forceFill(['adoption_status' => 'claim_pending'])->save();
        $this->audit('institution_claim_submitted', $requester, $university, ['request_id' => $claim->id]);

        return $claim;
    }

    public function recordSelfDeclared(User $user, string $source = 'onboarding'): ?UserInstitutionAffiliation
    {
        if (! $user->university_id) {
            return null;
        }
        $identity = ['user_id' => $user->id, 'university_id' => $user->university_id, 'department_id' => $user->department_id, 'affiliation_type' => $this->affiliationTypeFor($user)];

        return UserInstitutionAffiliation::query()->firstOrCreate($identity, ['faculty_id' => $user->faculty_id, 'status' => 'self_declared', 'source' => $source]);
    }

    public function requestAffiliationVerification(User $user, University $university, ?Department $department, string $requestedRole, ?string $identifier = null, string $method = 'admin_review'): InstitutionAccessRequest
    {
        if (! in_array($requestedRole, ['student', 'lecturer', 'researcher', 'department_admin', 'university_admin', 'staff'], true)) {
            throw ValidationException::withMessages(['requested_role' => 'Unsupported academic role.']);
        }
        $organisations = app(AcademicOrganisationService::class);
        $organisations->validateHierarchy($university, $department);
        abort_if($requestedRole === 'department_admin' && ! $department, 422, 'Select a department.');
        abort_if($requestedRole === 'university_admin' && $department, 422, 'Institution administrator access must use institution scope.');
        abort_unless($organisations->available($university, $department), 422, 'This academic organisation has not completed adoption.');
        if ($method === 'institution_email' && ! $this->emailMatchesVerifiedDomain($user, $university)) {
            throw ValidationException::withMessages(['verification_method' => 'Your email does not match a verified institution domain.']);
        }

        return DB::transaction(function () use ($user, $university, $department, $requestedRole, $identifier, $method) {
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $identity = ['user_id' => $user->id, 'university_id' => $university->id, 'department_id' => $department?->id, 'affiliation_type' => $requestedRole];
            $affiliation = UserInstitutionAffiliation::query()->where($identity)->first();
            if ($affiliation?->isVerified()) {
                throw ValidationException::withMessages(['requested_role' => 'This membership is already approved. Switch to it in My Academic Affiliations.']);
            }
            $existing = InstitutionAccessRequest::query()->where('user_id', $user->id)->where('university_id', $university->id)->where('department_id', $department?->id)->where('requested_role', $requestedRole)->whereIn('status', ['pending', 'in_review'])->first();
            if ($existing) {
                return $existing;
            }
            $request = InstitutionAccessRequest::create([
                'user_id' => $user->id, 'university_id' => $university->id, 'department_id' => $department?->id, 'requested_role' => $requestedRole, 'status' => 'pending', 'verification_method' => $method,
                'identifier_hash' => $identifier ? hash('sha256', Str::lower(trim($identifier))) : null, 'identifier_last4' => $identifier ? Str::substr(trim($identifier), -4) : null,
            ]);
            UserInstitutionAffiliation::query()->updateOrCreate($identity, ['faculty_id' => $department?->faculty_id, 'status' => 'pending_verification', 'revoked_at' => null, 'verified_at' => null, 'verified_by' => null, 'verification_method' => $method, 'source' => 'access_request', 'identifier_hash' => $request->identifier_hash, 'identifier_last4' => $request->identifier_last4]);
            $this->audit('institution_access_requested', $user, $university, ['request_id' => $request->id, 'requested_role' => $requestedRole, 'department_id' => $department?->id]);

            return $request;
        }, 3);
    }

    public function verifyAffiliation(User $subject, University $university, ?Department $department, string $type, User $reviewer, string $method = 'admin_review', ?string $note = null): UserInstitutionAffiliation
    {
        $this->assertCanVerify($reviewer, $university, $department, $type);
        app(AcademicOrganisationService::class)->validateHierarchy($university, $department);
        abort_if($type === 'department_admin' && ! $department, 422, 'A department is required.');

        return DB::transaction(function () use ($subject, $university, $department, $type, $reviewer, $method, $note): UserInstitutionAffiliation {
            User::withoutGlobalScopes()->whereKey($subject->id)->lockForUpdate()->firstOrFail();
            $first = ! $subject->institutionAffiliations()->approved()->exists();
            $affiliation = UserInstitutionAffiliation::query()->updateOrCreate(
                ['user_id' => $subject->id, 'university_id' => $university->id, 'department_id' => $department?->id, 'affiliation_type' => $type],
                ['faculty_id' => $department?->faculty_id, 'status' => 'verified', 'revoked_at' => null, 'expires_at' => null, 'verification_method' => $method, 'source' => $method, 'verified_by' => $reviewer->id, 'verified_at' => now(), 'review_note' => $note]
            );
            // Keep the legacy primary profile for the first approved connection.
            // Further memberships never overwrite another organisation's role.
            if ($first && ! in_array($subject->getRawOriginal('role'), ['super_admin', 'university_admin', 'department_admin'], true)) {
                $updates = ['university_id' => $university->id, 'faculty_id' => $department?->faculty_id, 'department_id' => $department?->id];
                if (in_array($type, ['department_admin', 'university_admin'], true) || ($subject->getRawOriginal('role') === 'member' && in_array($type, ['student', 'lecturer'], true))) {
                    $updates['role'] = $type;
                }
                $subject->forceFill($updates)->save();
            }
            $this->audit('institution_affiliation_verified', $reviewer, $subject, ['university_id' => $university->id, 'department_id' => $department?->id, 'type' => $type]);
            $this->notifications->send($subject, 'institution_affiliation_verified', 'Academic membership approved', "Your existing AcadSyntra account is now connected to {$university->name}.", ['affiliation_id' => $affiliation->id, 'route' => 'institution.access']);

            return $affiliation;
        }, 3);
    }

    public function rejectAccessRequest(InstitutionAccessRequest $access, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($access, $reviewer, $note): void {
            $access = InstitutionAccessRequest::query()->lockForUpdate()->findOrFail($access->id);
            $this->assertCanVerify($reviewer, $access->university, $access->department, $access->requested_role);
            abort_unless(in_array($access->status, ['pending', 'in_review'], true), 422, 'This request has already been reviewed.');
            $access->forceFill(['status' => 'rejected', 'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            UserInstitutionAffiliation::query()->where('user_id', $access->user_id)->where('university_id', $access->university_id)->where('department_id', $access->department_id)->where('affiliation_type', $access->requested_role)->where('status', 'pending_verification')->update(['status' => 'rejected', 'review_note' => $note]);
            $this->audit('academic_membership_rejected', $reviewer, $access, ['note' => $note]);
            $this->notifications->send($access->user, 'institution_access_rejected', 'Academic access request reviewed', 'Your academic access request was not approved.', ['request_id' => $access->id]);
        }, 3);
    }

    public function approveAccessRequest(InstitutionAccessRequest $access, User $reviewer, ?string $note = null): UserInstitutionAffiliation
    {
        return DB::transaction(function () use ($access, $reviewer, $note) {
            $access = InstitutionAccessRequest::query()->lockForUpdate()->findOrFail($access->id);
            abort_unless(in_array($access->status, ['pending', 'in_review'], true), 422, 'This access request was already reviewed.');
            abort_unless(app(AcademicOrganisationService::class)->available($access->university, $access->department), 422, 'This academic organisation is not active.');
            $affiliation = $this->verifyAffiliation($access->user, $access->university, $access->department, $access->requested_role, $reviewer, $access->verification_method ?: 'admin_review', $note);
            $access->forceFill(['status' => 'approved', 'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();

            return $affiliation;
        }, 3);
    }

    public function createDepartmentAdoption(User $requester, University $university, ?Faculty $faculty, ?Department $department, array $data): DepartmentAdoptionRequest
    {
        app(AcademicOrganisationService::class)->validateHierarchy($university, $department);
        if ($faculty && (int) $faculty->university_id !== (int) $university->id) {
            throw ValidationException::withMessages(['faculty_id' => 'This faculty does not belong to the selected institution.']);
        }
        if ($faculty && $department && (int) $department->faculty_id !== (int) $faculty->id) {
            throw ValidationException::withMessages(['department_id' => 'This department does not belong to the selected faculty.']);
        }
        $faculty ??= $department?->faculty;
        if (! $faculty && empty($data['faculty_name'])) {
            throw ValidationException::withMessages(['faculty_name' => 'Select a faculty/school or enter its name.']);
        }

        return DB::transaction(function () use ($requester, $university, $faculty, $department, $data) {
            University::query()->whereKey($university->id)->lockForUpdate()->firstOrFail();
            if(! $faculty && !empty($data['faculty_name']))$faculty=Faculty::where('university_id',$university->id)->whereRaw('LOWER(TRIM(name)) = ?',[Str::lower(trim($data['faculty_name']))])->first();
            if(! $department && $faculty && !empty($data['department_name']))$department=Department::where('faculty_id',$faculty->id)->whereRaw('LOWER(TRIM(name)) = ?',[Str::lower(trim($data['department_name']))])->first();
            if ($department && in_array($department->fresh()->adoption_status, ['independent_active', 'institution_managed', 'institution_affiliated'], true)) {
                throw ValidationException::withMessages(['department_id' => 'This department is already active. Request membership instead.']);
            }
            $name = trim((string) ($department?->name ?? $data['department_name']));
            $pending = DepartmentAdoptionRequest::query()->where('university_id', $university->id)->whereIn('status', ['pending', 'in_review'])
                ->where(function ($q) use ($department, $name, $faculty) {
                    if ($department) {
                        $q->where('department_id', $department->id);
                    } else {
                        $q->where('faculty_id', $faculty?->id)->whereRaw('LOWER(department_name) = ?', [Str::lower($name)]);
                    }
                })->exists();
            if ($pending) {
                throw ValidationException::withMessages(['department_name' => 'This department already has an adoption request under review.']);
            }
            $application = DepartmentAdoptionRequest::create([
                'requester_user_id' => $requester->id, 'university_id' => $university->id, 'faculty_id' => $faculty?->id, 'faculty_name' => $faculty?->name ?? $data['faculty_name'], 'department_id' => $department?->id,
                'department_name' => $name, 'department_code' => $department?->code ?? ($data['department_code'] ?? null), 'representative_title' => $data['representative_title'], 'classification' => $data['classification'] ?? null,
                'official_email' => $data['official_email'] ?? null, 'official_phone' => $data['official_phone'] ?? null, 'plan_key' => $data['plan_key'] ?? 'department_core', 'status' => 'pending',
                'metadata' => ['evidence' => $data['evidence'] ?? null, 'evidence_media_id' => $data['evidence_media_id'] ?? null],
            ]);
            $this->audit('department_adoption_requested', $requester, $application);

            return $application;
        }, 3);
    }

    public function approveDepartmentAdoption(DepartmentAdoptionRequest $request, User $reviewer, ?string $note = null): Department
    {
        abort_unless($reviewer->isSuperAdmin(), 403);

        return DB::transaction(function () use ($request, $reviewer, $note): Department {
            $request = DepartmentAdoptionRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless(in_array($request->status, ['pending', 'in_review'], true), 422, 'This department request has already been reviewed.');
            University::query()->whereKey($request->university_id)->lockForUpdate()->firstOrFail();
            $faculty = $request->faculty;
            if (! $faculty) {
                abort_unless($request->faculty_name, 422, 'Specify a faculty or school before approval.');
                $faculty = Faculty::where('university_id',$request->university_id)->whereRaw('LOWER(TRIM(name)) = ?',[Str::lower(trim($request->faculty_name))])->first()
                    ?? Faculty::query()->firstOrCreate(['university_id' => $request->university_id, 'name' => $request->faculty_name], ['short_name' => Str::limit($request->faculty_name, 50, ''), 'code' => 'FAC-'.Str::upper(Str::random(10)), 'is_active' => true]);
            }
            abort_unless((int) $faculty->university_id === (int) $request->university_id, 422, 'Faculty belongs to another institution.');
            $department = $request->department ?: Department::where('faculty_id',$faculty->id)->whereRaw('LOWER(TRIM(name)) = ?',[Str::lower(trim($request->department_name))])->first()
                ?? Department::query()->firstOrCreate(['faculty_id' => $faculty->id, 'name' => $request->department_name], ['short_name' => Str::limit($request->department_name, 50, ''), 'code' => $request->department_code ?: 'DPT-'.Str::upper(Str::random(10)), 'is_active' => true]);
            abort_unless((int) $department->faculty_id === (int) $faculty->id, 422, 'Department and faculty do not match.');
            abort_if(in_array($department->adoption_status, ['independent_active', 'institution_managed', 'institution_affiliated'], true), 422, 'This department has already adopted AcadSyntra.');
            $department->forceFill(['adoption_status' => 'independent_active', 'adopted_at' => $department->adopted_at ?: now(), 'is_active' => true, 'adoption_settings' => array_merge($department->adoption_settings ?? [], ['classification' => $request->classification, 'plan_key' => $request->plan_key])])->save();
            $this->verifyAffiliation($request->requester, $request->university, $department, 'department_admin', $reviewer, 'department_adoption', $note);
            $request->forceFill(['faculty_id' => $faculty->id, 'department_id' => $department->id, 'status' => 'approved', 'reviewed_by_user_id' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            CommercialAccountRequest::query()->firstOrCreate(['department_id' => $department->id, 'request_type' => 'account_setup', 'status' => 'pending'], ['university_id' => $request->university_id, 'requested_by_user_id' => $request->requester_user_id, 'currency' => 'NGN', 'requested_changes' => ['plan_key' => $request->plan_key], 'message' => 'Department adoption approved. Review commercial terms; no charge or recurring subscription has been created.']);
            $this->audit('department_adoption_approved', $reviewer, $department, ['request_id' => $request->id]);
            app(AcademicOrganisationService::class)->notifyAvailable($department);

            return $department;
        }, 3);
    }

    public function migrateIndependentDepartments(University $university, User $reviewer): int
    {
        abort_unless($reviewer->isSuperAdmin() || ($reviewer->isUniversityAdmin() && (int) $reviewer->university_id === (int) $university->id), 403);
        abort_unless($university->adoption_status === 'active', 422, 'Complete institution activation first.');
        // Compatibility entry point: identify candidates, never silently transfer
        // department governance, data or commercial ownership.
        $departments = Department::query()->whereHas('faculty', fn ($q) => $q->where('university_id', $university->id))->where('adoption_status', 'independent_active');
        $count = (clone $departments)->count();
        UserInstitutionAffiliation::query()->approved()->whereIn('department_id', $departments->select('departments.id'))
            ->where('affiliation_type', 'department_admin')
            ->whereHas('user', fn ($q) => $q->whereDoesntHave('notifications', fn ($n) => $n->where('type', 'parent_institution_available')->where('data->university_id', $university->id)))
            ->with(['user' => fn ($q) => $q->select(User::notificationRecipientColumns())])->chunkById(200, function ($memberships) use ($university): void {
                // A representative may administer several departments. The notice
                // is institution-wide, so send it once per recipient in this batch.
                foreach ($memberships->unique('user_id') as $membership) {
                    $this->notifications->send($membership->user, 'parent_institution_available', 'Your institution now uses AcadSyntra', $university->name.' has completed adoption. Your department remains independent unless an affiliation is approved.', ['university_id' => $university->id, 'department_id' => $membership->department_id, 'route' => 'academic.organisations']);
                }
            });
        $this->audit('independent_departments_identified', $reviewer, $university, ['count' => $count]);

        return $count;
    }

    public function canReceiveInstitutionBenefits(User $user, ?int $universityId = null): bool
    {
        return app(AcademicOrganisationService::class)->hasInstitutionAccess($user, $universityId);
    }

    public function proposeEmailDomain(University $university, string $domain, User $actor): InstitutionEmailDomain
    {
        $domain = Str::lower(trim($domain));
        $domain = preg_replace('/^@/', '', $domain) ?: '';
        if (! filter_var('verify@'.$domain, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['domain' => ['Enter a valid institution email domain.']]);
        }
        $existing = InstitutionEmailDomain::query()->where('domain', $domain)->first();
        if ($existing && (int) $existing->university_id !== (int) $university->id) {
            throw ValidationException::withMessages(['domain' => ['This email domain is already associated with another institution.']]);
        }

        return InstitutionEmailDomain::query()->updateOrCreate(['domain' => $domain], ['university_id' => $university->id, 'status' => $existing?->status ?? 'proposed', 'proposed_by' => $actor->id]);
    }

    public function verifyEmailDomain(InstitutionEmailDomain $domain, User $actor): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        $domain->forceFill(['status' => 'verified', 'verified_by' => $actor->id, 'verified_at' => now()])->save();
        $this->audit('institution_email_domain_verified', $actor, $domain->university, ['domain' => $domain->domain]);
    }

    public function emailMatchesVerifiedDomain(User $user, University $university): bool
    {
        $domain = $this->emailDomain($user->email);

        return $domain !== '' && InstitutionEmailDomain::query()->where('university_id', $university->id)->where('domain', $domain)->where('status', 'verified')->exists();
    }

    private function assertCanVerify(User $reviewer, University $university, ?Department $department, string $type): void
    {
        app(AcademicOrganisationService::class)->assertReviewer($reviewer, $university, $department, $type);
    }

    private function affiliationTypeFor(User $user): string
    {
        return match ($user->role) {
            'student' => 'student','lecturer' => 'lecturer','department_admin' => 'department_admin','university_admin' => 'university_admin',default => in_array($user->account_type, ['researcher', 'academic_staff', 'non_academic_staff'], true) ? $user->account_type : 'staff'
        };
    }

    private function audit(string $action, User $actor, object $subject, array $newValues = []): void
    {
        AuditLog::log($action,$actor->id,$subject::class,(int) ($subject->id ?? 0),null,$newValues,request()?->ip(),request()?->userAgent());
    }

    private function emailDomain(string $email): string
    {
        $parts = explode('@',Str::lower(trim($email)));

        return count($parts) === 2 ? $parts[1] : '';
    }

    private function host(string $url): string
    {
        return Str::lower((string) (parse_url($url,PHP_URL_HOST) ?: ''));
    }
}
