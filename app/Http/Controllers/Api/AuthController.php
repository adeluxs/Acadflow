<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['error' => 'Account disabled'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function register(Request $request)
    {
        // Build password rules based on settings
        $passwordRules = array_merge(
            ['required', 'string'],
            SettingService::getPasswordRules()
        );
        
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'student_id' => 'nullable|string|unique:users',
            'password' => implode('|', $passwordRules),
        ]);

        $user = User::create([
            'uuid' => Str::uuid(),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'student_id' => $validated['student_id'] ?? null,
            'password' => $validated['password'],
            'role' => 'student',
            'is_active' => 1,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'phone' => 'string|max:20',
        ]);

        $request->user()->update($validated);

        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        // Build password rules based on settings
        $passwordRules = array_merge(
            ['required', 'string', 'confirmed'],
            SettingService::getPasswordRules()
        );
        
        $validated = $request->validate([
            'current_password' => 'required',
            'password' => implode('|', $passwordRules),
        ]);

        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            return response()->json(['error' => 'Current password incorrect'], 400);
        }

        $request->user()->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Password changed']);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(['email' => $validated['email']]);

        return response()->json([
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset email sent.'
                : 'Unable to send password reset email.',
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    public function notifications(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function markNotificationRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllNotificationsRead(Request $request)
    {
        Notification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
