<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserOnboardingState;
use App\Services\SettingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->nextDestination($request->user());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = 'login:'.Str::lower($credentials['email']).'|'.$request->ip();
        $user = User::query()->whereRaw('LOWER(email) = ?', [Str::lower($credentials['email'])])->first();
        $scope = $user?->university_id;
        $maxAttempts = max(1, (int) SettingService::get('max_login_attempts', 5, $scope));
        $lockoutSeconds = max(60, (int) SettingService::get('lockout_duration_minutes', 15, $scope) * 60);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Too many sign-in attempts. Try again in {$seconds} seconds."],
            ]);
        }

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, $lockoutSeconds);
            throw ValidationException::withMessages([
                'email' => ['The email address or password is incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['This account is inactive. Contact an administrator if you believe this is an error.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->forget(['auth.two_factor_passed', 'auth.two_factor_user_id', 'auth.two_factor_passed_at']);
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->nextDestination($user);
    }

    public function showRegisterForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->nextDestination($request->user());
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_type' => ['required', 'in:student,lecturer,researcher,university_representative,department_representative,academic_staff,non_academic_staff,independent_professional,author_publisher,research_discovery,community_events,alumni,organisation,other'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', 'unique:users,username'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => array_values(array_unique(array_merge(SettingService::getPasswordRules(), ['confirmed']))),
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'username' => Str::lower($validated['username']),
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
            'account_type' => $validated['account_type'],
            // Public registration never grants privileged institutional roles.
            // Verification/administration may promote the user later.
            'role' => $validated['account_type'] === 'student' ? 'student' : 'member',
            'is_active' => true,
        ]);

        UserOnboardingState::create([
            'user_id' => $user->id,
            'path' => $validated['account_type'],
            'current_step' => 2,
            'data' => [
                'path' => $validated['account_type'],
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
            ],
            'started_at' => now(),
            'last_saved_at' => now(),
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice')
            ->with('status', 'We sent a verification link to your email address.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have signed out securely.');
    }

    private function nextDestination(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->two_factor_secret && ! $this->hasCurrentTwoFactorSession($user)) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $user->onboarding_completed_at) {
            return redirect()->route('onboarding.show');
        }

        return redirect()->intended(match ($user->role) {
            'super_admin', 'university_admin', 'department_admin' => route('admin.dashboard'),
            default => route('dashboard'),
        });
    }

    private function hasCurrentTwoFactorSession(User $user): bool
    {
        $passed = (bool) session()->get('auth.two_factor_passed');
        $userId = (int) session()->get('auth.two_factor_user_id');
        $passedAt = session()->get('auth.two_factor_passed_at');
        $ttlHours = max(1, (int) config('auth.two_factor_session_hours', 12));

        return $passed
            && $userId === (int) $user->getKey()
            && is_string($passedAt)
            && now()->diffInHours(\Illuminate\Support\Carbon::parse($passedAt), absolute: true) <= $ttlHours;
    }

}
