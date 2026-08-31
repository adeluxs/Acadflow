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
        $stats = [
            'total_students' => User::where('department_id', Auth::user()->department_id)
                ->where('role', 'student')->count(),
            'total_lecturers' => User::where('department_id', Auth::user()->department_id)
                ->where('role', 'lecturer')->count(),
            'total_courses' => Department::find(Auth::user()->department_id)->courses()->count(),
        ];

        return view('admin.department', compact('stats'));
    }

    // Admin: User management
    public function users(Request $request)
    {
        $user = Auth::user();

        $query = User::query();

        // Filter by scope based on role
        if ($user->isDepartmentAdmin()) {
            $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $query->where('university_id', $user->university_id);
        }

        if ($request->role) {
            $query->where('role', $request->role);
        }

        if ($request->department_id) {
            if ($user->isDepartmentAdmin() && $request->department_id != $user->department_id) {
                abort(403, 'You can only view users in your department.');
            }
            $query->where('department_id', $request->department_id);
        }

        $users = $query->with('department')->latest()->paginate(20);

        // Only show departments they have access to
        if ($user->isDepartmentAdmin()) {
            $departments = Department::where('id', $user->department_id)->where('is_active', true)->get();
        } elseif ($user->isUniversityAdmin()) {
            $departments = Department::whereHas('faculty', fn ($faculty) => $faculty
                ->where('university_id', $user->university_id))
                ->where('is_active', true)
                ->get();
        } else {
            $departments = Department::where('is_active', true)->get();
        }

        return view('admin.users', compact('users', 'departments'));
    }

    public function editUser(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $departments = $this->departmentsForAdmin($request->user());

        return view('admin.users-edit', compact('user', 'departments'));
    }

    public function updateUser(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $actor = $request->user();

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
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:lecturer,student,department_admin'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        if (! Gate::allows('canInviteRole', [User::class, $validated['role']])) {
            abort(403, 'You cannot invite users with this role.');
        }

        $inviter = $request->user();
        $department = Department::with('faculty')->findOrFail($validated['department_id']);
        $departmentUniversityId = $department->faculty?->university_id;

        if ($inviter->isDepartmentAdmin() && $department->id !== $inviter->department_id) {
            abort(403, 'You can only invite users to your department.');
        }

        if ($inviter->isUniversityAdmin() && $departmentUniversityId !== $inviter->university_id) {
            abort(403, 'You can only invite users to departments in your university.');
        }

        if ($validated['role'] === 'department_admin') {
            $commercialAccount = CommercialAccount::query()
                ->where('university_id', $departmentUniversityId)
                ->where('status', 'active')
                ->first();
            $maxAdmins = data_get($commercialAccount?->metadata, 'max_administrators');
            if ($maxAdmins !== null) {
                $currentCount = User::query()
                    ->where('role', 'department_admin')
                    ->where('department_id', $department->id)
                    ->count();

                if ($currentCount >= $maxAdmins) {
                    return back()->withErrors([
                        'role' => "Maximum number of administrators ({$maxAdmins}) reached for this institution's commercial access configuration.",
                    ]);
                }
            }
        }

        $invitedUser = User::create([
            'uuid' => (string) Str::uuid(),
            'university_id' => $departmentUniversityId,
            'department_id' => $department->id,
            'email' => $validated['email'],
            'password' => Str::random(40),
            'role' => $validated['role'],
            'student_id' => $validated['role'] === 'student' ? Str::upper(Str::random(8)) : null,
            'first_name' => 'Pending',
            'last_name' => 'Setup',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $status = Password::sendResetLink(['email' => $invitedUser->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'The account was created, but the secure setup email could not be sent. Use the password-reset page to resend it.');
        }

        return back()->with('success', 'User invited. A secure account-setup link was emailed to them.');
    }

    // Admin: Reports
    public function reports(Request $request)
    {
        $user = Auth::user();
        $reports = [];

        // Build scope query based on role
        $userScope = fn ($query) => $query;
        $courseScope = fn ($query) => $query;

        if ($user->isDepartmentAdmin()) {
            $userScope = fn ($query) => $query->where('department_id', $user->department_id);
            $courseScope = fn ($query) => $query->where('department_id', $user->department_id);
        } elseif ($user->isUniversityAdmin()) {
            $userScope = fn ($query) => $query->where('university_id', $user->university_id);
            $departmentIds = Department::whereHas('faculty', fn ($q) => $q->where('university_id', $user->university_id))->pluck('id');
            $courseScope = fn ($query) => $query->whereIn('department_id', $departmentIds);
        }

        // Submission stats (scoped)
        $reports['submissions'] = [
            'total' => Submission::whereHas('course', $courseScope)->count(),
            'pending' => Submission::whereHas('course', $courseScope)->whereIn('status', ['submitted', 'under_review'])->count(),
            'graded' => Submission::whereHas('course', $courseScope)->where('status', 'graded')->count(),
        ];

        // Attendance stats (scoped)
        $reports['attendance'] = [
            'total_sessions' => AttendanceSession::whereHas('course', $courseScope)->count(),
            'present_rate' => $this->calculateAttendanceRate($courseScope),
        ];

        // Billing stats (scoped)
        $reports['billing'] = [
            'total_invoices' => Invoice::whereHas('user', $userScope)->count(),
            'paid' => Invoice::whereHas('user', $userScope)->where('status', 'paid')->count(),
            'pending' => Invoice::whereHas('user', $userScope)->where('status', 'pending')->count(),
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
                ->pluck('id');
            $courseScope = fn ($query) => $query->whereIn('department_id', $departmentIds);
        }

        [$headers, $rows] = match ($type) {
            'submissions' => [
                ['Submission ID', 'Student', 'Email', 'Course', 'Title', 'Status', 'Submitted At', 'Score'],
                Submission::query()
                    ->whereHas('course', $courseScope)
                    ->with(['user', 'course', 'grade'])
                    ->latest('id')
                    ->get()
                    ->map(fn (Submission $submission) => [
                        $submission->uuid,
                        $submission->user?->full_name,
                        $submission->user?->email,
                        $submission->course?->code,
                        $submission->title,
                        $submission->status,
                        $submission->submitted_at?->toDateTimeString(),
                        $submission->grade?->score,
                    ]),
            ],
            'attendance' => [
                ['Session ID', 'Course', 'Student', 'Email', 'Status', 'Check-in At', 'Verified', 'Notes'],
                AttendanceRecord::query()
                    ->whereHas('session', function ($session) use ($courseScope, $request): void {
                        $session->whereHas('course', $courseScope);
                        if ($request->filled('session_id')) {
                            $session->whereKey($request->integer('session_id'));
                        }
                    })
                    ->with(['user', 'session.course'])
                    ->latest('id')
                    ->get()
                    ->map(fn (AttendanceRecord $record) => [
                        $record->session?->uuid,
                        $record->session?->course?->code,
                        $record->user?->full_name,
                        $record->user?->email,
                        $record->status,
                        $record->check_in_at?->toDateTimeString(),
                        $record->is_verified ? 'Yes' : 'No',
                        $record->verification_notes,
                    ]),
            ],
            'billing' => [
                ['Invoice ID', 'Student', 'Email', 'Amount', 'Status', 'Due Date', 'Paid At', 'Transaction Reference'],
                Invoice::query()
                    ->whereHas('user', $userScope)
                    ->with('user')
                    ->latest('id')
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        $invoice->uuid,
                        $invoice->user?->full_name,
                        $invoice->user?->email,
                        $invoice->amount,
                        $invoice->status,
                        $invoice->due_date?->toDateString(),
                        $invoice->paid_at?->toDateTimeString(),
                        $invoice->transaction_ref,
                    ]),
            ],
        };

        $filename = sprintf('acadflow-%s-%s.csv', $type, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($headers, $rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function calculateAttendanceRate($courseScope)
    {
        $total = AttendanceRecord::whereHas('session', fn ($q) => $q->whereHas('course', $courseScope))->count();
        if ($total === 0) {
            return 0;
        }

        $present = AttendanceRecord::whereHas('session', fn ($q) => $q->whereHas('course', $courseScope))
            ->whereIn('status', ['present', 'late'])->count();

        return round(($present / $total) * 100, 1);
    }

    // University Admin: Manage faculties
    public function faculties()
    {
        $actor = Auth::user();
        $faculties = Faculty::query()
            ->with(['departments', 'university', 'dean'])
            ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('university_id', $actor->university_id))
            ->orderBy('name')
            ->get();
        $universities = $actor->isSuperAdmin() ? University::query()->where('is_active', true)->orderBy('name')->get() : collect([$actor->university]);
        $deans = User::query()
            ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('university_id', $actor->university_id))
            ->whereIn('role', ['lecturer', 'university_admin'])
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get();

        return view('admin.faculties', compact('faculties', 'universities', 'deans'));
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
        $deans = User::query()->where('university_id', $faculty->university_id)->whereIn('role', ['lecturer', 'university_admin'])->orderBy('first_name')->get();
        return view('admin.faculties-edit', compact('faculty', 'deans'));
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
    public function universities()
    {
        $universities = University::query()
            ->withCount(['faculties', 'users'])
            ->orderBy('institution_type')
            ->orderBy('name')
            ->paginate(20);

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

    private function departmentsForAdmin(User $actor)
    {
        return Department::query()
            ->with('faculty')
            ->when($actor->isDepartmentAdmin(), fn ($query) => $query->whereKey($actor->department_id))
            ->when($actor->isUniversityAdmin(), fn ($query) => $query->whereHas('faculty', fn ($faculty) => $faculty->where('university_id', $actor->university_id)))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    // Settings - redirect to SettingsController
    public function settings()
    {
        return redirect()->route('admin.settings');
    }
}
