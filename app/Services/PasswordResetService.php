<?php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function createWatch(): string
    {
        $watch = bin2hex(random_bytes(32));
        Cache::put('password-reset-watch:'.hash('sha256', $watch), 'pending', now()->addMinutes(10));

        return $watch;
    }

    public function status(string $watch): string
    {
        return Cache::get('password-reset-watch:'.hash('sha256', $watch), 'expired');
    }

    public function complete(string $resetHash): void
    {
        $watchHash = Cache::pull('password-reset-watch-for:'.$resetHash);
        if ($watchHash) {
            Cache::put('password-reset-watch:'.$watchHash, 'completed', now()->addMinutes(10));
        }
    }

    public function send(User $user, ?string $watch = null): void
    {
        $token = Str::random(64);
        PasswordResetToken::updateOrCreate(
            ['email' => strtolower($user->email)],
            ['token' => hash('sha256', $token), 'createdAt' => now()->utc()],
        );

        if ($watch) {
            Cache::put('password-reset-watch-for:'.hash('sha256', $token), hash('sha256', $watch), now()->addMinutes(10));
        }

        // The configured public URL is authoritative, including when requested from localhost.
        $resetUrl = rtrim(config('app.url'), '/').route('password.reset.form', [
            'email' => strtolower($user->email),
            'token' => $token,
        ], false);

        Notification::route('mail', $user->email)
            ->notify(new PasswordResetLink($resetUrl));
    }
}
