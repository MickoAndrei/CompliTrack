<?php

namespace App\Services;

use App\Models\{Audit, User};
use App\Support\MongoId;
use Illuminate\Http\Request;

class AuditeeEvidenceService
{
    public function audit(User $actor, string $id): Audit
    {
        abort_unless($actor->isActive, 403);
        $query = Audit::where('_id', MongoId::parse($id));
        if ((int) $actor->roleId === 2) {
            $query->where('auditorId', MongoId::parse((string) $actor->getKey()))
                ->whereIn('departmentId', app(RoleScopeService::class)->assignedDepartmentIds($actor));
        } elseif ((int) $actor->roleId === 3) {
            $query->where('departmentId', MongoId::parse((string) $actor->departmentId))
                ->where('evidenceRequests.auditeeId', (string) $actor->getKey());
        } else { abort(403); }
        return $query->firstOrFail();
    }

    public function requestIndex(Audit $audit, string $id, User $actor): int
    {
        foreach ($audit->evidenceRequests ?? [] as $index => $request) {
            if ($request['id'] === $id && ((int) $actor->roleId === 2 || $request['auditeeId'] === (string) $actor->getKey())) { return $index; }
        }
        abort(404);
    }

    public function save(Audit $audit, Request $request, array $changes): void
    {
        $request->validate(['version' => 'required|integer|min:1']);
        abort_unless(in_array($audit->status, ['draft', 'ready', 'returned'], true), 403, 'This audit is locked.');
        $updated = Audit::where('_id', $audit->getKey())->where('version', (int) $request->input('version'))
            ->update($changes + ['version' => (int) $audit->version + 1]);
        abort_unless($updated === 1, 409, 'This audit changed. Refresh before saving.');
        app(AuditLogService::class)->record($request, 'Updated audit evidence request', 'audit', $audit->getKey());
    }
}
