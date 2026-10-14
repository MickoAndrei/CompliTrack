<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use App\Casts\NullableObjectId;
use App\Casts\ObjectIdArray;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $connection = 'mongodb';
    protected $collection = 'users';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userCode', 'fullName', 'email', 'password', 'roleId', 'roleSlug',
        'departmentId', 'assignedDepartmentIds', 'officeName', 'isActive', 'fcmToken', 'lastLoginAt',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'departmentId' => NullableObjectId::class,
        'assignedDepartmentIds' => ObjectIdArray::class,
        'roleId' => 'integer',
        'isActive' => 'boolean',
        'lastLoginAt' => 'datetime',
    ];

    // roleId: 0 = Super Admin, 1 = Admin, 2 = Auditor, 3 = Auditee (Guide §6)
    public function isSuperAdmin(): bool
    {
        return $this->roleId === 0;
    }

    public function isAdmin(): bool
    {
        return $this->roleId === 1;
    }

    public function isAuditor(): bool
    {
        return $this->roleId === 2;
    }

    public function isAuditee(): bool
    {
        return $this->roleId === 3;
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'departmentId');
    }
}
