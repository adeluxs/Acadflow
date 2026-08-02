<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordResetLinkController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users'],
        ]);

        Password::sendResetLink(
            $request->only('email')
        );

        return back()->with('status', 'We have emailed your password reset link.');
    }
}
