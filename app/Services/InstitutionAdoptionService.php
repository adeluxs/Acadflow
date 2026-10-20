<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
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
        if (mb_strlen($term) < 2) return collect();
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
            ->withCount(['users','faculties'])
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(short_name) = ? THEN 1 ELSE 2 END', [$normalized,$normalized])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function institutionState(University $university): string
    {
        $status = (string) ($university->adoption_status ?: 'unclaimed');
        if ($status === 'active') return 'active';
        if (InstitutionOnboardingRequest::query()->where('university_id',$university->id)->whereIn('status',['pending','in_review'])->exists()) return 'claim_pending';
        return $status;
    }

    public function findPotentialMatch(array $data, ?int $excludeId = null): ?University
    {
        $name = Str::lower(trim((string)($data['institution_name'] ?? '')));
        $code = Str::lower(trim((string)($data['code'] ?? '')));
        $short = Str::lower(trim((string)($data['short_name'] ?? '')));
        $websiteHost = $this->host((string)($data['website'] ?? ''));
        $emailDomain = $this->emailDomain((string)($data['email'] ?? ''));

        return University::query()
            ->where('code', '!=', IndependentAcademicContextService::UNIVERSITY_CODE)
            ->when($excludeId, fn(Builder $q) => $q->whereKeyNot($excludeId))
            ->where(function (Builder $q) use ($name,$code,$short,$websiteHost,$emailDomain): void {
                if ($name !== '') $q->orWhereRaw('LOWER(name) = ?', [$name]);
                if ($code !== '') $q->orWhereRaw('LOWER(code) = ?', [$code]);
                if ($short !== '') $q->orWhereRaw('LOWER(short_name) = ?', [$short]);
                if ($websiteHost !== '') $q->orWhere('website','like','%'.$websiteHost.'%');
                if ($emailDomain !== '') $q->orWhere('email','like','%@'.$emailDomain);
            })->first();
    }

    public function createClaim(User $requester, University $university, array $data): InstitutionOnboardingRequest
    {
        if ($this->institutionState($university) === 'active') {
            throw ValidationException::withMessages(['university_id'=>['This institution already uses AcadSyntra. Request institution access instead.']]);
        }
        if (InstitutionOnboardingRequest::query()->where('university_id',$university->id)->whereIn('status',['pending','in_review'])->exists()) {
            throw ValidationException::withMessages(['university_id'=>['An onboarding claim for this institution is already under review.']]);
        }

        $claim = InstitutionOnboardingRequest::create([
            'requester_user_id'=>$requester->id,'university_id'=>$university->id,
            'institution_name'=>$university->name,'short_name'=>$university->short_name,'code'=>$university->code,
            'institution_type'=>$university->institution_type ?: ($data['institution_type'] ?? 'university'),
            'ownership'=>$university->ownership,'state'=>$university->state,'regulator'=>$university->regulator,
            'email'=>$data['email'] ?? $university->email,'phone'=>$data['phone'] ?? $university->phone,
            'website'=>$data['website'] ?? $university->website,'address'=>$university->address,
            'representative_title'=>$data['representative_title'],'request_type'=>'claim_existing','status'=>'pending',
            'metadata'=>['evidence'=>$data['evidence'] ?? null],
        ]);
        $university->forceFill(['adoption_status'=>'claim_pending'])->save();
        $this->audit('institution_claim_submitted',$requester,$university,['request_id'=>$claim->id]);
        return $claim;
    }

    public function recordSelfDeclared(User $user, string $source = 'onboarding'): ?UserInstitutionAffiliation
    {
        if (! $user->university_id) return null;
        $type = $this->affiliationTypeFor($user);
        return UserInstitutionAffiliation::query()->updateOrCreate(
            ['user_id'=>$user->id,'university_id'=>$user->university_id,'affiliation_type'=>$type],
            ['faculty_id'=>$user->faculty_id,'department_id'=>$user->department_id,'status'=>'self_declared','source'=>$source]
        );
    }

    public function requestAffiliationVerification(User $user, University $university, ?Department $department, string $requestedRole, ?string $identifier = null, string $method = 'admin_review'): InstitutionAccessRequest
    {
        if (! in_array($requestedRole,['student','lecturer','researcher','department_admin','university_admin','staff'],true)) {
            throw ValidationException::withMessages(['requested_role'=>['Unsupported institution role.']]);
        }
        if ($department && (int)$department->faculty?->university_id !== (int)$university->id) {
            throw ValidationException::withMessages(['department_id'=>['The selected department does not belong to this institution.']]);
        }
        $existing = InstitutionAccessRequest::query()->where('user_id',$user->id)->where('university_id',$university->id)->whereIn('status',['pending','in_review'])->first();
        if ($existing) return $existing;

        $request = InstitutionAccessRequest::create([
            'user_id'=>$user->id,'university_id'=>$university->id,'department_id'=>$department?->id,'requested_role'=>$requestedRole,
            'status'=>'pending','verification_method'=>$method,
            'identifier_hash'=>$identifier ? hash('sha256', Str::lower(trim($identifier))) : null,
            'identifier_last4'=>$identifier ? Str::substr(trim($identifier),-4) : null,
        ]);
        UserInstitutionAffiliation::query()->updateOrCreate(
            ['user_id'=>$user->id,'university_id'=>$university->id,'affiliation_type'=>$requestedRole],
            ['faculty_id'=>$department?->faculty_id,'department_id'=>$department?->id,'status'=>'pending_verification','verification_method'=>$method,'source'=>'access_request','identifier_hash'=>$request->identifier_hash,'identifier_last4'=>$request->identifier_last4]
        );
        $this->audit('institution_access_requested',$user,$university,['request_id'=>$request->id,'requested_role'=>$requestedRole]);
        return $request;
    }

    public function verifyAffiliation(User $subject, University $university, ?Department $department, string $type, User $reviewer, string $method = 'admin_review', ?string $note = null): UserInstitutionAffiliation
    {
        $this->assertCanVerify($reviewer,$university,$department,$type);
        return DB::transaction(function() use($subject,$university,$department,$type,$reviewer,$method,$note): UserInstitutionAffiliation {
            $affiliation = UserInstitutionAffiliation::query()->updateOrCreate(
                ['user_id'=>$subject->id,'university_id'=>$university->id,'affiliation_type'=>$type],
                ['faculty_id'=>$department?->faculty_id ?? $subject->faculty_id,'department_id'=>$department?->id ?? $subject->department_id,'status'=>'verified','verification_method'=>$method,'source'=>$method,'verified_by'=>$reviewer->id,'verified_at'=>now(),'review_note'=>$note]
            );
            $role = in_array($type,['student','lecturer','department_admin','university_admin'],true) ? $type : $subject->role;
            $updates=['university_id'=>$university->id,'role'=>$role];
            if ($department) $updates += ['department_id'=>$department->id,'faculty_id'=>$department->faculty_id];
            $subject->forceFill($updates)->save();
            $this->audit('institution_affiliation_verified',$reviewer,$subject,['university_id'=>$university->id,'department_id'=>$department?->id,'type'=>$type]);
            $this->notifications->send($subject,'institution_affiliation_verified','Institution affiliation verified',"Your AcadSyntra account is now verified with {$university->name}.",['university_id'=>$university->id,'department_id'=>$department?->id]);
            return $affiliation;
        },3);
    }

    public function rejectAccessRequest(InstitutionAccessRequest $access, User $reviewer, ?string $note = null): void
    {
        $this->assertCanVerify($reviewer,$access->university,$access->department,$access->requested_role);
        $access->forceFill(['status'=>'rejected','reviewed_by_user_id'=>$reviewer->id,'reviewed_at'=>now(),'review_note'=>$note])->save();
        UserInstitutionAffiliation::query()->where('user_id',$access->user_id)->where('university_id',$access->university_id)->where('affiliation_type',$access->requested_role)->where('status','pending_verification')->update(['status'=>'rejected','review_note'=>$note]);
        $this->notifications->send($access->user,'institution_access_rejected','Institution access request reviewed','Your institution access request was not approved.',['request_id'=>$access->id]);
    }

    public function approveAccessRequest(InstitutionAccessRequest $access, User $reviewer, ?string $note = null): UserInstitutionAffiliation
    {
        abort_unless(in_array($access->status,['pending','in_review'],true),422,'This access request was already reviewed.');
        $affiliation=$this->verifyAffiliation($access->user,$access->university,$access->department,$access->requested_role,$reviewer,$access->verification_method ?: 'admin_review',$note);
        $access->forceFill(['status'=>'approved','reviewed_by_user_id'=>$reviewer->id,'reviewed_at'=>now(),'review_note'=>$note])->save();
        return $affiliation;
    }

    public function createDepartmentAdoption(User $requester, University $university, ?Faculty $faculty, ?Department $department, array $data): DepartmentAdoptionRequest
    {
        if ($department && (int)$department->faculty?->university_id !== (int)$university->id) throw ValidationException::withMessages(['department_id'=>['Department does not belong to the selected institution.']]);
        if ($department && in_array($department->adoption_status,['independent_active','institution_managed'],true)) throw ValidationException::withMessages(['department_id'=>['This department is already active on AcadSyntra.']]);
        $pending=DepartmentAdoptionRequest::query()->where('university_id',$university->id)->when($department,fn($q)=>$q->where('department_id',$department->id))->whereIn('status',['pending','in_review'])->exists();
        if($pending) throw ValidationException::withMessages(['department_id'=>['A department adoption request is already under review.']]);
        return DepartmentAdoptionRequest::create([
            'requester_user_id'=>$requester->id,'university_id'=>$university->id,'faculty_id'=>$faculty?->id ?? $department?->faculty_id,'department_id'=>$department?->id,
            'department_name'=>$department?->name ?? $data['department_name'],'department_code'=>$department?->code ?? ($data['department_code']??null),
            'representative_title'=>$data['representative_title'],'status'=>'pending','metadata'=>['evidence'=>$data['evidence']??null],
        ]);
    }

    public function approveDepartmentAdoption(DepartmentAdoptionRequest $request, User $reviewer, ?string $note = null): Department
    {
        abort_unless($reviewer->isSuperAdmin(),403);
        abort_unless(in_array($request->status,['pending','in_review'],true),422,'This department adoption request has already been reviewed.');
        return DB::transaction(function() use($request,$reviewer,$note): Department {
            $department=$request->department;
            if(!$department){
                $faculty=$request->faculty ?: Faculty::query()->where('university_id',$request->university_id)->orderBy('id')->first();
                if(!$faculty) throw ValidationException::withMessages(['department'=>['Create or link a faculty before approving this department.']]);
                $department=Department::firstOrCreate(['faculty_id'=>$faculty->id,'name'=>$request->department_name],['short_name'=>$request->department_name,'code'=>$request->department_code,'is_active'=>true]);
            }
            $department->forceFill(['adoption_status'=>$request->university?->adoption_status === 'active' ? 'institution_managed' : 'independent_active','adopted_at'=>now(),'is_active'=>true])->save();
            $request->requester->forceFill(['university_id'=>$request->university_id,'faculty_id'=>$department->faculty_id,'department_id'=>$department->id,'role'=>'department_admin'])->save();
            $this->verifyAffiliation($request->requester,$request->university,$department,'department_admin',$reviewer,'department_adoption',$note);
            $request->forceFill(['department_id'=>$department->id,'status'=>'approved','reviewed_by_user_id'=>$reviewer->id,'reviewed_at'=>now(),'review_note'=>$note])->save();
            $this->audit('department_adoption_approved',$reviewer,$department,['request_id'=>$request->id]);
            return $department;
        },3);
    }

    public function migrateIndependentDepartments(University $university, User $reviewer): int
    {
        abort_unless($reviewer->isSuperAdmin() || ($reviewer->isUniversityAdmin() && $reviewer->university_id === $university->id),403);
        $count=Department::query()->whereHas('faculty',fn($q)=>$q->where('university_id',$university->id))->where('adoption_status','independent_active')->update(['adoption_status'=>'institution_managed','adopted_at'=>now()]);
        if($count) $this->audit('independent_departments_migrated',$reviewer,$university,['count'=>$count]);
        return $count;
    }

    public function canReceiveInstitutionBenefits(User $user, ?int $universityId = null): bool
    {
        $universityId ??= $user->university_id;
        if(!$universityId) return false;
        $university=University::find($universityId);
        return (bool)($university && $university->adoption_status === 'active' && $user->hasVerifiedInstitutionAffiliation($universityId));
    }

    public function proposeEmailDomain(University $university, string $domain, User $actor): InstitutionEmailDomain
    {
        $domain=Str::lower(trim($domain));
        $domain=preg_replace('/^@/','',$domain) ?: '';
        if(!filter_var('verify@'.$domain,FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['domain'=>['Enter a valid institution email domain.']]);
        $existing=InstitutionEmailDomain::query()->where('domain',$domain)->first();
        if($existing && (int)$existing->university_id !== (int)$university->id) throw ValidationException::withMessages(['domain'=>['This email domain is already associated with another institution.']]);
        return InstitutionEmailDomain::query()->updateOrCreate(['domain'=>$domain],['university_id'=>$university->id,'status'=>$existing?->status ?? 'proposed','proposed_by'=>$actor->id]);
    }

    public function verifyEmailDomain(InstitutionEmailDomain $domain, User $actor): void
    {
        abort_unless($actor->isSuperAdmin(),403);
        $domain->forceFill(['status'=>'verified','verified_by'=>$actor->id,'verified_at'=>now()])->save();
        $this->audit('institution_email_domain_verified',$actor,$domain->university,['domain'=>$domain->domain]);
    }

    public function emailMatchesVerifiedDomain(User $user, University $university): bool
    {
        $domain=$this->emailDomain($user->email);
        return $domain !== '' && InstitutionEmailDomain::query()->where('university_id',$university->id)->where('domain',$domain)->where('status','verified')->exists();
    }

    private function assertCanVerify(User $reviewer, University $university, ?Department $department, string $type): void
    {
        if ($reviewer->isSuperAdmin()) return;
        if (in_array($type,['university_admin','department_admin'],true)) abort(403,'Only Platform Admin can approve administrator access.');
        if ($reviewer->isUniversityAdmin() && (int)$reviewer->university_id === (int)$university->id) return;
        if ($reviewer->isDepartmentAdmin() && $department && (int)$reviewer->department_id === (int)$department->id && in_array($type,['student','lecturer','researcher','staff'],true)) return;
        abort(403);
    }

    private function affiliationTypeFor(User $user): string
    {
        return match($user->role){'student'=>'student','lecturer'=>'lecturer','department_admin'=>'department_admin','university_admin'=>'university_admin',default=>in_array($user->account_type,['researcher','academic_staff','non_academic_staff'],true)?$user->account_type:'staff'};
    }

    private function audit(string $action, User $actor, object $subject, array $newValues = []): void
    {
        AuditLog::log($action,$actor->id,$subject::class,(int)($subject->id ?? 0),null,$newValues,request()?->ip(),request()?->userAgent());
    }

    private function emailDomain(string $email): string
    {
        $parts=explode('@',Str::lower(trim($email))); return count($parts)===2 ? $parts[1] : '';
    }
    private function host(string $url): string { return Str::lower((string)(parse_url($url,PHP_URL_HOST) ?: '')); }
}
