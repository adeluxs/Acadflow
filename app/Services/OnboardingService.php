<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Models\UserOnboardingState;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public const STEPS = [
        1 => 'path',
        2 => 'profile',
        3 => 'affiliation',
        4 => 'interests',
        5 => 'preferences',
        6 => 'review',
    ];

    public const ACCOUNT_PATHS = [
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

    public const PUBLIC_ACCOUNT_PATHS = [
        'student' => 'Student',
        'lecturer' => 'Lecturer or supervisor',
        'researcher' => 'Researcher',
        'independent_professional' => 'Independent professional',
        'author_publisher' => 'Author or publisher',
        'other' => 'Other academic participant',
    ];

    public const ADOPTION_ACCOUNT_PATHS = [
        'department_representative' => 'Register My Department',
        'university_representative' => 'Register My Institution',
    ];

    // Representatives may request missing directory records after account setup.
    public const INSTITUTIONAL_PATHS = [
        'academic_staff', 'non_academic_staff',
    ];

    public function __construct(
        private readonly SocialNotificationService $notifications,
        private readonly InstitutionAdoptionService $institutionAdoption,
    ) {}

    /** Personal values must never come from the currently selected organisation context. */
    public function defaults(User $user): array
    {
        $fields = ['first_name', 'last_name', 'username', 'phone', 'country_code', 'location', 'avatar_media_id',
            'university_id', 'faculty_id', 'department_id', 'programme', 'academic_level', 'research_interests',
            'skills', 'topic_interests', 'community_interests', 'event_interests', 'profile_visibility', 'notification_preferences'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $user->getAttributeValue($field);
        }
        if ($user->relationLoaded('notificationSetting') && $user->notificationSetting) {
            $settings = $user->notificationSetting;
            $data['notification_preferences'] = array_replace($data['notification_preferences'] ?? $this->defaultNotifications(), [
                'email' => $settings->email_enabled, 'in_app' => $settings->in_app_enabled,
                'research_updates' => $settings->research_updates, 'event_reminders' => $settings->academic_alerts,
            ]);
        }
        $data['headline'] = $user->creatorProfile?->headline;
        $data['biography'] = $user->creatorProfile?->biography;
        $data['academic_position'] = $user->creatorProfile?->position;

        return $data;
    }

    public function state(User $user): UserOnboardingState
    {
        return UserOnboardingState::firstOrCreate(
            ['user_id' => $user->id],
            ['path' => $user->account_type, 'current_step' => 1, 'started_at' => now(),
                'completed_at' => $user->onboarding_completed_at, 'data' => []]
        );
    }

    public function data(User $user, ?UserOnboardingState $state): array
    {
        // Completed profiles are authoritative: edits through profile settings or invitations
        // must not be replaced by the historical onboarding snapshot.
        return $user->onboarding_completed_at
            ? array_merge($state?->data ?? [], $this->defaults($user))
            : array_merge($this->defaults($user), $state?->data ?? []);
    }

    public function saveStep(User $user, string $step, array $validated): UserOnboardingState
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false, 404);

        return DB::transaction(function () use ($user, $step, $stepNumber, $validated) {
            $personal = User::withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);
            $this->state($personal);
            $state = UserOnboardingState::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($step === 'path') {
                $state->path = $validated['path'];
            }
            $changes = $this->normalizeStepData($step, $validated);
            if ($step === 'preferences') {
                $changes['notification_preferences'] = array_replace($this->data($personal, $state)['notification_preferences'] ?? [], $changes['notification_preferences']);
            }
            $data = array_merge($state->data ?? [], $changes);
            $state->forceFill([
                'data' => $data,
                'skipped_steps' => array_values(array_diff($state->skipped_steps ?? [], [$step])),
                'current_step' => min(count(self::STEPS), $stepNumber + 1),
                'last_saved_at' => now(),
            ])->save();
            if ($personal->onboarding_completed_at) {
                $this->applyChanges($personal, $step, $changes);
                $user->setRawAttributes($personal->getAttributes(), true);
                $user->unsetRelation('creatorProfile');
                $user->unsetRelation('onboardingState');
            }

            $user->unsetRelation('onboardingState');

            return $state;
        }, 3);
    }

    public function skip(User $user, string $step): UserOnboardingState
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false || in_array($step, ['path', 'profile', 'review'], true), 422, 'This onboarding step is required.');

        return DB::transaction(function () use ($user, $step, $stepNumber) {
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->state($user);
            $state = UserOnboardingState::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($step === 'affiliation' && in_array($state->path, self::INSTITUTIONAL_PATHS, true)) {
                throw ValidationException::withMessages(['university_id' => ['Institutional account paths must confirm their affiliation.']]);
            }
            $state->forceFill([
                'skipped_steps' => array_values(array_unique(array_merge($state->skipped_steps ?? [], [$step]))),
                'current_step' => min(count(self::STEPS), $stepNumber + 1), 'last_saved_at' => now(),
            ])->save();

            $user->unsetRelation('onboardingState');

            return $state;
        }, 3);
    }

    public function dismissBanner(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::withoutGlobalScopes()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $state = $this->state($user);
            $state->update(['data' => array_merge($state->data ?? [], ['_banner_dismissed_at' => now()->toIso8601String()])]);
            $user->unsetRelation('onboardingState');
        });
    }

    /** Actual field completion, independent of the wizard cursor and legacy access flag. */
    public function progress(User $user, ?UserOnboardingState $state = null): array
    {
        $state ??= $user->relationLoaded('onboardingState') ? $user->onboardingState : $user->onboardingState()->first();
        $data = $this->data($user, $state);
        $path = $user->onboarding_completed_at ? $user->account_type : ($state?->path ?: $user->account_type);
        $data['path'] = $path;
        $required = ['path', 'first_name', 'last_name', 'username'];
        if (in_array($path, self::INSTITUTIONAL_PATHS, true)) {
            $required[] = 'university_id';
        }
        $interestFields = ['research_interests', 'skills', 'topic_interests', 'community_interests', 'event_interests'];
        if ($path === 'student') {
            $interestFields = array_merge(['programme', 'academic_level'], $interestFields);
        }
        if ($path === 'lecturer') {
            $interestFields = array_merge(['academic_position', 'specialisation', 'supervision_interests'], $interestFields);
        }
        $sections = [
            'path' => ['path'],
            'profile' => ['first_name', 'last_name', 'username', 'headline', 'biography', 'country_code', 'location', 'avatar_media_id'],
            'affiliation' => ['university_id', 'faculty_id', 'department_id'],
            'interests' => $interestFields,
            'preferences' => ['profile_visibility', 'notification_preferences'],
        ];
        $missingRequired = [];
        $steps = [];
        $filled = $total = 0;
        foreach ($sections as $key => $fields) {
            $missing = [];
            foreach ($fields as $field) {
                $value = $data[$field] ?? null;
                $provided = filled($value);
                if ($field === 'path') {
                    $provided = isset(self::ACCOUNT_PATHS[$value ?? '']);
                }
                if ($field === 'avatar_media_id') {
                    $provided = $provided || filled($user->avatar);
                }
                if (! $provided) {
                    $missing[] = $field;
                    if (in_array($field, $required, true)) {
                        $missingRequired[] = $key;
                    }
                }
                $total++;
                $filled += (int) $provided;
            }
            $isRequired = array_intersect($fields, $required) !== [];
            $status = $missing === [] ? 'completed' : (in_array($key, $missingRequired, true) ? 'incomplete'
                : (in_array($key, $state?->skipped_steps ?? [], true) ? 'skipped' : ($isRequired ? 'completed' : 'incomplete')));
            $steps[$key] = ['status' => $status, 'required' => $isRequired, 'missing_fields' => $missing, 'missing_labels' => array_map(fn ($field) => $this->fieldLabel($field), $missing),
                'url' => route('onboarding.show', ['step' => array_search($key, self::STEPS, true), 'edit' => 1])];
        }
        $steps['review'] = ['status' => ($user->onboarding_completed_at || $state?->completed_at) ? 'completed' : 'incomplete',
            'required' => true, 'missing_fields' => [], 'missing_labels' => [], 'url' => route('onboarding.show', ['step' => 6, 'edit' => 1])];
        $missingRequired = array_values(array_unique($missingRequired));
        $suggestions = [];
        foreach ($steps as $key => $details) {
            if ($details['missing_fields']) {
                $suggestions[$key] = $details;
            }
        }

        return ['percentage' => (int) round($filled / max(1, $total) * 100), 'filled_fields' => $filled, 'total_fields' => $total,
            'mandatory_complete' => $missingRequired === [], 'fully_completed' => $filled === $total,
            'missing_required_steps' => $missingRequired, 'steps' => $steps, 'suggestions' => $suggestions,
            'banner_dismissed' => filled($state?->data['_banner_dismissed_at'] ?? null)];
    }

    private function applyChanges(User $user, string $step, array $data): void
    {
        $fields = match ($step) {
            'path' => ['account_type' => $data['path']],
            'profile' => Arr::only($data, ['first_name', 'last_name', 'username', 'phone', 'country_code', 'location', 'avatar_media_id']),
            'affiliation' => Arr::only($data, ['university_id', 'faculty_id', 'department_id']),
            'interests' => Arr::only($data, ['programme', 'academic_level', 'research_interests', 'skills', 'topic_interests', 'community_interests', 'event_interests']),
            'preferences' => Arr::only($data, ['profile_visibility', 'notification_preferences']),
            default => [],
        };
        if ($step === 'path' && in_array($user->role, ['member', 'student', 'lecturer'], true)) {
            $fields['role'] = in_array($data['path'], ['student', 'lecturer'], true) ? $data['path'] : 'member';
        }
        if ($step === 'affiliation') {
            $changed = collect($fields)->contains(fn ($value, $key) => filled($user->$key) && (int) $user->$key !== (int) $value);
            // Existing institutional identity is changed through verified membership management.
            if ($changed && $user->university_id) {
                throw ValidationException::withMessages(['university_id' => 'Use My Academic Affiliations to change your existing institution or department.']);
            }
        }
        if (isset($fields['country_code'])) {
            $fields['country_code'] = strtoupper($fields['country_code']);
        }
        if (isset($fields['username'])) {
            $fields['username'] = strtolower($fields['username']);
        }
        $user->fill($fields)->save();
        if ($step === 'affiliation' && $user->university_id) {
            $this->institutionAdoption->recordSelfDeclared($user, 'onboarding');
        }
        $profile = match ($step) {
            'profile' => Arr::only($data, ['headline', 'biography']),
            'interests' => array_merge(array_key_exists('academic_position', $data) ? ['position' => $data['academic_position']] : [],
                ['expertise' => array_values(array_unique(array_merge($user->research_interests ?? [], $user->skills ?? [])))]),
            'preferences' => ['is_public' => $data['profile_visibility'] === 'public', 'privacy_settings' => array_merge($user->creatorProfile?->privacy_settings ?? [], [
                'show_institution' => $data['profile_visibility'] !== 'private', 'show_department' => $data['profile_visibility'] === 'public',
                'personalized_recommendations' => (bool) Arr::get($data, 'notification_preferences.personalized_recommendations', true),
            ])],
            default => [],
        };
        if ($profile) {
            $user->creatorProfile()->updateOrCreate(['user_id' => $user->id], $profile);
        }
        if ($step === 'preferences') {
            $prefs = array_replace($this->defaultNotifications(), $data['notification_preferences']);
            $user->notificationSetting()->updateOrCreate(['user_id' => $user->id], [
                'email_enabled' => $prefs['email'], 'in_app_enabled' => $prefs['in_app'],
                'research_updates' => $prefs['research_updates'], 'academic_alerts' => $prefs['event_reminders'],
            ]);
        }
    }

    public function assertAffiliation(array $data, ?string $path): array
    {
        $validated = Validator::make($data, [
            'university_id' => [Rule::requiredIf(in_array($path, self::INSTITUTIONAL_PATHS, true)), 'nullable', 'integer', 'exists:universities,id'],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ])->validate();

        if (! empty($validated['faculty_id'])) {
            $faculty = Faculty::findOrFail($validated['faculty_id']);
            abort_if(! empty($validated['university_id']) && $faculty->university_id !== (int) $validated['university_id'], 422, 'The selected faculty does not belong to that university.');
        }

        if (! empty($validated['department_id'])) {
            $department = Department::with('faculty')->findOrFail($validated['department_id']);
            abort_if(! empty($validated['faculty_id']) && $department->faculty_id !== (int) $validated['faculty_id'], 422, 'The selected department does not belong to that faculty.');
            abort_if(! empty($validated['university_id']) && $department->faculty?->university_id !== (int) $validated['university_id'], 422, 'The selected department does not belong to that university.');
        }

        if (! empty($validated['department_id'])) {
            $validated['faculty_id'] = $department->faculty_id;
            $validated['university_id'] = $department->faculty->university_id;
        } elseif (! empty($validated['faculty_id'])) {
            $validated['university_id'] = $faculty->university_id;
        }

        return $validated;
    }

    public function complete(User $user): User
    {
        $completed = DB::transaction(function () use ($user): bool {
            $personal = User::withoutGlobalScopes()->lockForUpdate()->findOrFail($user->id);
            // Repeated review submissions cannot replay welcome events or historical drafts.
            if ($personal->onboarding_completed_at) {
                return false;
            }
            $state = $this->state($personal);
            $data = $this->data($personal, $state);
            if (isset($data['username'])) {
                $data['username'] = strtolower($data['username']);
            }
            Validator::make(array_merge($data, ['path' => $state->path]), [
                'path' => ['required', Rule::in(array_keys(self::ACCOUNT_PATHS))],
                'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
                'username' => ['required', 'alpha_dash', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($personal->id)],
            ])->validate();
            $data = array_replace($data, $this->assertAffiliation($data, $state->path));
            $this->applyChanges($personal, 'path', ['path' => $state->path]);
            foreach (['profile', 'affiliation', 'interests'] as $step) {
                $this->applyChanges($personal, $step, $data);
            }
            if (filled($data['profile_visibility'] ?? null) && filled($data['notification_preferences'] ?? null)) {
                $personal->unsetRelation('creatorProfile');
                $this->applyChanges($personal, 'preferences', $data);
            }
            $personal->forceFill(['onboarding_completed_at' => now(), 'onboarding_version' => 1])->save();
            $state->forceFill(['current_step' => count(self::STEPS), 'completed_at' => now(), 'last_saved_at' => now()])->save();

            return true;
        }, 3);
        $fresh = User::withoutGlobalScopes()->findOrFail($user->id);
        $user->setRawAttributes($fresh->getAttributes(), true);
        $user->unsetRelation('creatorProfile');
        $user->unsetRelation('onboardingState');
        if ($completed) {
            $this->notifications->send($fresh, 'onboarding_completed', 'Welcome to AcadSyntra', 'Your profile and workspace setup are complete.', ['account_type' => $fresh->account_type]);
            app(MeaningfulActivityService::class)->onboardingCompleted($fresh);
        }

        return $fresh;
    }

    private function fieldLabel(string $field): string
    {
        return match ($field) {
            'university_id' => 'institution', 'faculty_id' => 'faculty or school', 'department_id' => 'department',
            'avatar_media_id' => 'profile photo', 'biography' => 'short biography', 'headline' => 'professional headline',
            default => str_replace('_', ' ', $field),
        };
    }

    public function accountPaths(?string $current = null): array
    {
        $paths = self::PUBLIC_ACCOUNT_PATHS + self::ADOPTION_ACCOUNT_PATHS;
        if ($current && isset(self::ACCOUNT_PATHS[$current])) {
            $paths[$current] = self::ACCOUNT_PATHS[$current];
        }

        return $paths;
    }

    public function interestRules(?string $path): array
    {
        $rules = [];
        if ($path === 'student') {
            $rules += ['programme' => ['nullable', 'string', 'max:255'], 'academic_level' => ['nullable', 'string', 'max:80']];
        }
        if ($path === 'lecturer') {
            $rules += ['academic_position' => ['nullable', 'string', 'max:255'],
                'specialisation' => ['nullable', 'string', 'max:255'], 'supervision_interests' => ['nullable', 'array', 'max:20'], 'supervision_interests.*' => ['string', 'max:100']];
        }
        foreach (['research_interests', 'skills', 'topic_interests', 'community_interests', 'event_interests'] as $field) {
            $rules[$field] = ['nullable', 'array', in_array($field, ['skills', 'topic_interests'], true) ? 'max:30' : 'max:20'];
            $rules[$field.'.*'] = ['string', 'max:100'];
        }

        return $rules;
    }

    public function normalizeStepData(string $step, array $validated): array
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

        return $step === 'path' ? ['path' => $validated['path']] : $validated;
    }

    public function defaultNotifications(): array
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
}
