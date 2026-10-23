<?php

namespace App\Services;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordResetLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function send(User $user): void
    {
        $token = Str::random(64);
        PasswordResetToken::updateOrCreate(
            ['email' => strtolower($user->email)],
            ['token' => hash('sha256', $token), 'createdAt' => now()->utc()],
        );

        $resetUrl = route('password.reset.form', [
            'email' => strtolower($user->email),
            'token' => $token,
        ]);

        Notification::route('mail', $user->email)
            ->notify(new PasswordResetLink($resetUrl));
    }
}
