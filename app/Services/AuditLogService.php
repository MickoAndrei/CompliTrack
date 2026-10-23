<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\MongoId;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class AuditLogService
{
    public function record(
        Request $request,
        string $action,
        string $entityType,
        mixed $entityId = null,
    ): void {
        $user = $request->user();

        AuditLog::create([
            'userId' => $user?->getKey() ? MongoId::parse((string) $user->getKey()) : null,
            'userName' => $user?->fullName ?? 'System',
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId !== null && $entityId !== '' ? MongoId::parse((string) $entityId, 'entityId') : null,
            'ipAddress' => $request->ip(),
            'userAgent' => $request->userAgent(),
        ]);
    }
}
