<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\FeatureAccessService;
use App\Services\SettingService;
use App\Services\TotpService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, TotpService $totp): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'two_factor_code' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $key = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])->first();
        $scope = $user?->university_id;
        $maxAttempts = max(1, (int) SettingService::get('max_login_attempts', 5, $scope));
        $lockoutSeconds = max(60, (int) SettingService::get('lockout_duration_minutes', 15, $scope) * 60);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Too many sign-in attempts.',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, $lockoutSeconds);
            return response()->json(['message' => 'The email address or password is incorrect.'], 401);
        }
        RateLimiter::clear($key);

        if (! $user->is_active) return response()->json(['message' => 'This account is inactive.'], 403);

        if ($user->two_factor_secret) {
            $code = (string) ($credentials['two_factor_code'] ?? '');
            if ($code === '' || ! $totp->verifyUserCode($user, $code)) {
                return response()->json(['message' => 'A valid two-factor or recovery code is required.', 'next_action' => 'two_factor'], 422);
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $ready = $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null;
        $token = $user->createToken(
            $credentials['device_name'] ?? 'api-client',
            [$ready ? 'platform:access' : 'account:onboarding']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'next_action' => ! $user->hasVerifiedEmail() ? 'verify_email' : (! $user->onboarding_completed_at ? 'complete_onboarding' : null),
            'user' => $this->userPayload($user),
            'features' => FeatureAccessService::clientSnapshot($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:student,lecturer,researcher,university_representative,department_representative,academic_staff,non_academic_staff,independent_professional,author_publisher,research_discovery,community_events,alumni,organisation,other'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => array_values(array_unique(array_merge(SettingService::getPasswordRules(), ['confirmed']))),
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'username' => Str::lower($validated['username']),
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
            'account_type' => $validated['account_type'],
            'role' => $validated['account_type'] === 'student' ? 'student' : 'member',
            'is_active' => true,
        ]);

        UserOnboardingState::create([
            'user_id' => $user->id,
            'path' => $validated['account_type'],
            'current_step' => 2,
            'data' => ['path' => $validated['account_type'], 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'username' => $user->username],
            'started_at' => now(),
            'last_saved_at' => now(),
        ]);

        event(new Registered($user));
        $token = $user->createToken('onboarding-client', ['account:onboarding'])->plainTextToken;

        return response()->json([
            'message' => 'Account created. Verify your email, then complete onboarding.',
            'token' => $token,
            'token_type' => 'Bearer',
            'next_action' => 'verify_email',
            'user' => $this->userPayload($user),
            'features' => FeatureAccessService::clientSnapshot($user),
        ], 201);
    }

    public function accountStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'features' => FeatureAccessService::clientSnapshot($user),
            'ready' => $user->is_active && $user->hasVerifiedEmail() && $user->onboarding_completed_at !== null,
            'next_action' => ! $user->is_active
                ? 'contact_support'
                : (! $user->hasVerifiedEmail() ? 'verify_email' : (! $user->onboarding_completed_at ? 'complete_onboarding' : null)),
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'The email address is already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'A new verification link was sent.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'features' => FeatureAccessService::clientSnapshot($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile_visibility' => ['sometimes', 'in:public,institution,private'],
            'notification_preferences' => ['sometimes', 'array'],
        ]);
        $request->user()->update($validated);
        $user = $request->user()->fresh();

        return response()->json([
            'user' => $this->userPayload($user),
            'features' => FeatureAccessService::clientSnapshot($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $passwordRules = array_values(array_unique(array_merge(SettingService::getPasswordRules(), ['confirmed'])));
        $validated = $request->validate(['current_password' => ['required', 'string'], 'password' => $passwordRules]);
        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => ['The current password is incorrect.']]);
        }
        $request->user()->update(['password' => $validated['password']]);
        $request->user()->tokens()->where('id', '!=', $request->user()->currentAccessToken()?->id)->delete();
        return response()->json(['message' => 'Password changed. Other API sessions were revoked.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        Password::sendResetLink(['email' => Str::lower($validated['email'])]);
        return response()->json(['message' => 'If an account matches that email address, a password reset link will be sent.']);
    }

    public function notifications(Request $request): JsonResponse
    {
        return response()->json(Notification::query()->where('user_id', $request->user()->id)->latest()->paginate(20));
    }

    public function markNotificationRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);
        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        Notification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    private function userPayload(User $user): array
    {
        return $user->only([
            'uuid', 'first_name', 'last_name', 'username', 'email', 'phone', 'country_code', 'location',
            'account_type', 'role', 'university_id', 'faculty_id', 'department_id', 'programme',
            'academic_level', 'profile_visibility', 'email_verified_at', 'onboarding_completed_at', 'is_active',
        ]);
    }
}
