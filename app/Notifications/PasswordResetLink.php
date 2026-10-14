<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetLink extends Notification
{
    public function __construct(
        private readonly string $token,
        private readonly string $email,
    )
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
            ->line('Enter this one-time code in the CompliTrack app: '.$this->token)
            ->line('This code expires in 60 minutes. If you did not request this, no action is needed.');
    }
}
