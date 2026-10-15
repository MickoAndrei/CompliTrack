<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'fcmToken' => ['sometimes', 'nullable', 'string', 'max:4096'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();
        if (!$user || !$user->isActive || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        $user->lastLoginAt = now()->utc();
        if (array_key_exists('fcmToken', $validated)) {
            $user->fcmToken = $validated['fcmToken'];
        }
        $user->save();

        $token = $user->createToken('mobile', ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->toArray(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'currentPassword' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $user = $request->user();
        if (!Hash::check($validated['currentPassword'], $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function forgotPassword(Request $request, PasswordResetService $passwordResets)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->where('isActive', true)->first();

        if ($user) {
            $passwordResets->send($user);
        }

        return response()->json([
            'message' => 'If an active account exists for that email, a reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $email = strtolower(trim($validated['email']));
        $reset = PasswordResetToken::where('email', $email)
            ->where('token', hash('sha256', $validated['token']))
            ->where('createdAt', '>=', now()->subMinutes(60))
            ->first();

        if (!$reset) {
            return response()->json(['message' => 'The password reset token is invalid or expired.'], 422);
        }

        $user = User::where('email', $email)->where('isActive', true)->first();
        if (!$user) {
            $reset->delete();

            return response()->json(['message' => 'The password reset token is invalid or expired.'], 422);
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->save();
        $reset->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
