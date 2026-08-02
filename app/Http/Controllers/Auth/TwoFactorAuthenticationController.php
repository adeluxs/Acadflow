<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthenticationController extends Controller
{
    public function showChallengeForm()
    {
        return view('auth.two-factor-challenge');
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (! $user->two_factor_secret) {
            return redirect()->route('dashboard');
        }

        $valid = false;

        if (Hash::check($request->code, $user->two_factor_secret)) {
            $valid = true;
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The provided two-factor authentication code is incorrect.'],
            ]);
        }

        $user->update(['two_factor_confirmed_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }
}
