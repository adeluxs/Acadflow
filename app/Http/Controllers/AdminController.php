<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CommercialAccount;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\University;
use App\Models\User;
use App\Services\AdminLookupService;
use App\Services\AcademicOrganisationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    // Admin Dashboard - redirects to role-specific admin page
    public function dashboard()
    {
        $user = Auth::user();

        return match ($user->role) {
            'super_admin' => redirect()->route('admin.universities'),
            'university_admin' => redirect()->route('admin.faculties'),
            'department_admin' => redirect()->route('admin.department'),
            'lecturer' => redirect()->route('lecturer.courses'),
            'student' => redirect()->route('dashboard'),
            default => redirect()->route('dashboard'),
        };
    }

    // Department Admin: Dashboard
    public function department()
    {
        $departmentId = Auth::user()->department_id;
        $department=Department::with('faculty.university')->findOrFail($departmentId);
        abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment(Auth::user(),$department),403);
        $members=\App\Models\UserInstitutionAffiliation::approved()->where('department_id',$departmentId);
        $memberCounts = $members->selectRaw("SUM(CASE WHEN affiliation_type = 'student' THEN 1 ELSE 0 END) AS students, SUM(CASE WHEN affiliation_type = 'lecturer' THEN 1 ELSE 0 END) AS lecturers")->first();

        $stats = [
            'total_students' => (int) $memberCounts?->students,
            'total_lecturers' => (int) $memberCounts?->lecturers,
            'total_courses' => $department->courses()->count(),
        ];
        $pending=\App\Models\InstitutionAccessRequest::where('department_id',$departmentId)->whereIn('status',['pending','in_review'])->count();
        $semester=app(\App\Services\AcademicContextService::class)->activeSemesterForDepartment((int)$departmentId);
        return view('admin.department', compact('stats','department','pending','semester'));
    }

    // Admin: User management
    public function users(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['member', 'student', 'lecturer', 'department_admin', 'university_admin', 'super_admin'])],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $query = User::query()->select(['id', 'first_name', 'last_name', 'email', 'role', 'department_id', 'is_active']);
        if ($search = trim((string) $request->input('q', ''))) {
            $query->where(fn ($q) => $q->where('first_name', 'like', '%'.$search.'%')->orWhere('last_name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }
        if ($request->filled('status')) $query->where('is_active', $request->status === 'active');

        // The User organisation boundary resolves approved memberships across
        // primary profiles; do not narrow these back to self-declared profile IDs.
        if ($request->role) {
            if($user->isSuperAdmin())$query->where('role',$request->role);
            else $query->whereHas('institutionAffiliations',fn($q)=>$q->approved()->where('affiliation_type',$request->role)->whereIn('department_id',app(\App\Services\AcademicOrganisationService::class)->departmentIds($user)->select('departments.id')));
        }

        if ($request->department_id) {
            if ($user->isDepartmentAdmin() && $request->department_id != $user->department_id) {
                abort(403, 'You can only view users in your department.');
            }
            if ($user->isSuperAdmin()) $query->where('department_id', $request->department_id);
            else $query->whereHas('institutionAffiliations', fn ($q) => $q->approved()->where('department_id', $request->integer('department_id'))
                ->whereIn('department_id', app(AcademicOrganisationService::class)->departmentIds($user)->select('departments.id')));
        }

        $users = $query->with('department:id,name')->latest()->latest('id')->paginate(20)->withQueryString();
        $selectedDepartment = app(AdminLookupService::class)->selected($user, 'departments', $request->input('department_id'));
        $inviteDepartment = app(AdminLookupService::class)->selected($user, 'departments', old('department_id'));

        return view('admin.users', compact('users', 'selectedDepartment', 'inviteDepartment'));
    }

    public function editUser(Request $request, User $user)
    {
        $this->authorize('update', $user);
        if (! $request->user()->isSuperAdmin()) return redirect()->route('academic.organisations')->with('info', 'Manage this person through their scoped membership.');
        $selectedDepartment = app(AdminLookupService::class)->selected($request->user(), 'departments', old('department_id', $user->department_id), includeInactive: true);

        return view('admin.users-edit', compact('user', 'selectedDepartment'));
    }

    public function updateUser(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $actor = $request->user();
        if(!$actor->isSuperAdmin())return redirect()->route('academic.organisations')->with('info','Manage this person through their scoped membership. Organisation administrators cannot change another account’s global role, identity or active status.');

        abort_if($user->isSuperAdmin() && ! $actor->isSuperAdmin(), 403);
        abort_if($actor->id === $user->id && ! $request->boolean('is_active'), 422, 'You cannot deactivate your own account.');

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in(['member', 'student', 'lecturer', 'department_admin', 'university_admin'])],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['role'] !== $user->role && ! Gate::allows('canInviteRole', [User::class, $data['role']])) {
            abort(403, 'You cannot assign this role.');
        }

        if (in_array($data['role'], ['student', 'lecturer', 'department_admin'], true) && empty($data['department_id'])) {
            throw ValidationException::withMessages(['department_id' => ['An institutional department is required for this role.']]);
        }

        $department = empty($data['department_id']) ? null : Department::with('faculty')->findOrFail($data['department_id']);
        if ($department) {
            $departmentUniversityId = $department->faculty?->university_id;
            if ($actor->isDepartmentAdmin() && $department->id !== $actor->department_id) abort(403);
            if ($actor->isUniversityAdmin() && $departmentUniversityId !== $actor->university_id) abort(403);
        }

        if ($data['role'] === 'university_admin' && ! $actor->isSuperAdmin()) abort(403);

        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
            'department_id' => $department?->id,
            'faculty_id' => $department?->faculty_id,
            'university_id' => $data['role'] === 'member' ? null : ($department?->faculty?->university_id ?? $user->university_id),
            'is_active' => (bool) $data['is_active'],
        ]);

        return redirect()->route('admin.users')->with('success', 'User account updated.');
    }

    // Admin: Invite user
    public function inviteUser(Request $request)
    {
        $this->authorize('create',User::class);
        $data=$request->validate(['email'=>['required','email','max:255'],'role'=>['required','in:lecturer,student,department_admin'],'department_id'=>['required','integer','exists:departments,id']]);
        abort_unless(Gate::allows('canInviteRole',[User::class,$data['role']]),403);
        $department=Department::with('faculty.university')->findOrFail($data['department_id']);
        $result=app(\App\Services\AcademicMembershipService::class)->invite($request->user(),$department->faculty->university,$department,$data['role'],$data['email']);
        return redirect()->route('academic.organisations')->with('success','Secure invitation created. Existing users keep their account. Administrator nominations await Platform Admin verification.')->with('invitation_url',$result['url']);
    }

    // Admin: Reports
    public function reports(Request $request)
    {
        $user = Auth::user();

        $userScope = fn ($query) => $query;
        $courseScope = fn ($query) => $query;

        if ($user->isDepartmentAdmin()) {
            $userScope = fn ($query) => $query->where('department_id', $user->department_id);
            $courseScope = fn ($query) => $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $userScope = fn ($query) => $query->where('university_id', $user->university_id);
            $departmentIds = Department::whereHas('faculty', fn ($q) => $q->where('university_id', $user->university_id))->select('departments.id');
            $courseScope = fn ($query) => $query->whereIn('department_id', $departmentIds);
        }

        // Use one conditional aggregate per dataset instead of issuing a separate COUNT query
        // for every dashboard card. This keeps the same report contract with fewer round trips.
        $submissionStats = Submission::query()
            ->whereHas('course', $courseScope)
            ->selectRaw("COUNT(*) AS total")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted', 'under_review') THEN 1 ELSE 0 END) AS pending")
            ->selectRaw("SUM(CASE WHEN status = 'graded' THEN 1 ELSE 0 END) AS graded")
            ->first();

        $attendanceStats = AttendanceRecord::query()
            ->whereHas('session', fn ($session) => $session->whereHas('course', $courseScope))
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) AS present")
            ->first();

        $invoiceStats = Invoice::query()
            ->whereHas('user', $userScope)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending")
            ->first();

        $attendanceTotal = (int) ($attendanceStats?->total ?? 0);
        $reports = [
            'submissions' => [
                'total' => (int) ($submissionStats?->total ?? 0),
                'pending' => (int) ($submissionStats?->pending ?? 0),
                'graded' => (int) ($submissionStats?->graded ?? 0),
            ],
            'attendance' => [
                'total_sessions' => AttendanceSession::whereHas('course', $courseScope)->count(),
                'present_rate' => $attendanceTotal > 0
                    ? round((((int) ($attendanceStats?->present ?? 0)) / $attendanceTotal) * 100, 1)
                    : 0,
            ],
            'billing' => [
                'total_invoices' => (int) ($invoiceStats?->total ?? 0),
                'paid' => (int) ($invoiceStats?->paid ?? 0),
                'pending' => (int) ($invoiceStats?->pending ?? 0),
            ],
        ];

        return view('admin.reports', compact('reports'));
    }

    // Admin: Export reports
    public function exportReports(Request $request, string $type): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(in_array($type, ['submissions', 'attendance', 'billing'], true), 404);

        $user = $request->user();
        $userScope = fn ($query) => $query;
        $courseScope = fn ($query) => $query;

        if ($user->isDepartmentAdmin()) {
            $userScope = fn ($query) => $query->where('department_id', $user->department_id);
            $courseScope = fn ($query) => $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $userScope = fn ($query) => $query->where('university_id', $user->university_id);
            $departmentIds = Department::whereHas('faculty', fn ($faculty) => $faculty
                ->where('university_id', $user->university_id))
                ->select('departments.id');
            $courseScope = fn ($query) => $query->whereIn('department_id', $departmentIds);
        }

        [$headers, $query, $rowMapper] = match ($type) {
            'submissions' => [
                ['Submission ID', 'Student', 'Email', 'Course', 'Title', 'Status', 'Submitted At', 'Score'],
                Submission::query()
                    ->select(['id', 'uuid', 'user_id', 'course_id', 'title', 'status', 'submitted_at'])
                    ->whereHas('course', $courseScope)
                    ->with([
                        'user:id,first_name,last_name,email',
                        'course:id,code',
                        'grade:id,submission_id,score',
                    ]),
                fn (Submission $submission) => [
                    $submission->uuid,
                    $submission->user?->full_name,
                    $submission->user?->email,
                    $submission->course?->code,
                    $submission->title,
                    $submission->status,
                    $submission->submitted_at?->toDateTimeString(),
                    $submission->grade?->score,
                ],
            ],
            'attendance' => [
                ['Session ID', 'Course', 'Student', 'Email', 'Status', 'Check-in At', 'Verified', 'Notes'],
                AttendanceRecord::query()
                    ->select(['id', 'session_id', 'user_id', 'status', 'check_in_at', 'is_verified', 'verification_notes'])
                    ->whereHas('session', function ($session) use ($courseScope, $request): void {
                        $session->whereHas('course', $courseScope);
                        if ($request->filled('session_id')) {
                            $session->whereKey($request->integer('session_id'));
                        }
                    })
                    ->with([
                        'user:id,first_name,last_name,email',
                        'session:id,uuid,course_id',
                        'session.course:id,code',
                    ]),
                fn (AttendanceRecord $record) => [
                    $record->session?->uuid,
                    $record->session?->course?->code,
                    $record->user?->full_name,
                    $record->user?->email,
                    $record->status,
                    $record->check_in_at?->toDateTimeString(),
                    $record->is_verified ? 'Yes' : 'No',
                    $record->verification_notes,
                ],
            ],
            'billing' => [
                ['Invoice ID', 'Student', 'Email', 'Amount', 'Status', 'Due Date', 'Paid At', 'Transaction Reference'],
                Invoice::query()
                    ->select(['id', 'uuid', 'user_id', 'amount', 'status', 'due_date', 'paid_at', 'transaction_ref'])
                    ->whereHas('user', $userScope)
                    ->with('user:id,first_name,last_name,email'),
                fn (Invoice $invoice) => [
                    $invoice->uuid,
                    $invoice->user?->full_name,
                    $invoice->user?->email,
                    $invoice->amount,
                    $invoice->status,
                    $invoice->due_date?->toDateString(),
                    $invoice->paid_at?->toDateTimeString(),
                    $invoice->transaction_ref,
                ],
            ],
        };

        $filename = sprintf('acadsyntra-%s-%s.csv', $type, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($headers, $query, $rowMapper): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);

            // lazyByIdDesc keeps memory bounded while preserving newest-first exports.
            foreach ($query->lazyByIdDesc(500) as $model) {
                fputcsv($stream, $rowMapper($model));
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // University Admin: Manage faculties
    public function faculties(Request $request)
    {
        $actor = Auth::user();
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $faculties = Faculty::query()
            ->select(['id', 'university_id', 'name', 'code', 'dean_id', 'is_active'])
            ->with(['university:id,name', 'dean:id,first_name,last_name'])
            ->withCount('departments')
            ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('university_id', $actor->university_id))
            ->when($search = trim((string) $request->input('q', '')), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
        $lookups = app(AdminLookupService::class);
        $selectedUniversity = $actor->isSuperAdmin() ? $lookups->selected($actor, 'universities', old('university_id')) : null;
        $selectedDean = $lookups->selected($actor, 'deans', old('dean_id'), ['university_id' => old('university_id')]);

        return view('admin.faculties', compact('faculties', 'selectedUniversity', 'selectedDean'));
    }

    public function createFaculty(Request $request)
    {
        $this->authorize('create', Faculty::class);
        $actor = $request->user();
        $data = $request->validate([
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:30'],
            'dean_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $universityId = $actor->isSuperAdmin() ? ($data['university_id'] ?? null) : $actor->university_id;
        throw_if(! $universityId, ValidationException::withMessages(['university_id' => ['Select a university.']]));
        $request->validate(['code' => [Rule::unique('faculties', 'code')->where('university_id', $universityId)]]);
        if (! empty($data['dean_id'])) abort_unless(User::whereKey($data['dean_id'])->where('university_id', $universityId)->exists(), 422, 'Dean must belong to the university.');
        Faculty::create([
            'university_id' => $universityId, 'name' => $data['name'], 'short_name' => $data['short_name'],
            'code' => $data['code'], 'dean_id' => $data['dean_id'] ?? null, 'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        return back()->with('success', 'Faculty created.');
    }

    public function editFaculty(Request $request, Faculty $faculty)
    {
        $this->authorize('update', $faculty);
        $selectedDean = app(AdminLookupService::class)->selected($request->user(), 'deans', old('dean_id', $faculty->dean_id), ['university_id' => $faculty->university_id], includeInactive: true);
        return view('admin.faculties-edit', compact('faculty', 'selectedDean'));
    }

    public function updateFaculty(Request $request, Faculty $faculty)
    {
        $this->authorize('update', $faculty);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:30', Rule::unique('faculties', 'code')->where('university_id', $faculty->university_id)->ignore($faculty->id)],
            'dean_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);
        if (! empty($data['dean_id'])) abort_unless(User::whereKey($data['dean_id'])->where('university_id', $faculty->university_id)->exists(), 422, 'Dean must belong to the university.');
        $faculty->update($data);
        return redirect()->route('admin.faculties')->with('success', 'Faculty updated.');
    }

    // Super Admin: Manage universities
    public function universities(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'institution_type' => ['nullable', Rule::in(['university', 'polytechnic'])]]);
        $universities = University::query()
            ->select(['id', 'name', 'code', 'state', 'institution_type', 'ownership', 'regulator', 'is_active'])
            ->withCount(['faculties', 'users'])
            ->when($search = trim((string) $request->input('q', '')), fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%')))
            ->when($request->filled('institution_type'), fn ($query) => $query->where('institution_type', $request->institution_type))
            ->orderBy('institution_type')
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return view('admin.universities', compact('universities'));
    }

    public function editUniversity(University $university)
    {
        $this->authorize('update', $university);
        return view('admin.universities-edit', compact('university'));
    }

    public function updateUniversity(Request $request, University $university)
    {
        $this->authorize('update', $university);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:10', Rule::unique('universities', 'code')->ignore($university->id)],
            'institution_type' => ['required', Rule::in(['university', 'polytechnic'])],
            'ownership' => ['nullable', Rule::in(['Federal', 'State', 'Private'])],
            'state' => ['nullable', 'string', 'max:80'],
            'regulator' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:500'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'timezone'],
            'is_active' => ['required', 'boolean'],
        ]);
        $university->update($data);
        return redirect()->route('admin.universities')->with('success', 'Institution updated.');
    }

    public function createUniversity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'short_name' => 'required|string|max:50',
            'code' => 'required|string|max:10|unique:universities',
            'institution_type' => ['required', Rule::in(['university', 'polytechnic'])],
            'ownership' => ['nullable', Rule::in(['Federal', 'State', 'Private'])],
            'state' => ['nullable', 'string', 'max:80'],
            'regulator' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:500'],
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'timezone' => ['nullable', 'timezone'],
        ]);

        $validated['timezone'] = $validated['timezone'] ?? 'Africa/Lagos';
        $validated['is_active'] = true;
        University::create($validated);

        return back()->with('success', 'Institution created.');
    }

    // Settings - redirect to SettingsController
    public function settings()
    {
        return redirect()->route('admin.settings');
    }
}
