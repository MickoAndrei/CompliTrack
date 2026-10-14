<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Role extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'roles';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $fillable = ['roleId', 'roleSlug', 'roleName', 'permissions'];
}
