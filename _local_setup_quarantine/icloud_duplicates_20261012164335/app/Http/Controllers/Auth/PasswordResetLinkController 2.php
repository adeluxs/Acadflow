<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc'],
        ]);

        // Always return a neutral response to avoid account enumeration.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account exists for that address, a password reset link has been sent.');
    }
}
