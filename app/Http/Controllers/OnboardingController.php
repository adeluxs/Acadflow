<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicEvent;
use App\Models\AcademicMembershipInvitation;
use App\Models\AcademicSession;
use App\Models\DepartmentAdoptionRequest;
use App\Models\DepartmentOnboardingDraft;
use App\Models\InstitutionOnboardingRequest;
use App\Models\KnowledgeCommunity;
use App\Models\KnowledgePublication;
use App\Models\ResearchProject;
use App\Models\University;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\FeatureAccessService;
use App\Services\Media\MediaSecurityService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public const STEPS = OnboardingService::STEPS;

    public function show(Request $request, OnboardingService $onboarding): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at && ! $request->boolean('edit')) {
            return redirect()->route('dashboard');
        }

        $state = $onboarding->state($user);
        $data = $onboarding->data($user, $state);
        if ($user->onboarding_completed_at) {
            $state->path = $user->account_type;
        }

        $requestedStep = $request->integer('step', $state->current_step ?: 1);
        $stepNumber = max(1, min(count(self::STEPS), $requestedStep));
        $step = self::STEPS[$stepNumber];
        if ($step === 'preferences') {
            $user->load('notificationSetting');
            $data = $onboarding->data($user, $state);
        }

        $universities = collect();
        $selectedUniversityId = (int) $request->old('university_id', $data['university_id'] ?? 0);
        if (in_array($step, ['affiliation', 'review'], true) && $selectedUniversityId) {
            $universities = University::query()->whereKey($selectedUniversityId)
                ->when($step === 'affiliation', fn ($q) => $q->with(['faculties:id,university_id,name', 'faculties.departments:id,faculty_id,name']))->get(['id', 'name', 'slug']);
        }

        return view('onboarding.show', [
            'user' => $user,
            'state' => $state,
            'data' => $data,
            'completion' => $onboarding->progress($user, $state),
            'editing' => (bool) $user->onboarding_completed_at,
            'steps' => self::STEPS,
            'step' => $step,
            'stepNumber' => $stepNumber,
            'universities' => $universities,
            'accountPaths' => $onboarding->accountPaths($state->path),
        ]);
    }

    public function save(Request $request, string $step, MediaSecurityService $media, OnboardingService $onboarding): RedirectResponse|JsonResponse
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false, 404);

        $user = $request->user();
        $state = $onboarding->state($user);

        if ($step === 'interests') {
            $fields = ['research_interests', 'skills', 'topic_interests', 'community_interests', 'event_interests', 'supervision_interests'];
            $text = $request->validate(collect($fields)->mapWithKeys(fn ($field) => [$field.'_text' => ['nullable', 'string', 'max:3000']])->all());
            foreach ($fields as $field) {
                if (array_key_exists($field.'_text', $text)) {
                    $request->merge([$field => array_values(array_filter(array_map('trim', explode(',', (string) $text[$field.'_text']))))]);
                }
            }
        }

        if ($step === 'profile' && $request->has('username')) {
            $request->merge(['username' => strtolower((string) $request->input('username'))]);
        }

        $validated = match ($step) {
            'path' => $request->validate([
                'path' => ['required', Rule::in(array_keys($onboarding->accountPaths($state->path)))],
            ]),
            'profile' => $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'username' => ['required', 'alpha_dash', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
                'headline' => ['nullable', 'string', 'max:255'],
                'biography' => ['nullable', 'string', 'max:3000'],
                'country_code' => ['nullable', 'string', 'size:2'],
                'location' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:40'],
                'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]),
            'affiliation' => $this->validateAffiliation($request, $state),
            'interests' => $request->validate($onboarding->interestRules($user->onboarding_completed_at ? $user->account_type : $state->path)),
            'preferences' => $request->validate([
                'profile_visibility' => ['required', Rule::in(['public', 'institution', 'private'])],
                'email_notifications' => ['nullable', 'boolean'],
                'in_app_notifications' => ['nullable', 'boolean'],
                'event_reminders' => ['nullable', 'boolean'],
                'research_updates' => ['nullable', 'boolean'],
                'community_updates' => ['nullable', 'boolean'],
                'personalized_recommendations' => ['nullable', 'boolean'],
            ]),
            'review' => [],
        };

        if ($step === 'profile' && $request->hasFile('avatar')) {
            $asset = $media->store(
                $request->file('avatar'),
                $user,
                $user,
                'public',
                ['purpose' => 'profile_avatar']
            );
            abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 422, 'The profile image did not pass security inspection.');
            $validated['avatar_media_id'] = $asset->id;
            unset($validated['avatar']);
        }

        $state = $onboarding->saveStep($user, $step, $validated);
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Profile changes saved.', 'state' => $state,
                'progress' => $onboarding->progress($user->fresh(), $state)]);
        }

        return $user->onboarding_completed_at
            ? redirect()->route('onboarding.manage')->with('success', 'Your profile changes were saved.')
            : redirect()->route('onboarding.show', ['step' => $state->current_step])->with('success', 'Your onboarding progress was saved.');
    }

    public function back(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        if ($request->user()->onboarding_completed_at) {
            return redirect()->route('onboarding.manage');
        }
        $state = $onboarding->state($request->user());
        $state->update(['current_step' => max(1, (int) $state->current_step - 1), 'last_saved_at' => now()]);

        return redirect()->route('onboarding.show', ['step' => $state->current_step]);
    }

    public function skip(Request $request, string $step, OnboardingService $onboarding): RedirectResponse
    {
        $state = $onboarding->skip($request->user(), $step);

        return $request->user()->onboarding_completed_at
            ? redirect()->route('onboarding.manage')
            : redirect()->route('onboarding.show', ['step' => $state->current_step]);
    }

    public function manage(Request $request, OnboardingService $onboarding): View
    {
        $user = $request->user();
        $state = $onboarding->state($user);

        $departmentApplications = ($user->isDepartmentAdmin() || $user->account_type === 'department_representative')
            ? DepartmentAdoptionRequest::where('requester_user_id', $user->id)->latest('id')->limit(5)->get() : collect();
        $institutionApplications = ($user->isUniversityAdmin() || $user->account_type === 'university_representative')
            ? InstitutionOnboardingRequest::where('requester_user_id', $user->id)->latest('id')->limit(5)->get() : collect();

        return view('onboarding.manage', ['user' => $user, 'completion' => $onboarding->progress($user, $state),
            'memberships' => $user->institutionAffiliations()->with(['university:id,name', 'department:id,name'])->latest('id')->limit(10)->get(),
            'departmentApplications' => $departmentApplications, 'institutionApplications' => $institutionApplications,
            'membershipRequestsAvailable' => FeatureAccessService::canAccessFeature('institution_self_service', $user),
            'organisationSteps' => $this->organisationSteps($user, $departmentApplications->first(), $institutionApplications->first()),
        ]);
    }

    private function organisationSteps(User $user, ?DepartmentAdoptionRequest $departmentApplication, ?InstitutionOnboardingRequest $institutionApplication): array
    {
        if ($user->isDepartmentAdmin() || $user->account_type === 'department_representative') {
            $department = $user->isDepartmentAdmin() ? $user->department : null;
            $draft = DepartmentOnboardingDraft::where('user_id', $user->id)->first();
            $data = array_replace($draft?->data ?? [], $departmentApplication?->toArray() ?? []);
            $infoFields = ['department_name', 'representative_title', 'official_email', 'official_phone', 'classification'];
            $infoComplete = $department || collect($infoFields)->every(fn ($field) => filled($data[$field] ?? null));
            $verification = ($department && in_array($department->adoption_status, ['independent_active', 'institution_managed', 'institution_affiliated'], true)) || $departmentApplication?->status === 'approved' ? 'Completed'
                : (in_array($departmentApplication?->status, ['pending', 'in_review'], true) ? 'Pending approval' : 'Incomplete');

            return [
                ['label' => 'Department information', 'status' => $infoComplete ? 'Completed' : 'Incomplete', 'url' => route('department.onboarding')],
                ['label' => 'Authority and verification', 'status' => $verification, 'url' => route('department.onboarding')],
                ['label' => 'Lecturer invitations (optional)', 'status' => $department && AcademicMembershipInvitation::where('department_id', $department->id)->where('invited_by', $user->id)->where('role', 'lecturer')->exists() ? 'Completed' : 'Incomplete', 'url' => route('academic.organisations').'#invite-members'],
                ['label' => 'Modules and academic calendar', 'status' => $department && filled($department->adoption_settings['modules'] ?? []) && AcademicSession::where('department_id', $department->id)->exists() ? 'Completed' : 'Incomplete', 'url' => route('academic.organisations').'#department-configuration'],
            ];
        }
        if ($user->isUniversityAdmin() || $user->account_type === 'university_representative') {
            $institution = $user->isUniversityAdmin() ? $user->university : null;
            $verification = ($institution && in_array($institution->adoption_status, ['claim_verified', 'setup_in_progress', 'active'], true)) || $institutionApplication?->status === 'approved' ? 'Completed'
                : (in_array($institutionApplication?->status, ['pending', 'in_review'], true) ? 'Pending approval' : 'Incomplete');

            return [
                ['label' => 'Institution profile', 'status' => $institution || $institutionApplication ? 'Completed' : 'Incomplete', 'url' => route('institution.onboarding.request')],
                ['label' => 'Institution verification', 'status' => $verification, 'url' => route('institution.onboarding.request')],
                ['label' => 'Faculties and departments', 'status' => $institution && $institution->faculties()->whereHas('departments')->exists() ? 'Completed' : 'Incomplete', 'url' => $institution ? route('admin.faculties') : route('institution.onboarding.request')],
                ['label' => 'Administration and configuration', 'status' => $institution?->adoption_status === 'active' ? 'Completed' : 'Incomplete', 'url' => $institution ? route('institution.setup') : route('institution.onboarding.request')],
            ];
        }

        return [];
    }

    public function dismiss(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->dismissBanner($request->user());

        return back()->with('success', 'Profile suggestion dismissed. You can manage your profile anytime.');
    }

    public function complete(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        if ($request->user()->onboarding_completed_at) {
            return redirect()->route('onboarding.manage');
        }
        $data = $request->validate(['next_action' => ['nullable', Rule::in(['dashboard', 'research', 'final_year'])]]);
        $completedUser = $onboarding->complete($request->user());
        if ($completedUser->account_type === 'department_representative') {
            return redirect()->route('department.onboarding');
        }
        if ($completedUser->account_type === 'university_representative') {
            return redirect()->route('institution.onboarding.request');
        }
        if (in_array($data['next_action'] ?? '', ['research', 'final_year'], true)
            && $request->user()->can('create', ResearchProject::class)
            && FeatureAccessService::effectiveStatus('research_studio', $request->user()->university_id) === FeatureAccessService::STATUS_ENABLED) {
            return redirect()->route('research.create', ['studio_mode' => $data['next_action'] === 'final_year' ? 'final_year' : 'research'])
                ->with('success', 'Your profile is ready. Create your first research workspace.');
        }

        return redirect()->route('onboarding.recommendations')
            ->with('success', 'Your AcadSyntra workspace is ready.');
    }

    public function recommendations(Request $request): View
    {
        $user = $request->user();
        $terms = array_values(array_unique(array_filter(array_merge(
            $user->research_interests ?? [],
            $user->topic_interests ?? [],
            $user->community_interests ?? [],
            $user->event_interests ?? []
        ))));

        $communities = KnowledgeCommunity::query()
            ->where('status', 'active')
            ->where(function ($query) use ($user): void {
                $query->where('visibility', 'public');
                if ($user->university_id) {
                    $query->orWhere(fn ($institution) => $institution
                        ->where('visibility', 'institution')
                        ->where('university_id', $user->university_id));
                }
            })
            ->when($terms !== [], function ($query) use ($terms): void {
                $query->where(function ($termQuery) use ($terms): void {
                    foreach (array_slice($terms, 0, 10) as $term) {
                        $termQuery->orWhere('name', 'like', "%{$term}%")
                            ->orWhere('description', 'like', "%{$term}%");
                    }
                });
            })
            ->withCount('members')
            ->limit(6)
            ->get();

        $events = AcademicEvent::query()
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($query) use ($user): void {
                $query->where('visibility', 'public');
                if ($user->university_id) {
                    $query->orWhere(fn ($institution) => $institution
                        ->where('visibility', 'institution')
                        ->where('university_id', $user->university_id));
                }
            })
            ->orderBy('starts_at')
            ->limit(6)
            ->get();

        $publications = KnowledgePublication::query()
            ->where('status', 'published')
            ->where(function ($query) use ($user): void {
                $query->where('visibility', 'public');
                if ($user->university_id) {
                    $query->orWhere(fn ($institution) => $institution
                        ->where('visibility', 'institution')
                        ->where('university_id', $user->university_id));
                }
            })
            ->latest('published_at')
            ->limit(6)
            ->get();

        return view('onboarding.recommendations', compact('communities', 'events', 'publications'));
    }

    public function adminIndex(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        $users = User::query()
            ->with(['university', 'department', 'onboardingState'])
            ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query->where('university_id', $request->user()->university_id))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $request->string('status')->toString() === 'complete'
                    ? $query->whereNotNull('onboarding_completed_at')
                    : $query->whereNull('onboarding_completed_at');
            })
            ->when($request->filled('account_type'), fn ($query) => $query->where('account_type', $request->string('account_type')->toString()))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.onboarding.index', ['users' => $users, 'accountPaths' => OnboardingService::ACCOUNT_PATHS]);
    }

    private function validateAffiliation(Request $request, UserOnboardingState $state): array
    {
        return app(OnboardingService::class)->assertAffiliation($request->all(), $state->path);
    }
}
