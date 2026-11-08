<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;
class College extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'colleges';
    protected $guarded = ['_id'];
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
}
