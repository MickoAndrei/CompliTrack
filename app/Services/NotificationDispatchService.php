<?php

namespace App\Services;

class NotificationDispatchService
{
    public function persist(mixed $recipient, string $key, string $type, string $title, string $message, mixed $sender = null, mixed $check = null, ?string $destination = null): \App\Models\Notification
    {
        return \App\Models\Notification::firstOrCreate(['dedupeKey' => $key], [
            'recipientUserId' => $recipient, 'senderUserId' => $sender, 'relatedCheckId' => $check,
            'type' => $type, 'title' => $title, 'message' => $message, 'isRead' => false, 'destination' => $destination,
        ]);
    }
}
