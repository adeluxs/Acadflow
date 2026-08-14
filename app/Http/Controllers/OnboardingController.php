<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\KnowledgeCommunity;
use App\Models\KnowledgePublication;
use App\Models\AcademicEvent;
use App\Models\University;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\Media\MediaSecurityService;
use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public const STEPS = [
        1 => 'path',
        2 => 'profile',
        3 => 'affiliation',
        4 => 'interests',
        5 => 'preferences',
        6 => 'review',
    ];

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->onboarding_completed_at && ! $request->boolean('edit')) {
            return redirect()->route('dashboard');
        }

        $state = UserOnboardingState::firstOrCreate(
            ['user_id' => $user->id],
            ['current_step' => 1, 'started_at' => now(), 'data' => []]
        );

        $requestedStep = $request->integer('step', $state->current_step ?: 1);
        $stepNumber = max(1, min(count(self::STEPS), $requestedStep));
        $step = self::STEPS[$stepNumber];

        $universities = University::query()
            ->where('is_active', true)
            ->with(['faculties' => fn ($query) => $query->where('is_active', true)->with([
                'departments' => fn ($departmentQuery) => $departmentQuery->where('is_active', true),
            ])])
            ->orderBy('name')
            ->get();

        return view('onboarding.show', [
            'user' => $user,
            'state' => $state,
            'data' => array_merge($this->userDefaults($user), $state->data ?? []),
            'steps' => self::STEPS,
            'step' => $step,
            'stepNumber' => $stepNumber,
            'universities' => $universities,
            'accountPaths' => $this->accountPaths(),
        ]);
    }

    public function save(Request $request, string $step, MediaSecurityService $media): RedirectResponse
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false, 404);

        $user = $request->user();
        $state = UserOnboardingState::firstOrCreate(
            ['user_id' => $user->id],
            ['current_step' => 1, 'started_at' => now(), 'data' => []]
        );

        $validated = match ($step) {
            'path' => $request->validate([
                'path' => ['required', Rule::in(array_keys($this->accountPaths()))],
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
            'interests' => $request->validate([
                'programme' => ['nullable', 'string', 'max:255'],
                'academic_level' => ['nullable', 'string', 'max:80'],
                'research_interests' => ['nullable', 'array', 'max:20'],
                'research_interests.*' => ['string', 'max:100'],
                'skills' => ['nullable', 'array', 'max:30'],
                'skills.*' => ['string', 'max:100'],
                'topic_interests' => ['nullable', 'array', 'max:30'],
                'topic_interests.*' => ['string', 'max:100'],
                'community_interests' => ['nullable', 'array', 'max:20'],
                'community_interests.*' => ['string', 'max:100'],
                'event_interests' => ['nullable', 'array', 'max:20'],
                'event_interests.*' => ['string', 'max:100'],
            ]),
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

        if ($step === 'path') {
            $state->path = $validated['path'];
            if (! in_array($validated['path'], $this->institutionalPaths(), true)) {
                $current = $state->data ?? [];
                unset($current['university_id'], $current['faculty_id'], $current['department_id']);
                $state->data = $current;
            }
        }

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

        $state->data = array_merge($state->data ?? [], $this->normalizeStepData($step, $validated));
        $state->current_step = min(count(self::STEPS), $stepNumber + 1);
        $state->last_saved_at = now();
        $state->save();

        return redirect()->route('onboarding.show', ['step' => $state->current_step])
            ->with('success', 'Your onboarding progress was saved.');
    }

    public function back(Request $request): RedirectResponse
    {
        $state = UserOnboardingState::firstOrCreate(['user_id' => $request->user()->id]);
        $state->current_step = max(1, (int) $state->current_step - 1);
        $state->last_saved_at = now();
        $state->save();

        return redirect()->route('onboarding.show', ['step' => $state->current_step]);
    }

    public function skip(Request $request, string $step): RedirectResponse
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false || in_array($step, ['path', 'profile', 'review'], true), 422, 'This onboarding step is required.');

        $state = UserOnboardingState::firstOrCreate(['user_id' => $request->user()->id]);
        if ($step === 'affiliation' && in_array($state->path, $this->institutionalPaths(), true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'university_id' => ['Institutional account paths must confirm their affiliation.'],
            ]);
        }
        $skipped = $state->skipped_steps ?? [];
        $skipped[] = $step;
        $state->skipped_steps = array_values(array_unique($skipped));
        $state->current_step = min(count(self::STEPS), $stepNumber + 1);
        $state->last_saved_at = now();
        $state->save();

        return redirect()->route('onboarding.show', ['step' => $state->current_step]);
    }

    public function complete(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->complete($request->user());

        return redirect()->route('onboarding.recommendations')
            ->with('success', 'Your AcadFlow workspace is ready.');
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

        return view('admin.onboarding.index', ['users' => $users, 'accountPaths' => $this->accountPaths()]);
    }

    private function validateAffiliation(Request $request, UserOnboardingState $state): array
    {
        $path = $state->path ?: (string) Arr::get($state->data ?? [], 'path');
        $institutional = in_array($path, ['student', 'lecturer', 'researcher', 'university_representative', 'department_representative', 'academic_staff', 'non_academic_staff'], true);

        $validated = $request->validate([
            'university_id' => [
                Rule::requiredIf(in_array($path, $this->institutionalPaths(), true)),
                'nullable', 'integer', 'exists:universities,id',
            ],
            'faculty_id' => [
                Rule::requiredIf($path === 'department_representative'),
                'nullable', 'integer', 'exists:faculties,id',
            ],
            'department_id' => [
                Rule::requiredIf($path === 'department_representative'),
                'nullable', 'integer', 'exists:departments,id',
            ],
        ]);

        if (! empty($validated['faculty_id'])) {
            $faculty = Faculty::findOrFail($validated['faculty_id']);
            abort_if(! empty($validated['university_id']) && $faculty->university_id !== (int) $validated['university_id'], 422, 'The selected faculty does not belong to that university.');
        }

        if (! empty($validated['department_id'])) {
            $department = \App\Models\Department::with('faculty')->findOrFail($validated['department_id']);
            abort_if(! empty($validated['faculty_id']) && $department->faculty_id !== (int) $validated['faculty_id'], 422, 'The selected department does not belong to that faculty.');
            abort_if(! empty($validated['university_id']) && $department->faculty?->university_id !== (int) $validated['university_id'], 422, 'The selected department does not belong to that university.');
        }

        return $validated;
    }

    private function normalizeStepData(string $step, array $validated): array
    {
        if ($step === 'preferences') {
            return [
                'profile_visibility' => $validated['profile_visibility'],
                'notification_preferences' => [
                    'email' => (bool) ($validated['email_notifications'] ?? false),
                    'in_app' => (bool) ($validated['in_app_notifications'] ?? false),
                    'event_reminders' => (bool) ($validated['event_reminders'] ?? false),
                    'research_updates' => (bool) ($validated['research_updates'] ?? false),
                    'community_updates' => (bool) ($validated['community_updates'] ?? false),
                    'personalized_recommendations' => (bool) ($validated['personalized_recommendations'] ?? false),
                ],
            ];
        }

        if ($step === 'path') {
            return ['path' => $validated['path']];
        }

        return $validated;
    }

    private function userDefaults(User $user): array
    {
        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'location' => $user->location,
            'avatar_media_id' => $user->avatar_media_id,
            'university_id' => $user->university_id,
            'faculty_id' => $user->faculty_id,
            'department_id' => $user->department_id,
            'programme' => $user->programme,
            'academic_level' => $user->academic_level,
            'research_interests' => $user->research_interests ?? [],
            'skills' => $user->skills ?? [],
            'topic_interests' => $user->topic_interests ?? [],
            'community_interests' => $user->community_interests ?? [],
            'event_interests' => $user->event_interests ?? [],
            'profile_visibility' => $user->profile_visibility ?? 'public',
            'notification_preferences' => $user->notification_preferences ?? $this->defaultNotifications(),
            'headline' => $user->creatorProfile?->headline,
            'biography' => $user->creatorProfile?->biography,
        ];
    }

    private function defaultNotifications(): array
    {
        return [
            'email' => true,
            'in_app' => true,
            'event_reminders' => true,
            'research_updates' => true,
            'community_updates' => true,
            'personalized_recommendations' => true,
        ];
    }

    private function institutionalPaths(): array
    {
        return [
            'student', 'lecturer', 'researcher', 'university_representative',
            'department_representative', 'academic_staff', 'non_academic_staff',
        ];
    }

    private function accountPaths(): array
    {
        return [
            'student' => 'Student',
            'lecturer' => 'Lecturer or educator',
            'researcher' => 'Researcher',
            'university_representative' => 'University representative',
            'department_representative' => 'Faculty or department representative',
            'academic_staff' => 'Academic staff',
            'non_academic_staff' => 'Non-academic staff',
            'independent_professional' => 'Independent professional',
            'author_publisher' => 'Author or publisher',
            'research_discovery' => 'Research and publication reader',
            'community_events' => 'Community and event participant',
            'alumni' => 'Alumni',
            'organisation' => 'Organisation or research group',
            'other' => 'Other',
        ];
    }
}
