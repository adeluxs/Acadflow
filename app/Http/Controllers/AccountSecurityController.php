<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SettingService;
use App\Services\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountSecurityController extends Controller
{
    public function show(Request $request): View
    {
        return view('account.security', [
            'user' => $request->user(),
            'twoFactorAvailable' => (bool) SettingService::get('enable_two_factor', false, $request->user()?->university_id),
            'pendingSecret' => $request->session()->get('security.two_factor_pending_secret'),
            'provisioningUri' => $request->session()->get('security.two_factor_provisioning_uri'),
            'recoveryCodes' => $request->session()->pull('security.two_factor_recovery_plain'),
        ]);
    }

    public function begin(Request $request, TotpService $totp): RedirectResponse
    {
        abort_unless((bool) SettingService::get('enable_two_factor', false, $request->user()?->university_id), 403, 'Two-factor authentication is disabled by your institution settings.');
        abort_if((bool) $request->user()->two_factor_secret, 422, 'Two-factor authentication is already enabled.');
        $secret = $totp->generateSecret();
        $request->session()->put([
            'security.two_factor_pending_secret' => $secret,
            'security.two_factor_provisioning_uri' => $totp->provisioningUri($request->user(), $secret),
        ]);

        return back()->with('success', 'Enter the six-digit code from your authenticator app to confirm setup.');
    }

    public function confirm(Request $request, TotpService $totp): RedirectResponse
    {
        abort_unless((bool) SettingService::get('enable_two_factor', false, $request->user()?->university_id), 403, 'Two-factor authentication is disabled by your institution settings.');
        $data = $request->validate(['code' => ['required','digits:6'], 'password' => ['required','current_password']]);
        $secret = (string) $request->session()->get('security.two_factor_pending_secret');
        abort_if($secret === '' || ! $totp->verify($secret, $data['code']), 422, 'The authenticator code is invalid or expired.');

        $recovery = $totp->recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $totp->encryptSecret($secret),
            'two_factor_recovery_codes' => $recovery['hashed'],
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget(['security.two_factor_pending_secret','security.two_factor_provisioning_uri']);
        $request->session()->put('security.two_factor_recovery_plain', $recovery['plain']);

        return back()->with('success', 'Two-factor authentication is enabled. Store the recovery codes securely.');
    }

    public function regenerateRecoveryCodes(Request $request, TotpService $totp): RedirectResponse
    {
        $request->validate(['password' => ['required','current_password']]);
        abort_unless((bool) $request->user()->two_factor_secret, 422, 'Enable two-factor authentication first.');
        $recovery = $totp->recoveryCodes();
        $request->user()->forceFill(['two_factor_recovery_codes' => $recovery['hashed']])->save();
        $request->session()->put('security.two_factor_recovery_plain', $recovery['plain']);

        return back()->with('success', 'New recovery codes were generated. Previous codes no longer work.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required','current_password']]);
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $request->session()->forget([
            'auth.two_factor_passed','auth.two_factor_user_id','auth.two_factor_passed_at',
            'security.two_factor_pending_secret','security.two_factor_provisioning_uri',
        ]);

        return back()->with('success', 'Two-factor authentication was disabled.');
    }
}
