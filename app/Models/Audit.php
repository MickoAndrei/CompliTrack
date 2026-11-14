<?php

namespace App\Models;

use App\Casts\NullableObjectId;
use MongoDB\Laravel\Eloquent\Model;

class Audit extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'audits';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
    protected $guarded = ['_id'];
    protected $casts = [
        'planId' => NullableObjectId::class,
        'departmentId' => NullableObjectId::class,
        'auditorId' => NullableObjectId::class,
        'reviewerId' => NullableObjectId::class,
    ];
}
