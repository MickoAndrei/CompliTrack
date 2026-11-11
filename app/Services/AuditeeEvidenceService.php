<?php

namespace App\Services;

use App\Models\{Audit, User};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Support\EvidenceRequestStatus;

class AuditeeEvidenceService
{
    public function addressedAudits(User $actor)
    {
        abort_unless($actor->isActive && (int) $actor->roleId === 3, 403);
        return Audit::where('departmentId', MongoId::parse((string) $actor->departmentId))
            ->where('evidenceRequests.auditeeId', (string) $actor->getKey());
    }

    public function requestCard(Audit $audit, array $entry, ?string $auditorName): array
    {
        $status = $entry['status'] ?? '';
        $due = $entry['dueDate'] ?? null;
        try { $due = $due ? \Illuminate\Support\Carbon::parse($due)->format('M j, Y') : null; }
        catch (\Throwable) { $due = null; }
        return [
            'id' => $entry['id'], 'auditId' => (string) $audit->getKey(),
            'title' => $entry['title'] ?? 'Evidence for '.($entry['referenceStandard'] ?? $audit->auditCode),
            'description' => $entry['description'] ?? '', 'referenceStandard' => $entry['referenceStandard'] ?? '',
            'auditCode' => $audit->auditCode, 'auditTitle' => $audit->title,
            'department' => $audit->departmentName, 'auditor' => $auditorName ?? 'Not recorded',
            'auditStatus' => $audit->status, 'status' => $status,
            'statusLabel' => EvidenceRequestStatus::label($status, $audit->status),
            'dueDate' => $due, 'createdAt' => $entry['createdAt'] ?? '',
            'canRespond' => in_array($status, ['requested', 'needs_information'], true)
                && in_array($audit->status, ['draft', 'ready', 'returned'], true),
            'hasDraft' => !empty($entry['draft']), 'allowUpload' => (bool) ($entry['allowUpload'] ?? false),
        ];
    }

    public function requestDetail(User $actor, string $requestId, ?string $auditId = null): array
    {
        $query = $this->addressedAudits($actor)->whereRaw(['evidenceRequests' => ['$elemMatch' => [
            'id' => $requestId, 'auditeeId' => (string) $actor->getKey(),
        ]]]);
        if ($auditId) { $query->where('_id', MongoId::parse($auditId)); }
        $audit = $query->firstOrFail(['auditCode', 'title', 'departmentName', 'auditorId', 'status', 'version', 'evidenceRequests']);
        $index = $this->requestIndex($audit, $requestId, $actor);
        $entry = Arr::only($audit->evidenceRequests[$index], ['id', 'description', 'referenceStandard', 'status', 'allowUpload', 'draft']);
        $entry['responses'] = collect($audit->evidenceRequests[$index]['responses'] ?? [])->map(function ($response) {
            $safe = Arr::only($response, ['method', 'at', 'reference', 'notes', 'availability', 'archiveId']);
            if (isset($response['review'])) { $safe['review'] = Arr::only($response['review'], ['feedback', 'signature', 'at']); }
            return $safe;
        })->all();
        $entry['draft'] = Arr::only($entry['draft'] ?? [], ['method', 'reference', 'notes', 'availability', 'archiveId']);
        // A stored reference never grants archive access. Only render download links already in the actor's archive scope.
        $archiveIds = collect($entry['responses'])->pluck('archiveId')->filter(fn ($id) => preg_match('/^[a-f0-9]{24}$/i', (string) $id))->unique();
        $downloadIds = $archiveIds->isEmpty() ? [] : app(QmsArchiveAccess::class)->documents($actor, true)
            ->whereIn('_id', $archiveIds->map(fn ($id) => MongoId::parse((string) $id))->all())
            ->where(function ($query) use ($audit, $requestId) {
                $query->where(fn ($query) => $query->where('auditId', (string) $audit->getKey())->where('requestId', $requestId))
                    ->orWhereIn('sharedAuditIds', [MongoId::parse((string) $audit->getKey()), (string) $audit->getKey()]);
            })
            ->get(['_id'])->map(fn ($archive) => (string) $archive->getKey())->all();
        return ['record' => $this->requestCard($audit, $audit->evidenceRequests[$index], User::find($audit->auditorId, ['fullName'])?->fullName),
            'entry' => $entry, 'version' => $audit->version, 'downloadIds' => $downloadIds];
    }

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
