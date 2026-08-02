<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Invoice;
use App\Models\Submission;
use App\Models\University;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
            $departments = Department::whereIn('id',
                Faculty::where('university_id', $user->university_id)->pluck('id')
            )->where('is_active', true)->get();
        } else {
            $departments = Department::where('is_active', true)->get();
        }

        return view('admin.users', compact('users', 'departments'));
    }

    // Admin: Invite user
    public function inviteUser(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'email' => 'required|email|unique:users',
            'role' => 'required|in:lecturer,student,department_admin',
            'department_id' => 'required_if:role,lecturer,department_admin',
        ]);

        if (! Gate::allows('canInviteRole', [User::class, $validated['role']])) {
            abort(403, 'You cannot invite users with this role.');
        }

        // Check max_administrators limit for admin roles
        if (in_array($validated['role'], ['department_admin', 'university_admin'])) {
            $user = Auth::user();
            $subscription = null;

            if ($validated['role'] === 'university_admin') {
                $subscription = UserSubscription::where('university_id', $user->university_id)
                    ->where('status', 'active')
                    ->whereHas('plan', fn ($q) => $q->where('plan_type', '!=', 'b2c'))
                    ->first();
            } elseif ($validated['role'] === 'department_admin') {
                $subscription = UserSubscription::where('department_id', $validated['department_id'])
                    ->where('status', 'active')
                    ->whereHas('plan', fn ($q) => $q->where('plan_type', '!=', 'b2c'))
                    ->first();
            }

            if ($subscription && $subscription->plan) {
                $maxAdmins = $subscription->plan->max_administrators;
                if ($maxAdmins !== null) {
                    $query = User::where('role', $validated['role']);
                    if ($validated['role'] === 'university_admin') {
                        $query->where('university_id', $user->university_id);
                    } else {
                        $query->where('department_id', $validated['department_id']);
                    }
                    $currentCount = $query->count();

                    if ($currentCount >= $maxAdmins) {
                        return back()->withErrors(['role' => 'Maximum number of administrators ('.$maxAdmins.') reached for your subscription plan.']);
                    }
                }
            }
        }

        $password = Str::random(8);
        $studentId = $request->role === 'student' ? Str::upper(Str::random(8)) : null;

        $user = User::create([
            'uuid' => Str::uuid(),
            'email' => $validated['email'],
            'password' => $password,
            'role' => $validated['role'],
            'department_id' => $validated['department_id'] ?? null,
            'student_id' => $studentId,
            'first_name' => 'Pending',
            'last_name' => 'Setup',
            'email_verified_at' => now(), // Auto-verify for invited users
        ]);

        // Send invitation email with credentials
        try {
            $this->sendInvitationEmail($user, $password);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Illuminate\Support\Facades\Log::error('Failed to send invitation email: '.$e->getMessage());
        }

        return back()->with('success', 'User invited. Password: '.$password);
    }

    /**
     * Send invitation email to newly invited user
     */
    protected function sendInvitationEmail(User $user, string $password): void
    {
        $roleName = ucfirst(str_replace('_', ' ', $user->role));
        $loginUrl = route('login');
        $siteName = \App\Services\SettingService::get('site_name', 'UniAcademic');
        
        $subject = 'Invitation to '.$siteName.' - '.$roleName.' Account';
        $body = "
            Hello,
            
            You have been invited to join {$siteName} as a {$roleName}.
            
            Your login credentials are:
            Email: {$user->email}
            Password: {$password}
            
            Please login at: {$loginUrl}
            
            After logging in, please update your profile and change your password.
            
            Thanks,
            {$siteName} Team
        ";

        \Illuminate\Support\Facades\Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email)
                    ->subject($subject);
        });
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
    public function exportReports(string $type)
    {
        $user = Auth::user();

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

        $data = match ($type) {
            'submissions' => Submission::whereHas('course', $courseScope)
                ->with('user', 'course', 'grade')
                ->get()
                ->toArray(),
            'attendance' => AttendanceRecord::whereHas('session', fn ($q) => $q->whereHas('course', $courseScope))
                ->with('user', 'session.course')
                ->get()
                ->toArray(),
            'billing' => Invoice::whereHas('user', $userScope)
                ->with('user', 'payments')
                ->get()
                ->toArray(),
            default => [],
        };

        return response()->json($data);
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
        $faculties = Faculty::where('university_id', Auth::user()->university_id)
            ->with('departments')
            ->get();

        return view('admin.faculties', compact('faculties'));
    }

    // Super Admin: Manage universities
    public function universities()
    {
        $universities = University::with('faculties')->paginate(10);

        return view('admin.universities', compact('universities'));
    }

    public function createUniversity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'short_name' => 'required|string|max:50',
            'code' => 'required|string|max:10|unique:universities',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'timezone' => 'string',
        ]);

        University::create($validated);

        return back()->with('success', 'University created.');
    }

    // Settings - redirect to SettingsController
    public function settings()
    {
        return redirect()->route('admin.settings');
    }
}
