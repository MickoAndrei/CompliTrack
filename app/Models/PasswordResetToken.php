<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class PasswordResetToken extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'password_reset_tokens';
    public $timestamps = false;

    protected $fillable = ['email', 'token', 'createdAt'];

    protected $hidden = ['token'];

    protected $casts = ['createdAt' => 'datetime'];
}
