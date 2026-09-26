<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Notification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications';

    protected $fillable = [
        'recipientUserId', 'senderUserId', 'type', 'title', 'message',
        'relatedCheckId', 'isRead', 'fcmSent',
    ];
}
