<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Casts\NullableObjectId;

class Department extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'departments';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $fillable = [
        'departmentCode', 'departmentName', 'collegeName',
        'campusName', 'headUserId', 'isActive',
    ];

    protected $casts = [
        'headUserId' => NullableObjectId::class,
        'isActive' => 'boolean',
    ];
}
