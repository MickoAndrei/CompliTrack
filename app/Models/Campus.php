<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Campus extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'campuses';
    protected $guarded = ['_id'];
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
}
