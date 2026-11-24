<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Services\ProfileImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();
        if (! $user || (! $user->isActive && (int) $user->roleId !== 0) || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ((int) $user->roleId !== 0 && (app(\App\Services\SystemSettingsService::class)->read()['maintenanceEnabled'] ?? false)) {
            abort(503, 'CompliTrack is undergoing maintenance. Please try again later.');
        }

        if ((int) $user->roleId === 0 && ! $user->isActive) {
            $user->isActive = true;
        }
        $user->lastLoginAt = now()->utc();
        $user->save();

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            app(\App\Services\AuditLogService::class)->record($request, 'Signed in', 'authentication', $user->getKey(), actor: $user);

            return response()->json(['user' => $user->toArray()]);
        }

        $token = auth('api')->login($user);
        app(\App\Services\AuditLogService::class)->record($request, 'Signed in', 'authentication', $user->getKey(), actor: $user);

        return response()->json([
            'token' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => auth('api')->factory()->getTTL()*60,
            'user' => $user->toArray(),
        ]);
    }

    public function logout(Request $request)
    {
        app(\App\Services\AuditLogService::class)->record($request, 'Signed out', 'authentication', $request->user()?->getKey());
        if ($request->hasSession() && Auth::guard('web')->check()){
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } else {
            auth('api')->logout(); // blacklist the JWT
        }

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
        if (! Hash::check($validated['currentPassword'], $user->password)) {
            return response()->json(['message' => 'The current password is incorrect.'], 422);
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->save();

        app(\App\Services\AuditLogService::class)->record($request, 'Changed account password', 'user', $user->getKey(), actor: $user);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'file', 'max:2048'],
        ]);
        $file = $validated['avatar'];
        $contents = $file->getContent();
        $image = $this->detectAvatarImage($contents);
        if ($image === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'avatar' => 'Choose a valid JPG, PNG, or WebP image up to 2 MB.',
            ]);
        }

        $user = $request->user();
        app(ProfileImageService::class)->store($user, $contents, match ($image['extension']) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        });

        return response()->json(['avatarUrl' => $user->avatarUrl()]);
    }

    private function detectAvatarImage(string $contents): ?array
    {
        if (str_starts_with($contents, "\xff\xd8\xff")) {
            return ['extension' => 'jpg'];
        }
        if (str_starts_with($contents, "\x89PNG\r\n\x1a\n")) {
            return ['extension' => 'png'];
        }
        if (strlen($contents) >= 12 && substr($contents, 0, 4) === 'RIFF' && substr($contents, 8, 4) === 'WEBP') {
            return ['extension' => 'webp'];
        }

        return null;
    }

    public function avatar(Request $request)
    {
        $user = $request->user();
        abort_unless(app(ProfileImageService::class)->migrateLegacy($user), 404);

        $versioned = $request->query('user') === (string) $user->getKey()
            && $request->query('version') === $user->avatarVersion;
        $response = response($user->avatarData->getData(), 200, [
            'Content-Type' => $user->avatarMimeType,
            'Cache-Control' => $versioned ? 'private, max-age=3600' : 'private, no-store',
            'Vary' => 'Cookie, Authorization',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setEtag($user->avatarVersion);
        $response->isNotModified($request);

        return $response;
    }

    public function forgotPassword(Request $request, PasswordResetService $passwordResets)
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->where('isActive', true)->first();
        $watch = $passwordResets->createWatch();

        if ($user) {
            $passwordResets->send($user, $watch);
        }

        return response()->json([
            'message' => 'If an active account exists for that email, a reset link has been sent.',
            'watch' => $watch,
        ]);
    }

    public function resetStatus(Request $request, PasswordResetService $passwordResets)
    {
        $data = $request->validate(['watch' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        return response()->json(['status' => $passwordResets->status($data['watch'])])->header('Cache-Control', 'no-store');
    }

    public function resetPassword(Request $request, PasswordResetService $passwordResets)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $email = strtolower(trim($validated['email']));
        $reset = PasswordResetToken::where('email', $email)
            ->where('token', hash('sha256', $validated['token']))
            ->where('createdAt', '>=', now()->subMinutes(10))
            ->first();

        if (! $reset) {
            return response()->json(['message' => 'The password reset token is invalid or expired.'], 422);
        }

        $user = User::where('email', $email)->where('isActive', true)->first();
        if (! $user) {
            $reset->delete();

            return response()->json(['message' => 'The password reset token is invalid or expired.'], 422);
        }

        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->save();
        $reset->delete();

        $passwordResets->complete($reset->token);

        app(\App\Services\AuditLogService::class)->record($request, 'Reset account password', 'user', $user->getKey());

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
