<?php
namespace App\Services;

use App\Models\{AuditLog, User};

class QmsRecentDocuments
{
    public function ids(User $actor): array
    {
        // Metadata inspection and editor polling are not file opens.
        return AuditLog::where('userId', \App\Support\MongoId::parse((string) $actor->getKey()))
            ->where('entityType', 'archive_document')->where('outcome', 'success')
            ->whereIn('action', ['Accessed authorized archive file', 'Opened ONLYOFFICE editor', 'Opened ONLYOFFICE read-only view'])
            ->orderBy('createdAt', 'desc')->orderBy('_id', 'desc')->get(['entityId'])
            ->pluck('entityId')->filter()->map('strval')->unique()->values()->all();
    }
}
