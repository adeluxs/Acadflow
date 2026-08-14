<?php

declare(strict_types=1);

namespace App\Services;

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

    public const INSTITUTIONAL_PATHS = [
        'student', 'lecturer', 'researcher', 'university_representative',
        'department_representative', 'academic_staff', 'non_academic_staff',
    ];

    public function __construct(private readonly SocialNotificationService $notifications) {}

    public function state(User $user): UserOnboardingState
    {
        return UserOnboardingState::firstOrCreate(
            ['user_id' => $user->id],
            ['path' => $user->account_type, 'current_step' => 1, 'started_at' => now(), 'data' => []]
        );
    }

    public function saveStep(User $user, string $step, array $validated): UserOnboardingState
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false, 404);

        $state = $this->state($user);
        if ($step === 'path') {
            $state->path = $validated['path'];
            if (! in_array($validated['path'], self::INSTITUTIONAL_PATHS, true)) {
                $current = $state->data ?? [];
                unset($current['university_id'], $current['faculty_id'], $current['department_id']);
                $state->data = $current;
            }
        }

        $state->data = array_merge($state->data ?? [], $this->normalizeStepData($step, $validated));
        $state->current_step = min(count(self::STEPS), $stepNumber + 1);
        $state->last_saved_at = now();
        $state->save();

        return $state->fresh();
    }

    public function skip(User $user, string $step): UserOnboardingState
    {
        $stepNumber = array_search($step, self::STEPS, true);
        abort_if($stepNumber === false || in_array($step, ['path', 'profile', 'review'], true), 422, 'This onboarding step is required.');

        $state = $this->state($user);
        if ($step === 'affiliation' && in_array($state->path, self::INSTITUTIONAL_PATHS, true)) {
            throw ValidationException::withMessages(['university_id' => ['Institutional account paths must confirm their affiliation.']]);
        }

        $state->skipped_steps = array_values(array_unique(array_merge($state->skipped_steps ?? [], [$step])));
        $state->current_step = min(count(self::STEPS), $stepNumber + 1);
        $state->last_saved_at = now();
        $state->save();

        return $state->fresh();
    }

    public function assertAffiliation(array $data, ?string $path): array
    {
        $validated = Validator::make($data, [
            'university_id' => [Rule::requiredIf(in_array($path, self::INSTITUTIONAL_PATHS, true)), 'nullable', 'integer', 'exists:universities,id'],
            'faculty_id' => [Rule::requiredIf($path === 'department_representative'), 'nullable', 'integer', 'exists:faculties,id'],
            'department_id' => [Rule::requiredIf($path === 'department_representative'), 'nullable', 'integer', 'exists:departments,id'],
        ])->validate();

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

    public function complete(User $user): User
    {
        $state = UserOnboardingState::where('user_id', $user->id)->firstOrFail();
        $data = $state->data ?? [];

        Validator::make(array_merge($data, ['path' => $state->path]), [
            'path' => ['required', Rule::in(array_keys(self::ACCOUNT_PATHS))],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($user->id)],
            'university_id' => [Rule::requiredIf(in_array($state->path, self::INSTITUTIONAL_PATHS, true)), 'nullable', 'integer', 'exists:universities,id'],
            'faculty_id' => [Rule::requiredIf($state->path === 'department_representative'), 'nullable', 'integer', 'exists:faculties,id'],
            'department_id' => [Rule::requiredIf($state->path === 'department_representative'), 'nullable', 'integer', 'exists:departments,id'],
        ])->validate();
        $this->assertAffiliation($data, $state->path);

        DB::transaction(function () use ($user, $state, $data): void {
            $user->fill([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'username' => strtolower($data['username']),
                'account_type' => $state->path,
                'role' => in_array($user->role, ['member', 'student'], true)
                    ? ($state->path === 'student' ? 'student' : 'member')
                    : $user->role,
                'phone' => $data['phone'] ?? $user->phone,
                'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
                'location' => $data['location'] ?? null,
                'avatar_media_id' => $data['avatar_media_id'] ?? $user->avatar_media_id,
                'university_id' => $data['university_id'] ?? null,
                'faculty_id' => $data['faculty_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'programme' => $data['programme'] ?? null,
                'academic_level' => $data['academic_level'] ?? null,
                'research_interests' => $data['research_interests'] ?? [],
                'skills' => $data['skills'] ?? [],
                'topic_interests' => $data['topic_interests'] ?? [],
                'community_interests' => $data['community_interests'] ?? [],
                'event_interests' => $data['event_interests'] ?? [],
                'profile_visibility' => $data['profile_visibility'] ?? 'public',
                'notification_preferences' => $data['notification_preferences'] ?? $this->defaultNotifications(),
                'onboarding_completed_at' => now(),
                'onboarding_version' => 1,
            ])->save();

            $user->creatorProfile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => $data['headline'] ?? null,
                    'biography' => $data['biography'] ?? null,
                    'expertise' => array_values(array_unique(array_merge($data['research_interests'] ?? [], $data['skills'] ?? []))),
                    'position' => self::ACCOUNT_PATHS[$state->path] ?? null,
                    'privacy_settings' => [
                        'show_institution' => ($data['profile_visibility'] ?? 'public') !== 'private',
                        'show_department' => ($data['profile_visibility'] ?? 'public') === 'public',
                        'personalized_recommendations' => (bool) Arr::get($data, 'notification_preferences.personalized_recommendations', true),
                    ],
                    'is_public' => ($data['profile_visibility'] ?? 'public') === 'public',
                ]
            );

            $state->forceFill([
                'current_step' => count(self::STEPS),
                'completed_at' => now(),
                'last_saved_at' => now(),
            ])->save();
        });

        $this->notifications->send($user, 'onboarding_completed', 'Welcome to AcadFlow', 'Your profile and workspace setup are complete.', ['account_type' => $user->account_type]);

        return $user->fresh();
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
