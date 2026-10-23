<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLink extends Notification
{
    public function __construct(private readonly string $resetUrl)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('CompliTrack password reset')
            ->line('A password reset was requested for your account.')
            ->line('Click the link below to choose a new password. This link expires in 10 minutes and can be used once.')
            ->action('Reset password', $this->resetUrl)
            ->line('If you did not request this, no action is needed.');
    }
}
