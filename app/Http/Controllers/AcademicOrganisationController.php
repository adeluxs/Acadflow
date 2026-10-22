<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicMembershipInvitation;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentInstitutionAffiliation;
use App\Models\Semester;
use App\Models\University;
use App\Models\UserInstitutionAffiliation;
use App\Services\AcademicMembershipService;
use App\Services\AcademicOrganisationService;
use App\Services\DepartmentAffiliationService;
use App\Services\FeatureAccessService;
use App\Services\InstitutionAdoptionService;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicOrganisationController extends Controller
{
    public function modules(Request $request, Department $department, AcademicOrganisationService $organisations)
    {
        abort_unless($organisations->canManageDepartment($request->user(), $department), 403);
        $data = $request->validate(['modules' => ['required', 'array'], 'modules.*' => ['required', Rule::in(['enabled', 'maintenance', 'disabled'])]]);
        abort_if(array_diff(array_keys($data['modules']), FeatureAccessService::keys()), 422, 'Unknown module.');
        $department->update(['adoption_settings' => array_merge($department->adoption_settings ?? [], ['modules' => $data['modules']])]);
        AuditLog::log('department_modules_updated', $request->user()->id, $department::class, $department->id, null, ['modules' => $data['modules']], $request->ip(), $request->userAgent());
        FeatureAccessService::flushRequestCache();

        return back()->with('success', 'Department module preferences saved. Platform availability and dependency rules still apply.');
    }

    public function calendar(Request $request, Department $department, AcademicOrganisationService $organisations)
    {
        abort_unless($organisations->canManageDepartment($request->user(), $department), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after:start_date']]);
        DB::transaction(function () use ($department, $data) {
            Department::whereKey($department->id)->lockForUpdate()->firstOrFail();
            AcademicSession::where('department_id', $department->id)->update(['is_active' => false, 'is_current' => false]);
            Semester::whereHas('academicSession', fn ($q) => $q->where('department_id', $department->id))->update(['is_active' => false]);
            $session = AcademicSession::create(['university_id' => $department->faculty->university_id, 'department_id' => $department->id, 'name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'is_active' => true, 'is_current' => true]);
            Semester::create(['academic_session_id' => $session->id, 'name' => $data['name'], 'number' => 1, 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'is_active' => true]);
        }, 3);
        AuditLog::log('department_calendar_created', $request->user()->id, $department::class, $department->id, null, $data, $request->ip(), $request->userAgent());

        return back()->with('success', 'Department academic period created. Other departments and historical enrolments were preserved.');
    }

    public function directory(Request $request, InstitutionAdoptionService $adoption, AcademicOrganisationService $organisations)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120'], 'university_id' => ['nullable', 'integer', 'exists:universities,id']]);
        if (! empty($data['university_id'])) {
            $university = $organisations->directoryInstitution((int) $data['university_id']);
            abort_unless($university, 404);

            return response()->json(['institution' => ['id' => $university->id, 'name' => $university->name, 'adoption_status' => $university->adoption_status],
                'faculties' => $university->faculties->map(fn ($f) => ['id' => $f->id, 'name' => $f->name, 'departments' => $f->departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'adoption_status' => $d->adoption_status])])]);
        }

        return response()->json(['institutions' => $adoption->searchInstitutions($data['q'] ?? '')->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'adoption_status' => $u->adoption_status])]);
    }

    public function index(Request $request, AcademicOrganisationService $organisations)
    {
        $filters = $request->validate(['scope_q' => ['nullable', 'string', 'max:120']]);
        $actor = $request->user();
        $memberships = $actor->institutionAffiliations()->with(['university', 'department'])->latest('id')
            ->simplePaginate(25, ['*'], 'memberships_page')->withQueryString()->fragment('my-memberships');
        // These catalogues supply HTML forms only; API clients discover scope
        // through the directory endpoint instead of loading unused records here.
        $departments = ! $request->expectsJson() && $actor->isAdmin() ? $organisations->departmentIds($actor)
            ->select(['departments.id', 'departments.name', 'departments.faculty_id'])
            ->when($filters['scope_q'] ?? null, fn ($q, $term) => $q->whereRaw('LOWER(departments.name) LIKE ?', ['%'.mb_strtolower(trim($term)).'%']))
            ->with(['faculty:id,university_id', 'faculty.university:id,name'])->orderBy('name')->orderBy('departments.id')
            ->simplePaginate(50, ['*'], 'scope_page')->withQueryString()->fragment('invite-members') : collect();
        $candidates = ! $request->expectsJson() && ($actor->isUniversityAdmin() || $actor->isSuperAdmin()) ? Department::select(['id', 'name', 'faculty_id'])
            ->with(['faculty:id,university_id', 'faculty.university:id,name'])->where('adoption_status', 'independent_active')->when(! $actor->isSuperAdmin(), fn ($q) => $q->whereHas('faculty', fn ($f) => $f->where('university_id', $actor->university_id)))
            ->orderBy('id')->simplePaginate(20, ['*'], 'candidates_page')->withQueryString()->fragment('affiliation-candidates') : collect();
        $affiliations = DepartmentInstitutionAffiliation::with(['department', 'university'])->when(! $actor->isSuperAdmin(), function ($q) use ($actor) {
            if ($actor->isUniversityAdmin()) {
                $q->where('university_id', $actor->university_id);
            } elseif ($actor->isDepartmentAdmin()) {
                $q->where('department_id', $actor->department_id);
            } else {
                $q->whereRaw('1 = 0');
            }
        })->latest('id')->simplePaginate(25, ['*'], 'agreements_page')->withQueryString()->fragment('department-agreements');
        $managedMembers = $actor->isAdmin() ? UserInstitutionAffiliation::with(['user', 'department'])->approved()->when(! $actor->isSuperAdmin(), function ($q) use ($actor, $organisations) {
            $q->where(function ($m) use ($actor, $organisations) {
                $m->whereIn('department_id', $organisations->departmentIds($actor)->select('departments.id'));
                if ($actor->isUniversityAdmin()) {
                    $m->orWhere(fn ($u) => $u->where('university_id', $actor->university_id)->whereNull('department_id'));
                }
            });
        })->latest('id')->simplePaginate(25, ['*'], 'members_page')->withQueryString()->fragment('approved-members') : collect();
        $invitations = $actor->isAdmin() ? AcademicMembershipInvitation::with(['department', 'university'])->when(! $actor->isSuperAdmin(), function ($q) use ($actor, $organisations) {
            $q->where(function ($m) use ($actor, $organisations) {
                $m->whereIn('department_id', $organisations->departmentIds($actor)->select('departments.id'));
                if ($actor->isUniversityAdmin()) {
                    $m->orWhere(fn ($u) => $u->where('university_id', $actor->university_id)->whereNull('department_id'));
                }
            });
        })->latest('id')->simplePaginate(25, ['*'], 'invitations_page')->withQueryString()->fragment('invitation-history') : collect();
        if ($request->expectsJson()) {
            $payload = [];
            foreach (compact('memberships', 'affiliations', 'managedMembers', 'invitations') as $key => $records) {
                // Preserve the existing arrays while exposing navigation for
                // complete histories without COUNT(*) scans on every request.
                $payload[$key] = $records instanceof Paginator ? $records->items() : $records->all();
                if ($records instanceof Paginator) {
                    $payload['pagination'][$key] = ['current_page' => $records->currentPage(), 'has_more_pages' => $records->hasMorePages(), 'next_page_url' => $records->nextPageUrl(), 'previous_page_url' => $records->previousPageUrl()];
                }
            }

            return response()->json($payload);
        }

        return view('institution.organisations', compact('memberships', 'departments', 'candidates', 'affiliations', 'managedMembers', 'invitations'));
    }

    public function switchContext(Request $request, AcademicOrganisationService $organisations)
    {
        $data = $request->validate(['affiliation_id' => ['required', 'integer', 'min:0']]);
        if ($data['affiliation_id']) {
            $membership = $request->user()->institutionAffiliations()->approved()->findOrFail($data['affiliation_id']);
            abort_unless($organisations->contextAvailable($membership), 422, 'This academic organisation is not active.');
        }
        if ($request->hasSession()) {
            $request->session()->put('academic_affiliation_id', (int) $data['affiliation_id']);
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'active_affiliation_id' => (int) $data['affiliation_id'], 'api_header' => 'X-Academic-Affiliation']);
        }

        return redirect()->route('academic.organisations')->with('success', 'Academic workspace changed. Your personal work and other memberships remain intact.');
    }

    public function endMembership(Request $request, UserInstitutionAffiliation $membership, AcademicMembershipService $memberships)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['revoked', 'graduated', 'transferred'])], 'note' => ['required', 'string', 'max:2000']]);
        $memberships->end($request->user(), $membership, $data['status'], $data['note']);

        return $this->done($request, 'Membership updated. Personal records were preserved.');
    }

    public function invite(Request $request, AcademicMembershipService $memberships)
    {
        $data = $request->validate(['university_id' => ['required', 'integer', 'exists:universities,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'], 'role' => ['required', Rule::in(['student', 'lecturer', 'researcher', 'staff', 'department_admin', 'university_admin'])], 'email' => ['required', 'email', 'max:255']]);
        $result = $memberships->invite($request->user(), University::findOrFail($data['university_id']), ! empty($data['department_id']) ? Department::with('faculty')->findOrFail($data['department_id']) : null, $data['role'], $data['email']);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'invitation' => $result['invitation'], 'url' => $result['url']], 201);
        }

        return back()->with('success', 'Invitation created. Share this secure link with the named recipient. Existing members also receive an in-app notification.')->with('invitation_url', $result['url']);
    }

    public function invitation(Request $request, string $token)
    {
        $invitation = AcademicMembershipInvitation::with(['department', 'university'])->where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless(hash_equals($invitation->email, strtolower($request->user()->email)), 403, 'Sign in with the invited email address.');
        abort_if($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, 'This invitation is no longer available.');

        return view('institution.accept-invitation', compact('invitation', 'token'));
    }

    public function acceptInvitation(Request $request, string $token, AcademicMembershipService $memberships)
    {
        $memberships->accept($request->user(), $token);

        return $this->done($request, 'Invitation accepted using your existing account. Administrator nominations require Platform Admin verification.');
    }

    public function revokeInvitation(Request $request, AcademicMembershipInvitation $invitation, AcademicMembershipService $memberships)
    {
        $memberships->revokeInvitation($request->user(), $invitation);

        return $this->done($request, 'Invitation revoked.');
    }

    public function requestAffiliation(Request $request, DepartmentAffiliationService $affiliations)
    {
        $data = $request->validate(['university_id' => ['required', 'integer', 'exists:universities,id'], 'department_id' => ['required', 'integer', 'exists:departments,id'], 'billing_owner' => ['required', Rule::in(['department', 'institution'])], 'share_academic_data' => ['required', 'boolean'], 'inherit_features' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:2000']]);
        $affiliation = $affiliations->request($request->user(), University::findOrFail($data['university_id']), Department::with('faculty')->findOrFail($data['department_id']), $data, $data['billing_owner'], $data['note'] ?? null);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'affiliation' => $affiliation], 201);
        }

        return back()->with('success', 'Affiliation proposal sent. Department consent and Platform Admin verification are required.');
    }

    public function respondAffiliation(Request $request, DepartmentInstitutionAffiliation $affiliation, DepartmentAffiliationService $affiliations)
    {
        // Avoid a named HTML control shadowing HTMLFormElement.action, which
        // shared loader and browser-push scripts use to identify form URLs.
        $request->merge(['action' => $request->input('affiliation_action', $request->input('action'))]);
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'decline', 'verify', 'withdraw', 'end'])], 'note' => ['nullable', 'string', 'max:2000']]);
        $affiliations->respond($request->user(), $affiliation, $data['action'], $data['note'] ?? null);

        return $this->done($request, 'Department affiliation updated. Existing data and balances have been preserved.');
    }

    private function done(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['success' => true, 'message' => $message]) : redirect()->route('academic.organisations')->with('success', $message);
    }
}
