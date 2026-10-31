<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReport;
use App\Models\AmbassadorReferral;
use App\Services\AmbassadorQualificationService;
use App\Models\AmbassadorReward;
use App\Models\AmbassadorTask;
use App\Models\AuditLog;
use App\Services\AmbassadorService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AmbassadorManagementController extends Controller
{
    public function index(Request $request, AmbassadorService $service)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'suspended', 'withdrawn', 'all'])],
            'work' => ['nullable', Rule::in(['reports', 'rewards', 'referrals'])],
            'q' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $status = $data['status'] ?? 'pending';
        $work = $data['work'] ?? null;
        $search = trim((string) ($data['q'] ?? ''));
        $applications = AmbassadorApplication::query()->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($work, fn ($q) => $q->whereHas($work, fn ($pending) => $pending->where($work === 'referrals' ? 'qualification_status' : 'status', 'pending')))
            // Institution or department text; bound parameters, wildcards in the input are literal.
            ->when($search !== '', function ($q) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->where(fn ($inner) => $inner->where('institution', 'like', $like)->orWhere('department', 'like', $like));
            })
            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($data['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->with('user:id,first_name,last_name,email')
            ->withCount(['referrals', 'referrals as pending_referrals_count' => fn ($q) => $q->where('qualification_status', 'pending'), 'reports as pending_reports_count' => fn ($q) => $q->where('status', 'pending'), 'rewards as pending_rewards_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest('id')->paginate(20)->withQueryString();
        $metrics = $service->metrics();
        $pendingReports = AmbassadorReport::where('status', 'pending')->count();
        $pendingRewards = AmbassadorReward::where('status', 'pending')->count();
        $pendingReferrals = AmbassadorReferral::where('qualification_status', 'pending')->count();
        $activeTasks = AmbassadorTask::available()->count();
        $auditTrail = AuditLog::query()->select(['id', 'user_id', 'action', 'model_type', 'model_id', 'created_at'])
            ->where('action', 'like', 'ambassador.%')->with('user:id,first_name,last_name')->latest('id')->limit(15)->get();
        $filters = ['q' => $search, 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? ''];
        return response()->view('admin.ambassadors.index', compact('applications', 'status', 'work', 'metrics', 'pendingReports', 'pendingRewards', 'pendingReferrals', 'activeTasks', 'auditTrail', 'filters'))->header('Cache-Control', 'no-store');
    }

    public function tasks(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $tasks = AmbassadorTask::query()->withCount(['reports', 'reports as pending_reports_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest('id')->paginate(20)->withQueryString();
        $editing = $request->query('edit') ? AmbassadorTask::where('uuid', (string) $request->query('edit'))->first() : null;
        return response()->view('admin.ambassadors.tasks', compact('tasks', 'editing'))->header('Cache-Control', 'no-store');
    }

    public function storeTask(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $task = AmbassadorTask::create($this->taskData($request));
        $task->forceFill(['created_by' => $request->user()->id, 'updated_by' => $request->user()->id])->save();
        AuditLog::log('ambassador.task.created', $request->user()->id, AmbassadorTask::class, $task->id, null, $task->only(['title', 'kind', 'is_active', 'starts_on', 'ends_on']));
        return redirect()->route('admin.ambassadors.tasks.index')->with('success', 'Programme activity published.');
    }

    public function updateTask(Request $request, AmbassadorTask $activity)
    {
        $task = $activity;
        abort_unless($request->user()->isSuperAdmin(), 403);
        $before = $task->only(['title', 'kind', 'is_active', 'starts_on', 'ends_on', 'requires_evidence']);
        $task->fill($request->has('toggle') ? ['is_active' => ! $task->is_active] : $this->taskData($request));
        $task->forceFill(['updated_by' => $request->user()->id])->save();
        AuditLog::log('ambassador.task.updated', $request->user()->id, AmbassadorTask::class, $task->id, $before, $task->only(array_keys($before)));
        return redirect()->route('admin.ambassadors.tasks.index')->with('success', $request->has('toggle') ? ($task->is_active ? 'Activity reopened.' : 'Activity closed. Existing reports are kept.') : 'Activity updated.');
    }

    private function taskData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(AmbassadorReport::KINDS))],
            'summary' => ['required', 'string', 'min:10', 'max:1000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'resource_url' => ['nullable', 'url:https', 'max:1000'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'requires_evidence' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        return $data + ['requires_evidence' => $request->boolean('requires_evidence'), 'is_active' => $request->boolean('is_active')];
    }

    public function show(Request $request, AmbassadorApplication $application, AmbassadorService $service)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $application->load('user:id,first_name,last_name,email,is_active');
        $metrics = $service->metrics($application->id);
        $reports = $application->reports()->with('task:id,title')->latest('id')->paginate(10, ['*'], 'reports_page')->withQueryString();
        $rewards = $application->rewards()->latest('id')->paginate(10, ['*'], 'rewards_page')->withQueryString();
        $application->loadMissing('university:id,name');
        $qualification = app(AmbassadorQualificationService::class);
        $rewardReferrals = $rewards->pluck('referral_id')->filter()->unique();
        $rewardEvidence = $rewardReferrals->isEmpty() ? collect() : $qualification->evidence(
            AmbassadorReferral::whereIn('id', $rewardReferrals)->where('ambassador_application_id', $application->id)
        )->get()->keyBy('id');
        $blockedRewards = [];
        foreach ($rewards as $reward) {
            if ($reward->status !== 'pending' || ($reward->kind !== 'performance_bonus' && ! $reward->referral_id)) continue;
            $evidence = $rewardEvidence->get($reward->referral_id);
            $check = $evidence ? $qualification->assessment($evidence) : null;
            if (! $evidence || $evidence->qualification_status !== 'qualified' || ! $check['eligible'] || ! $check['review_current']) {
                $blockedRewards[$reward->id] = true;
            }
        }
        $reviewStatus = $request->validate(['referral_status' => ['nullable', Rule::in(['pending', 'qualified', 'disqualified', 'all'])]])['referral_status'] ?? 'pending';
        $referrals = app(AmbassadorQualificationService::class)->evidence($application->referrals()->getQuery()
            ->when($reviewStatus !== 'all', fn ($q) => $q->where('qualification_status', $reviewStatus)))
            ->orderBy('id')->paginate(10, ['*'], 'referrals_page')->withQueryString();
        return response()->view('admin.ambassadors.show', compact('application', 'metrics', 'reports', 'rewards', 'referrals', 'reviewStatus', 'blockedRewards'))->header('Cache-Control', 'no-store');
    }

    public function review(Request $request, AmbassadorApplication $application, AmbassadorService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'suspended'])], 'review_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->reviewApplication($application, $request->user(), $data['status'], $data['review_note']);
        return back()->with('success', 'Application status updated.');
    }

    public function reviewReport(Request $request, AmbassadorReport $report, AmbassadorService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'review_note' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->reviewReport($report, $request->user(), $data['status'], $data['review_note']);
        return back()->with('success', 'Activity report reviewed.');
    }

    public function reviewReferral(Request $request, AmbassadorReferral $referral, AmbassadorQualificationService $qualification)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['qualified', 'disqualified', 'pending'])],
            'review_note' => ['required', 'string', 'min:20', 'max:2000'],
            'acknowledge_risks' => ['sometimes', 'boolean'],
            'evidence_signature' => ['required', 'string', 'size:64'],
        ]);
        $qualification->review($referral, $request->user(), $data['status'], $data['review_note'], $request->boolean('acknowledge_risks'), $data['evidence_signature']);
        return back()->with('success', 'Referral review recorded.');
    }

    public function award(Request $request, AmbassadorApplication $application, AmbassadorService $service)
    {
        $data = $request->validate([
            'referral_id' => ['nullable', 'integer', 'min:1'],
            'award_key' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{3,80}$/'],
            'kind' => ['required', Rule::in(array_keys(AmbassadorReward::KINDS))],
            'title' => ['required', 'string', 'max:160'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'amount' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'currency' => ['required', Rule::in(['NGN', 'USD', 'GBP', 'EUR'])],
        ]);
        $data['amount_minor'] = Money::toMinor((string) $data['amount']);
        unset($data['amount']);
        if (($data['kind'] === 'recognition' && $data['amount_minor'] !== 0) || ($data['kind'] !== 'recognition' && $data['amount_minor'] <= 0)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount for financial rewards, or 0 for recognition.']);
        }
        $service->award($application, $request->user(), $data);
        return back()->with('success', 'Award recorded. Fulfil it below when ready.');
    }

    public function fulfil(Request $request, AmbassadorReward $reward, AmbassadorService $service)
    {
        $data = $request->validate(['fulfilment_reference' => ['nullable', 'string', 'max:160']]);
        $service->fulfil($reward, $request->user(), (string) ($data['fulfilment_reference'] ?? ''));
        return back()->with('success', 'Reward fulfilled.');
    }

    public function cancel(Request $request, AmbassadorReward $reward, AmbassadorService $service)
    {
        $service->cancelReward($reward, $request->user());
        return back()->with('success', 'Pending award cancelled.');
    }
}
