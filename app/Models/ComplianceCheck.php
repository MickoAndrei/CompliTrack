<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ComplianceCheck extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'compliance_checks';

    protected $fillable = [
        'checkCode', 'departmentId', 'auditorId', 'auditeeId',
        'referenceStandard', 'result', 'correctiveStatus',
        'statusHistory', 'linkedArchiveIds', 'dueDate', 'closedAt',
    ];
}
