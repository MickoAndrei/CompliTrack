<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    public function getJWTIdentifier(){
        return (string) $this->getKey();
    }

    public function getJWTCustomClaims(){
        return ['roleId'=>(int) $this->roleId];
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'userCode',
        'fullName',
        'roleId',
        'departmentId',
        'officeName',
        'roleSlug',
        'assignedDepartmentIds',
        'isActive',
        'archiveResponsibility', 'archiveUnitIds', 'archiveCollegeIds',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
            'lastLoginAt' => 'datetime',
            // Read timestamps created before this model used camelCase names.
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'isActive' => 'boolean',
        ];
    }
}
