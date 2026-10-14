<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class AuditLogService
{
    public function record(
        Request $request,
        string $action,
        string $entityType,
        ?ObjectId $entityId = null,
    ): void {
        $user = $request->user();

        AuditLog::create([
            'userId' => $user?->getKey(),
            'userName' => $user?->fullName ?? 'System',
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'ipAddress' => $request->ip(),
            'userAgent' => $request->userAgent(),
        ]);
    }
}