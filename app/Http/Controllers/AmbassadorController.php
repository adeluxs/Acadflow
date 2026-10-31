<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReport;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AmbassadorQualificationService;
use App\Services\AmbassadorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AmbassadorController extends Controller
{
    public function referral(Request $request, string $code, AmbassadorService $service)
    {
        if ($request->user()) return redirect()->route('ambassadors.index');
        // Unknown, suspended and valid codes all lead to registration, so the
        // response never reveals whether a code belongs to an active ambassador.
        $service->captureReferral($request, $code);
        return redirect()->route('register')->header('Cache-Control', 'no-store');
    }

    /** Public "Become a Campus Ambassador" entry point that survives sign-in or signup. */
    public function join(Request $request)
    {
        if ($request->user()) return redirect()->to(route('ambassadors.index').'#apply');
        $request->session()->put('url.intended', route('ambassadors.index'));
        // Registration continues through onboarding; this marker lets the
        // dashboard offer the way back to the application afterwards.
        $request->session()->put('ambassador_join_intent', true);
        return redirect()->route($request->query('account') === 'existing' ? 'login' : 'register');
    }

    public function index(Request $request, AmbassadorService $service)
    {
        $user = $request->user();
        $request->session()->forget('ambassador_join_intent');
        $application = AmbassadorApplication::where('user_id', $user->id)->first();
        $ownReferral = $user->has_ambassador_referral
            ? app(AmbassadorQualificationService::class)->evidence(AmbassadorReferral::where('user_id', $user->id))->first() : null;
        $metrics = $application && in_array($application->status, ['approved', 'suspended'], true) ? $service->metrics($application->id) : null;
        $reports = $application?->reports()->with('task:id,title')->latest('id')->paginate(10, ['*'], 'reports_page')->withQueryString();
        $rewards = $application?->rewards()->latest('id')->paginate(10, ['*'], 'rewards_page')->withQueryString();
        $tasks = $application?->status === 'approved'
            ? AmbassadorTask::available()->orderByRaw('ends_on IS NULL')->orderBy('ends_on')->latest('id')->limit(12)->get()
            : collect();
        $profile = $application ? [] : $this->profileDefaults($user);
        return response()->view('ambassadors.index', compact('application', 'metrics', 'reports', 'rewards', 'ownReferral', 'tasks', 'profile'))
            ->header('Cache-Control', 'no-store');
    }

    public function verifyReferralEmail(Request $request)
    {
        abort_unless($request->user()->has_ambassador_referral, 403);
        if (! $request->user()->hasVerifiedEmail()) {
            // Referral qualification always requires ownership, even if global verification is optional.
            $request->user()->notify(new \App\Notifications\QueuedVerifyEmail());
        }
        return back()->with('success', 'A verification link has been queued for your email.');
    }

    public function apply(Request $request)
    {
        $user = $request->user();
        abort_unless($user->is_active && ! $user->isAdmin(), 403);
        if (! $user->isStudent()) {
            throw ValidationException::withMessages(['application' => 'The campus ambassador programme is for student accounts.']);
        }
        $data = $request->validate([
            'institution' => ['required', 'string', 'max:200'],
            'faculty' => ['nullable', 'string', 'max:200'],
            'department' => ['required', 'string', 'max:200'],
            'study_level' => ['required', 'string', 'max:80'],
            'leadership_role' => ['required', Rule::in(['course_rep', 'student_leader', 'student_volunteer'])],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,40}$/'],
            'community_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'statement' => ['required', 'string', 'min:30', 'max:3000'],
            'social_profile_url' => ['nullable', 'url:https', 'max:500'],
            'terms' => ['accepted'],
        ]);
        unset($data['terms']);
        DB::transaction(function () use ($request, $user, $data): void {
            // Serialize concurrent submissions from the same account.
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $application = AmbassadorApplication::where('user_id', $user->id)->lockForUpdate()->first();
            if ($application && ! in_array($application->status, AmbassadorApplication::RESUBMITTABLE, true)) {
                throw ValidationException::withMessages(['application' => 'You already have an application. Check its status below.']);
            }
            $values = $data + [
                'university_id' => $user->university_id,
                'browser_hash' => app(AmbassadorQualificationService::class)->browserHash($request),
                'status' => 'pending', 'terms_accepted_at' => now(), 'submitted_at' => now(), 'withdrawn_at' => null,
                'reviewed_at' => null, 'reviewed_by' => null, 'review_note' => null,
            ];
            if ($application) {
                $previous = $application->status;
                $application->forceFill($values)->save();
            } else {
                $previous = null;
                $application = AmbassadorApplication::forceCreate($values + ['user_id' => $user->id]);
            }
            AuditLog::log('ambassador.application.submitted', $user->id, AmbassadorApplication::class, $application->id, $previous, 'pending');
        });
        return redirect()->route('ambassadors.index')->with('success', 'Application submitted. The platform team will review it.');
    }

    public function withdraw(Request $request)
    {
        DB::transaction(function () use ($request): void {
            $application = AmbassadorApplication::where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($application->status !== 'pending') {
                throw ValidationException::withMessages(['application' => 'Only an application awaiting review can be withdrawn.']);
            }
            $application->forceFill(['status' => 'withdrawn', 'withdrawn_at' => now()])->save();
            AuditLog::log('ambassador.application.withdrawn', $request->user()->id, AmbassadorApplication::class, $application->id, 'pending', 'withdrawn');
        });
        return redirect()->route('ambassadors.index')->with('success', 'Application withdrawn. You can update and resubmit it at any time.');
    }

    public function report(Request $request, AmbassadorService $service)
    {
        $data = $request->validate([
            'submission_key' => ['required', 'uuid'],
            'task' => ['nullable', 'uuid'],
            'kind' => ['required', Rule::in(array_keys(AmbassadorReport::KINDS))],
            'title' => ['required', 'string', 'max:160'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:2026-01-01'],
            'participants' => ['required', 'integer', 'min:0', 'max:10000'],
            'summary' => ['required', 'string', 'min:20', 'max:5000'],
            'evidence_url' => ['nullable', 'url:https', 'max:1000'],
            // Content sniffing (mimetypes), not the client extension, decides the type.
            'evidence_file' => ['nullable', 'file', 'max:'.AmbassadorReport::EVIDENCE_MAX_KB, 'mimetypes:'.implode(',', array_keys(AmbassadorReport::EVIDENCE_MIMES))],
        ]);
        $task = null;
        if (! empty($data['task'])) {
            $task = AmbassadorTask::available()->where('uuid', $data['task'])->first();
            if (! $task) throw ValidationException::withMessages(['task' => 'That activity is no longer open. Choose another activity or leave it blank.']);
            if ($task->requires_evidence && empty($data['evidence_url']) && ! $request->hasFile('evidence_file')) {
                throw ValidationException::withMessages(['evidence_file' => 'This activity needs a supporting link or file.']);
            }
        }
        unset($data['task'], $data['evidence_file']);
        $data['ambassador_task_id'] = $task?->id;
        $service->submitReport($request->user(), $data, $request->file('evidence_file'));
        return redirect()->route('ambassadors.index')->with('success', 'Activity report submitted for review.');
    }

    /** Evidence is private: only the reporting ambassador and Super Admin may open it. */
    public function evidence(Request $request, AmbassadorReport $report)
    {
        $report->loadMissing('application:id,user_id');
        abort_unless($request->user()->isSuperAdmin() || $report->application?->user_id === $request->user()->id, 404);
        abort_unless($report->evidence_path && Storage::disk('local')->exists($report->evidence_path), 404);
        $extension = AmbassadorReport::EVIDENCE_MIMES[$report->evidence_mime] ?? 'bin';
        return Storage::disk('local')->response($report->evidence_path, 'activity-evidence-'.$report->id.'.'.$extension, [
            'Content-Type' => $report->evidence_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            // Images and PDFs only; scripts cannot run even if a file were crafted.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $report->evidence_mime === 'application/pdf' ? 'attachment' : 'inline');
    }

    /** Reuse what the student already told us so they do not retype it. */
    private function profileDefaults(User $user): array
    {
        // Prefill from the student's own profile fields (raw values, not the
        // request's resolved academic context). The application's university_id
        // link is set separately, only from an approved membership.
        $name = fn (string $model, $id) => $id ? $model::query()->whereKey($id)->value('name') : null;
        return array_filter([
            'institution' => $name(\App\Models\University::class, $user->getRawOriginal('university_id')),
            'faculty' => $name(\App\Models\Faculty::class, $user->getRawOriginal('faculty_id')),
            'department' => $name(\App\Models\Department::class, $user->getRawOriginal('department_id')) ?? $user->programme,
            'study_level' => $user->academic_level,
            'phone' => $user->phone,
        ], fn ($value) => is_string($value) && trim($value) !== '');
    }
}
