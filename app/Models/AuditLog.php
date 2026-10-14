<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Casts\NullableObjectId;

class AuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audit_logs';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $fillable = [
        'userId', 'userName', 'action', 'entityType', 'entityId', 'ipAddress', 'userAgent',
    ];

    protected $casts = [
        'userId' => NullableObjectId::class,
        'entityId' => NullableObjectId::class,
    ];
}
