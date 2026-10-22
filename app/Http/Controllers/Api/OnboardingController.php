<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\University;
use App\Services\Media\MediaSecurityService;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function show(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $request->user()->load('notificationSetting');
        $state = $onboarding->state($request->user());
        $data = $onboarding->data($request->user(), $state);
        $universities = empty($data['university_id']) ? collect() : University::query()->whereKey($data['university_id'])
            ->with(['faculties:id,university_id,name', 'faculties.departments:id,faculty_id,name'])->get(['id', 'name', 'slug']);

        return response()->json([
            'steps' => OnboardingService::STEPS,
            'account_paths' => $onboarding->accountPaths($state->path),
            'state' => $state,
            'data' => $data,
            'progress' => $onboarding->progress($request->user(), $state),
            'directory_url' => url('/api/v1/onboarding/directory'),
            'user' => $request->user()->only(['uuid', 'first_name', 'last_name', 'username', 'email', 'email_verified_at', 'onboarding_completed_at']),
            'universities' => $universities,
        ]);
    }

    public function save(Request $request, string $step, OnboardingService $onboarding, MediaSecurityService $media): JsonResponse
    {
        abort_unless(in_array($step, OnboardingService::STEPS, true), 404);
        $user = $request->user();
        $state = $onboarding->state($user);

        if ($step === 'profile' && $request->has('username')) {
            $request->merge(['username' => strtolower((string) $request->input('username'))]);
        }

        $validated = match ($step) {
            'path' => $request->validate(['path' => ['required', Rule::in(array_keys($onboarding->accountPaths($state->path)))]]),
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
            'affiliation' => $onboarding->assertAffiliation($request->all(), $state->path),
            'interests' => $request->validate($onboarding->interestRules($user->onboarding_completed_at ? $user->account_type : $state->path)),
            'preferences' => $request->validate([
                'profile_visibility' => ['required', Rule::in(['public', 'institution', 'private'])],
                'email_notifications' => ['nullable', 'boolean'], 'in_app_notifications' => ['nullable', 'boolean'],
                'event_reminders' => ['nullable', 'boolean'], 'research_updates' => ['nullable', 'boolean'],
                'community_updates' => ['nullable', 'boolean'], 'personalized_recommendations' => ['nullable', 'boolean'],
            ]),
            'review' => [],
        };

        if ($step === 'profile' && $request->hasFile('avatar')) {
            $asset = $media->store($request->file('avatar'), $user, $user, 'public', ['purpose' => 'profile_avatar']);
            abort_unless(in_array($asset->scan_status, ['clean', 'skipped'], true), 422, 'The profile image did not pass security inspection.');
            $validated['avatar_media_id'] = $asset->id;
            unset($validated['avatar']);
        }

        $state = $onboarding->saveStep($user, $step, $validated);

        return response()->json(['message' => 'Onboarding progress saved.', 'state' => $state, 'progress' => $onboarding->progress($user->fresh(), $state)]);
    }

    public function skip(Request $request, string $step, OnboardingService $onboarding): JsonResponse
    {
        return response()->json(['message' => 'Onboarding step skipped.', 'state' => $onboarding->skip($request->user(), $step)]);
    }

    public function complete(Request $request, OnboardingService $onboarding): JsonResponse
    {
        abort_unless($request->user()->hasRequiredEmailVerification(), 403, 'Verify your email before completing onboarding.');
        $user = $onboarding->complete($request->user());
        $request->user()->currentAccessToken()?->delete();
        $token = $user->createToken($request->string('device_name', 'api-client')->toString(), ['platform:access'])->plainTextToken;

        return response()->json([
            'message' => 'Onboarding completed. A full-access API token has been issued.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['uuid', 'first_name', 'last_name', 'username', 'email', 'account_type', 'role', 'university_id', 'faculty_id', 'department_id', 'onboarding_completed_at']),
        ]);
    }
}
