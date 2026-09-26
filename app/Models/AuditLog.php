<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audit_logs';

    protected $fillable = [
        'userId', 'action', 'entityType', 'entityId', 'ipAddress', 'userAgent',
    ];
}
