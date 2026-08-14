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
        $state = $onboarding->state($request->user());
        $universities = University::query()
            ->where('is_active', true)
            ->with(['faculties' => fn ($query) => $query->where('is_active', true)->with([
                'departments' => fn ($departments) => $departments->where('is_active', true),
            ])])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'steps' => OnboardingService::STEPS,
            'account_paths' => OnboardingService::ACCOUNT_PATHS,
            'state' => $state,
            'user' => $request->user()->only(['uuid','first_name','last_name','username','email','email_verified_at','onboarding_completed_at']),
            'universities' => $universities,
        ]);
    }

    public function save(Request $request, string $step, OnboardingService $onboarding, MediaSecurityService $media): JsonResponse
    {
        abort_unless(in_array($step, OnboardingService::STEPS, true), 404);
        $user = $request->user();
        $state = $onboarding->state($user);

        $validated = match ($step) {
            'path' => $request->validate(['path' => ['required', Rule::in(array_keys(OnboardingService::ACCOUNT_PATHS))]]),
            'profile' => $request->validate([
                'first_name' => ['required','string','max:100'],
                'last_name' => ['required','string','max:100'],
                'username' => ['required','alpha_dash','min:3','max:60',Rule::unique('users','username')->ignore($user->id)],
                'headline' => ['nullable','string','max:255'],
                'biography' => ['nullable','string','max:3000'],
                'country_code' => ['nullable','string','size:2'],
                'location' => ['nullable','string','max:255'],
                'phone' => ['nullable','string','max:40'],
                'avatar' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            ]),
            'affiliation' => $onboarding->assertAffiliation($request->all(), $state->path),
            'interests' => $request->validate([
                'programme' => ['nullable','string','max:255'],
                'academic_level' => ['nullable','string','max:80'],
                'research_interests' => ['nullable','array','max:20'], 'research_interests.*' => ['string','max:100'],
                'skills' => ['nullable','array','max:30'], 'skills.*' => ['string','max:100'],
                'topic_interests' => ['nullable','array','max:30'], 'topic_interests.*' => ['string','max:100'],
                'community_interests' => ['nullable','array','max:20'], 'community_interests.*' => ['string','max:100'],
                'event_interests' => ['nullable','array','max:20'], 'event_interests.*' => ['string','max:100'],
            ]),
            'preferences' => $request->validate([
                'profile_visibility' => ['required',Rule::in(['public','institution','private'])],
                'email_notifications' => ['nullable','boolean'], 'in_app_notifications' => ['nullable','boolean'],
                'event_reminders' => ['nullable','boolean'], 'research_updates' => ['nullable','boolean'],
                'community_updates' => ['nullable','boolean'], 'personalized_recommendations' => ['nullable','boolean'],
            ]),
            'review' => [],
        };

        if ($step === 'profile' && $request->hasFile('avatar')) {
            $asset = $media->store($request->file('avatar'), $user, $user, 'public', ['purpose' => 'profile_avatar']);
            abort_unless(in_array($asset->scan_status, ['clean','skipped'], true), 422, 'The profile image did not pass security inspection.');
            $validated['avatar_media_id'] = $asset->id;
            unset($validated['avatar']);
        }

        $state = $onboarding->saveStep($user, $step, $validated);

        return response()->json(['message' => 'Onboarding progress saved.', 'state' => $state]);
    }

    public function skip(Request $request, string $step, OnboardingService $onboarding): JsonResponse
    {
        return response()->json(['message' => 'Onboarding step skipped.', 'state' => $onboarding->skip($request->user(), $step)]);
    }

    public function complete(Request $request, OnboardingService $onboarding): JsonResponse
    {
        abort_unless($request->user()->hasVerifiedEmail(), 403, 'Verify your email before completing onboarding.');
        $user = $onboarding->complete($request->user());
        $request->user()->currentAccessToken()?->delete();
        $token = $user->createToken($request->string('device_name', 'api-client')->toString(), ['platform:access'])->plainTextToken;

        return response()->json([
            'message' => 'Onboarding completed. A full-access API token has been issued.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['uuid','first_name','last_name','username','email','account_type','role','university_id','faculty_id','department_id','onboarding_completed_at']),
        ]);
    }
}
