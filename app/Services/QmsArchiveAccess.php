<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, User};
use MongoDB\BSON\ObjectId;

class QmsArchiveAccess
{
    public function isAuditTeamLeader(User $actor): bool
    {
        if (!$actor->isActive || (int) $actor->roleId !== 1) { return false; }
        $key = 'qms.auditTeamLeader.'.(string) $actor->getKey();
        $attributes = request()->attributes;
        if (!$attributes->has($key)) {
            $attributes->set($key, Audit::whereIn('reviewerId', [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()])->exists());
        }
        return $attributes->get($key);
    }

    public function ids(array $ids): array
    {
        return array_values(array_map(fn ($id) => new ObjectId($id), array_unique(array_filter(array_map('strval', $ids), fn ($id) => preg_match('/^[a-f0-9]{24}$/i', $id)))));
    }

    public function organizationalUnits(User $actor, bool $verifiedOnly = false)
    {
        $query = Department::where('isActive', true)->where('unitType', '!=', 'legacy_unclassified');
        if ($verifiedOnly) { $query->where('ownershipVerified', true); }
        if (!$actor->isActive) { return $query->whereRaw(['_id' => null]); }
        if ($actor->archiveResponsibility === 'cluster_administrator') { return app(QmsFolderAccess::class)->units($actor); }
        if ((int) $actor->roleId === 0 || $this->isAuditTeamLeader($actor)) { return $query; }
        if ((int) $actor->roleId === 2) {
            return $query->whereIn('_id', $this->ids($actor->assignedDepartmentIds ?? []));
        }
        if (in_array($actor->archiveResponsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true)) {
            $colleges = \App\Models\College::whereIn('_id', $this->ids($actor->archiveCollegeIds ?? []))->where('isActive', true)->get(['_id'])->map(fn ($college) => $college->getKey())->all();
            return $query->whereIn('collegeId', $this->ids($colleges));
        }
        $explicitUnits = $actor->archiveUnitIds ?? [];
        $unitIds = in_array($actor->archiveResponsibility, ['department_head', 'department_secretary'], true) && $explicitUnits !== []
            ? $explicitUnits : array_merge([$actor->departmentId], $explicitUnits);
        return $query->whereIn('_id', $this->ids($unitIds));
    }

    public function documents(User $actor, bool $download = false)
    {
        $query = ArchiveDocument::query();
        $hidden = app(QmsFolderAccess::class)->hiddenIds($actor);
        if ($hidden !== []) { $query->whereNotIn('categoryId', array_merge($this->ids($hidden), array_map('strval', $hidden))); }
        if (!$actor->isActive || !in_array((int) $actor->roleId, [0, 1, 2, 3], true)) { return $query->whereRaw(['_id' => null]); }
        if ((int) $actor->roleId === 0) { return $query->whereRaw(['_id' => null]); }
        $units = $this->organizationalUnits($actor, true)->get(['_id']);
        $unitIds = $this->ids($units->map(fn ($unit) => $unit->getKey())->all());
        $unitValues = array_merge($unitIds, array_map('strval', $unitIds)); // Existing legacy references may be strings.
        if ($this->isAuditTeamLeader($actor)) {
            $query->whereRaw(['departmentId' => ['$in' => $unitValues], 'ownershipVerified' => true]);
            if ($download) { $query->where(fn ($q) => $q->where('recordType', 'file')->orWhereNull('recordType')); }
            return $query;
        }
        if ((int) $actor->roleId === 1) {
            // Reviewing an assigned audit grants its explicitly shared attachments, not the department repository.
            $cetUnits = Department::where('collegeName', 'CET')->where('unitType', 'academic_department')->where('ownershipVerified', true)->where('isActive', true)->get(['_id']);
            $cetIds = $this->ids($cetUnits->map(fn ($unit) => $unit->getKey())->all());
            $reviews = Audit::whereIn('reviewerId', [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()])->whereIn('departmentId', array_merge($cetIds, array_map('strval', $cetIds)))->get(['_id']);
            $auditIds = $this->ids($reviews->map(fn ($audit) => $audit->getKey())->all());
            $actorIds = [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()];
            $scope = ['$or' => [
                ['$and' => [['departmentId' => ['$in' => $unitValues]], ['$or' => [['uploadedByUserId' => ['$in' => $actorIds]], ['accessUserIds' => ['$in' => $actorIds]], ['visibility' => ['$in' => ['department', 'college']]]]]]],
                ['$and' => [['departmentId' => ['$in' => array_merge($cetIds, array_map('strval', $cetIds))]], ['sharedAuditIds' => ['$in' => array_merge($auditIds, array_map('strval', $auditIds))]]]],
            ]];
            $conditions = [$scope, ['ownershipVerified' => true]];
            if ($download) { $conditions[] = ['downloadAllowed' => ['$ne' => false]]; $conditions[] = ['$or' => [['recordType' => 'file'], ['recordType' => ['$exists' => false]]]]; }
            return $query->whereRaw(['$and' => $conditions]);
        }
        $conditions = [['departmentId' => ['$in' => $unitValues]], ['ownershipVerified' => true]];
        if ($download) {
            $conditions[] = ['downloadAllowed' => ['$ne' => false]];
            $conditions[] = ['$or' => [['recordType' => 'file'], ['recordType' => ['$exists' => false]]]];
        }
        if ((int) $actor->roleId === 2) {
            [$archiveIds, $auditIds] = $this->auditEvidence($actor);
            $conditions[] = ['$or' => [['_id' => ['$in' => $archiveIds]], ['sharedAuditIds' => ['$in' => array_merge($auditIds, array_map('strval', $auditIds))]]]];
        } elseif ((int) $actor->roleId !== 0) {
            $userIds = [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()];
            $conditions[] = ['$or' => [
                ['uploadedByUserId' => ['$in' => $userIds]],
                ['accessUserIds' => ['$in' => $userIds]],
                ['visibility' => ['$in' => ['department', 'college']]],
            ]];
        }
        return $query->whereRaw(['$and' => $conditions]);
    }

