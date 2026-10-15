<?php

namespace App\Services;

use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\User;
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class ComplianceCheckService
{
    public function __construct(
        private readonly CounterService $counters,
        private readonly RoleScopeService $scopes,
        private readonly AuditLogService $auditLogs,
    ) {
    }

    public function create(User $actor, array $data, Request $request): ComplianceCheck
    {
        abort_unless((int) $actor->roleId === 2, 403);

        $departmentId = MongoId::parse($data['departmentId'], 'departmentId');
        $auditeeId = MongoId::parse($data['auditeeId'], 'auditeeId');
        abort_unless($this->scopes->canAccessDepartment($actor, $departmentId), 403);

        $department = Department::find($departmentId);
        $auditee = User::find($auditeeId);
        if (!$department || !$department->isActive
            || !$auditee || !$auditee->isActive || (int) $auditee->roleId !== 3
            || (string) $auditee->departmentId !== (string) $departmentId) {
            throw ValidationException::withMessages([
                'auditeeId' => 'The auditee must be active and belong to the selected department.',
            ]);
        }

        $linkedArchiveIds = [];
        foreach ($data['linkedArchiveIds'] ?? [] as $archiveId) {
            $objectId = MongoId::parse($archiveId, 'linkedArchiveIds');
            $archive = $this->scopes->archives($actor)
                ->where('_id', $objectId)
                ->first();
            if (!$archive
                || (string) $archive->departmentId !== (string) $departmentId
                || $archive->referenceStandard !== $data['referenceStandard']) {
                throw ValidationException::withMessages([
                    'linkedArchiveIds' => 'Selected evidence must belong to the check department and reference standard.',
                ]);
            }
            $linkedArchiveIds[] = $archive->getKey();
        }

        $now = now()->utc();
        $nonCompliant = $data['result'] === 'non_compliant';
        $check = ComplianceCheck::create([
            'checkCode' => $this->counters->next('CHK', yearly: true),
            'departmentId' => $departmentId,
            'auditorId' => $actor->getKey(),
            'auditeeId' => $auditeeId,
            'referenceStandard' => $data['referenceStandard'],
            'result' => $data['result'],
            'remarks' => $data['remarks'] ?? null,
            'correctiveStatus' => $nonCompliant ? 'open' : null,
            'statusHistory' => $nonCompliant ? [[
                'status' => 'open',
                'changedByUserId' => $actor->getKey(),
                'remarks' => 'Finding created',
                'timestamp' => $now,
            ]] : [],
            'linkedArchiveIds' => $linkedArchiveIds,
            'dueDate' => $nonCompliant ? $now->copy()->addDays(7) : null,
            'closedAt' => null,
        ]);

        $this->auditLogs->record($request, 'Created compliance check', 'compliance_check', $check->getKey());

        return $check;
    }

    public function updateStatus(
        User $actor,
        string $id,
        string $nextStatus,
        ?string $remarks,
        Request $request,
    ): ComplianceCheck {
        abort_unless((int) $actor->roleId === 2, 403);

        $objectId = MongoId::parse($id);
        $check = $this->scopes->checks($actor)->where('_id', $objectId)->firstOrFail();
        $previousStatus = $check->correctiveStatus;

        $allowed = match ($previousStatus) {
            'open' => ['in_progress'],
            'in_progress' => ['closed'],
            'closed' => ['open'],
            default => [],
        };

        if (!in_array($nextStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'correctiveStatus' => 'This corrective-action status transition is not allowed.',
            ]);
        }
        if ($previousStatus === 'closed' && trim((string) $remarks) === '') {
            throw ValidationException::withMessages([
                'remarks' => 'Remarks are required when reopening a closed check.',
            ]);
        }

        $now = now()->utc();
        $update = [
            '$set' => [
                'correctiveStatus' => $nextStatus,
                'updatedAt' => new UTCDateTime($now->getTimestamp() * 1000),
            ],
            '$push' => [
                'statusHistory' => [
                    'status' => $nextStatus,
                    'changedByUserId' => $actor->getKey(),
                    'remarks' => $remarks,
                    'timestamp' => new UTCDateTime($now->getTimestamp() * 1000),
                ],
            ],
        ];

        if ($nextStatus === 'closed') {
            $update['$set']['closedAt'] = new UTCDateTime($now->getTimestamp() * 1000);
        } elseif ($previousStatus === 'closed') {
            $update['$unset'] = ['closedAt' => ''];
        }

        $result = $check->getConnection()
            ->getMongoDB()
            ->selectCollection($check->getTable())
            ->updateOne(
                ['_id' => $check->getKey(), 'correctiveStatus' => $previousStatus],
                $update,
            );

        if ($result->getModifiedCount() !== 1) {
            abort(409, 'The check was updated by another request. Refresh and retry.');
        }

        $this->auditLogs->record($request, "Changed check status to {$nextStatus}", 'compliance_check', $check->getKey());

        return $check->fresh();
    }
}
