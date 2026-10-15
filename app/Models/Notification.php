<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Casts\NullableObjectId;

class Notification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $fillable = [
        'recipientUserId', 'senderUserId', 'type', 'title', 'message',
        'relatedCheckId', 'isRead', 'fcmSent', 'dedupeKey',
    ];

    protected $casts = [
        'recipientUserId' => NullableObjectId::class,
        'senderUserId' => NullableObjectId::class,
        'relatedCheckId' => NullableObjectId::class,
        'isRead' => 'boolean',
        'fcmSent' => 'boolean',
    ];
}
