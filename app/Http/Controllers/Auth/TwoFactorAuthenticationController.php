<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\TotpService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorAuthenticationController extends Controller
{
    public function showChallengeForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user?->two_factor_secret || $this->hasValidSession($request)) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function confirm(Request $request, TotpService $totp): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        if (! $user?->two_factor_secret) {
            return redirect()->route('dashboard');
        }

        $valid = $totp->verifyUserCode($user, $data['code']);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The authentication or recovery code is incorrect.'],
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put([
            'auth.two_factor_passed' => true,
            'auth.two_factor_user_id' => (int) $user->getKey(),
            'auth.two_factor_passed_at' => now()->toIso8601String(),
        ]);

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();

        return redirect()->intended(
            $user->onboarding_completed_at ? route('dashboard') : route('onboarding.show')
        );
    }

    private function hasValidSession(Request $request): bool
    {
        $user = $request->user();
        $passedAt = $request->session()->get('auth.two_factor_passed_at');
        $ttlHours = max(1, (int) config('auth.two_factor_session_hours', 12));

        return (bool) $request->session()->get('auth.two_factor_passed')
            && (int) $request->session()->get('auth.two_factor_user_id') === (int) $user?->getKey()
            && is_string($passedAt)
            && Carbon::parse($passedAt)->addHours($ttlHours)->isFuture();
    }
}
