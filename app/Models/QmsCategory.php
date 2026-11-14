<?php
namespace App\Models;
use MongoDB\Laravel\Eloquent\Model;
class QmsCategory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'qms_categories';
    protected $guarded = ['_id'];
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';
    protected $casts = ['collegeId' => \App\Casts\NullableObjectId::class, 'parentId' => \App\Casts\NullableObjectId::class, 'departmentId' => \App\Casts\NullableObjectId::class];
}
