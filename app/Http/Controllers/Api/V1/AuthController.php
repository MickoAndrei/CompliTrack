<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\TransientToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'fcmToken' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = User::where('email', strtolower(trim($validated['email'])))->first();
        if (! $user || (! $user->isActive && (int) $user->roleId !== 0) || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if ((int) $user->roleId === 0 && ! $user->isActive) {
            $user->isActive = true;
        }
        $user->lastLoginAt = now()->utc();
        if (array_key_exists('fcmToken', $validated)) {
            $user->fcmToken = $validated['fcmToken'];
        }
        $user->save();

        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return response()->json(['user' => $user->toArray()]);
        }

        $token = $user->createToken('mobile', ['*'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->toArray(),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof TransientToken) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } else {
            $token?->delete();
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

        $rawToken = \Illuminate\Support\Str::random(64);
        \App\Models\PasswordResetToken::create([
            'email' => strtolower($user->email),
            'token' => hash('sha256', $rawToken),
            'createdAt' => now(),
        ]);
        $resetLink = url("/reset-password?token={$rawToken}&email={$user->email}");

        \Illuminate\Support\Facades\Log::info("TEST RESET LINK for {$user->email}: {$resetLink}");

        return response()->json([
            'message' => 'Password updated successfully.',
            'test_reset_link' => $resetLink, // <-- this is what you give to user for testing
            'stored_in' => 'storage/logs/laravel.log + audit_logs collection'
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
        $previousPath = $user->avatarPath;
        $path = 'avatars/'.bin2hex(random_bytes(16)).'.'.$image['extension'];
        $saved = $this->writeAvatarFile($path, $contents);

        if (! $saved) {
            return response()->json(['message' => 'The profile photo could not be saved.'], 500);
        }

        $user->avatarPath = $path;
        $user->save();
        if ($previousPath && $previousPath !== $path) {
            $this->deleteAvatarFile($previousPath);
        }

        return response()->json(['avatarUrl' => url('/api/v1/auth/avatar')]);
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
        $path = $request->user()->avatarPath;
        $absolutePath = $path ? $this->avatarFilePath($path) : null;
        abort_unless($absolutePath && is_file($absolutePath), 404);

        return response()->file($absolutePath, [
            'Content-Type' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'application/octet-stream',
            },
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function writeAvatarFile(string $relativePath, string $contents): bool
    {
        $path = $this->avatarFilePath($relativePath);
        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }

    private function deleteAvatarFile(string $relativePath): void
    {
        $path = $this->avatarFilePath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function avatarFilePath(string $relativePath): string
    {
        abort_unless(
            preg_match('/^avatars\\/[A-Za-z0-9._-]+\\.(?:jpg|jpeg|png|webp)$/i', $relativePath) === 1,
            404,
        );

        return rtrim(config('filesystems.disks.public.root'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
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

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
