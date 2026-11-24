<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogFields;
use App\Support\MongoId;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        Request $request,
        string $action,
        string $entityType,
        mixed $entityId = null,
        string $outcome = 'success',
        ?User $actor = null,
    ): void {
        $user = $actor ?? $request->user();

        AuditLog::create([
            'userId' => $user?->getKey() ? MongoId::parse((string) $user->getKey()) : null,
            'userName' => $user?->fullName ?? 'System',
            'action' => $action,
            'entityType' => $entityType,
            'entityId' => $entityId !== null && $entityId !== '' ? MongoId::parse((string) $entityId, 'entityId') : null,
            'ipAddress' => $request->ip(),
            'userAgent' => $request->userAgent(),
            'category' => AuditLogFields::category($action, $entityType),
            'outcome' => $outcome,
            // Route templates carry no query strings, credentials or document content.
            'route' => $request->route()?->uri(),
            'method' => $request->method(),
        ]);
    }
}
