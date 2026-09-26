<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Department extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'departments';

    protected $fillable = [
        'departmentCode', 'departmentName', 'collegeName',
        'campusName', 'headUserId', 'isActive',
    ];
}
