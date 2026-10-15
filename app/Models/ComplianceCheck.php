<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Casts\NullableObjectId;

class ComplianceCheck extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'compliance_checks';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'checkCode', 'departmentId', 'auditorId', 'auditeeId',
        'referenceStandard', 'result', 'remarks', 'correctiveStatus',
        'statusHistory', 'linkedArchiveIds', 'dueDate', 'closedAt',
    ];

    protected $casts = [
        'departmentId' => NullableObjectId::class,
        'auditorId' => NullableObjectId::class,
        'auditeeId' => NullableObjectId::class,
        'dueDate' => 'datetime',
        'closedAt' => 'datetime',
    ];
}