    // Aggregate reporting does not grant document listing, metadata, or file access.
    public function summaryDocuments(User $actor)
    {
        if (!$actor->isActive || (int) $actor->roleId !== 0) { return $this->documents($actor); }
        $units = $this->organizationalUnits($actor, true)->get(['_id']);
        $ids = $this->ids($units->map(fn ($unit) => $unit->getKey())->all());
        return ArchiveDocument::whereRaw(['departmentId' => ['$in' => array_merge($ids, array_map('strval', $ids))], 'ownershipVerified' => true]);
    }

    private function auditEvidence(User $actor): array
    {
        $departments = $this->ids($actor->assignedDepartmentIds ?? []);
        $departments = array_merge($departments, array_map('strval', $departments));
        $actorIds = [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()];
        $archives = ComplianceCheck::whereIn('auditorId', $actorIds)->whereIn('departmentId', $departments)
            ->get(['linkedArchiveIds'])->flatMap(fn ($check) => $check->linkedArchiveIds ?? [])->all();
        $audits = Audit::whereIn('auditorId', $actorIds)->whereIn('departmentId', $departments)->get(['items', 'evidenceRequests']);
        foreach ($audits as $audit) {
            foreach ($audit->items ?? [] as $item) {
                foreach ($item['evidence'] ?? [] as $evidence) { if (!empty($evidence['archiveId'])) { $archives[] = $evidence['archiveId']; } }
            }
            foreach ($audit->evidenceRequests ?? [] as $request) {
                // Drafts are not shared evidence; only submitted responses are available to the assigned auditor.
                foreach ($request['responses'] ?? [] as $response) { if (!empty($response['archiveId'])) { $archives[] = $response['archiveId']; } }
            }
        }
        return [$this->ids($archives), $this->ids($audits->map(fn ($audit) => $audit->getKey())->all())];
    }

    public function referencedEvidenceIds(User $actor, ?string $auditId = null): array
    {
        $units = $this->organizationalUnits($actor, true)->get(['_id'])->map(fn ($unit) => $unit->getKey())->all();
        $audits = Audit::whereIn('departmentId', array_merge($this->ids($units), array_map('strval', $units)));
        if ($auditId) { $audits->where('_id', new ObjectId($auditId)); }
        if ((int) $actor->roleId === 2) {
            $audits->whereIn('auditorId', [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()]);
        }
        $ids = [];
        foreach ($audits->get(['items', 'evidenceRequests']) as $audit) {
            foreach ($audit->items ?? [] as $item) {
                foreach ($item['evidence'] ?? [] as $evidence) { if (!empty($evidence['archiveId'])) { $ids[] = $evidence['archiveId']; } }
            }
            foreach ($audit->evidenceRequests ?? [] as $request) {
                foreach ($request['responses'] ?? [] as $response) { if (!empty($response['archiveId'])) { $ids[] = $response['archiveId']; } }
            }
        }
        return $this->ids($ids);
    }

    public function canCreate(User $actor, Department $unit): bool
    {
        if (!$unit->ownershipVerified || !$unit->collegeId || !$this->isAuditTeamLeader($actor)) { return false; }
        return $this->organizationalUnits($actor, true)->where('_id', $unit->getKey())->exists();
    }

    public function canManage(User $actor, ArchiveDocument $document): bool
    {
        return $this->isAuditTeamLeader($actor)
            && $this->documents($actor)->where('_id', $document->getKey())->exists();
    }
}
