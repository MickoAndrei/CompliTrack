<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Casts\NullableObjectId;

class ArchiveDocument extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'archive_documents';
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    protected $fillable = [
        'fileCode', 'originalName', 'encryptedPath', 'mimeType', 'fileSize',
        'checksum', 'departmentId', 'uploadedByUserId', 'referenceStandard',
        'tags', 'isEncrypted', 'encryptionIv', 'encryptionTag',
    ];

    protected $hidden = ['encryptedPath', 'encryptionIv', 'encryptionTag'];

    protected $casts = [
        'departmentId' => NullableObjectId::class,
        'uploadedByUserId' => NullableObjectId::class,
        'isEncrypted' => 'boolean',
    ];
}
