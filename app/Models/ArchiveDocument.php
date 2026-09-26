<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ArchiveDocument extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'archive_documents';

    protected $fillable = [
        'fileCode', 'originalName', 'encryptedPath', 'mimeType', 'fileSize',
        'checksum', 'departmentId', 'uploadedByUserId', 'referenceStandard',
        'tags', 'isEncrypted', 'encryptionIv',
    ];
}
