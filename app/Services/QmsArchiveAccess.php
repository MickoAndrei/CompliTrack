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
            $units = $this->ids(app(QmsRepositoryPolicy::class)->units()->get(['_id'])->map(fn ($unit) => $unit->getKey())->all());
            $attributes->set($key, Audit::whereIn('reviewerId', [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()])
                ->whereIn('departmentId', array_merge($units, array_map('strval', $units)))->exists());
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
        if ($this->isAuditTeamLeader($actor)) { return app(QmsRepositoryPolicy::class)->units(); }
        if ((int) $actor->roleId === 0) { return $query; }
        if ((int) $actor->roleId === 2) {
            return $query->whereIn('_id', $this->ids(array_merge($actor->assignedDepartmentIds ?? [], $actor->archiveResponsibility === 'department_head' ? (($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]) : [])));
        }
        if (in_array($actor->archiveResponsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true)) {
            $colleges = \App\Models\College::whereIn('_id', $this->ids($actor->archiveCollegeIds ?? []))->where('isActive', true)->get(['_id'])->map(fn ($college) => $college->getKey())->all();
            return $query->whereIn('collegeId', $this->ids($colleges));
        }
        $explicitUnits = $actor->archiveUnitIds ?? [];
        $unitIds = in_array($actor->archiveResponsibility, ['department_head'], true) && $explicitUnits !== []
            ? $explicitUnits : array_merge([$actor->departmentId], $explicitUnits);
        return $query->whereIn('_id', $this->ids($unitIds));
    }

    public function documents(User $actor, bool $download = false)
    {
        $query = ArchiveDocument::whereNull('deletedAt');
        $hidden = app(QmsFolderAccess::class)->hiddenIds($actor);
        if ($hidden !== []) { $query->whereNotIn('categoryId', array_merge($this->ids($hidden), array_map('strval', $hidden))); }
        if (!$actor->isActive || !in_array((int) $actor->roleId, [0, 1, 2, 3], true)) { return $query->whereRaw(['_id' => null]); }
        if ((int) $actor->roleId === 0) { return $query->whereRaw(['_id' => null]); }
        $units = $this->organizationalUnits($actor, true)->get(['_id']);
        $unitIds = $this->ids($units->map(fn ($unit) => $unit->getKey())->all());
        $unitValues = array_merge($unitIds, array_map('strval', $unitIds)); // Existing legacy references may be strings.
        if ($this->isAuditTeamLeader($actor)) {
            $cetIds = $this->ids(app(QmsRepositoryPolicy::class)->units()->get(['_id'])->map(fn ($unit) => $unit->getKey())->all());
            $unitValues = array_merge($cetIds, array_map('strval', $cetIds));
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
            $branches = [['_id' => ['$in' => $archiveIds]], ['sharedAuditIds' => ['$in' => array_merge($auditIds, array_map('strval', $auditIds))]]];
            $ownIds = [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()];
            $notes = \App\Models\QmsCategory::where('folderPolicy', 'notes')->get()->filter(fn ($f) => app(QmsRepositoryPolicy::class)->permissions($actor, $f)['view'])->pluck('_id')->all();
            $branches[] = ['categoryId' => ['$in' => $this->ids($notes)]];
            if ($actor->archiveResponsibility === 'department_head') {
                $ownUnits = $this->ids(($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]);
                $branches[] = ['$and' => [['departmentId' => ['$in' => array_merge($ownUnits, array_map('strval', $ownUnits))]], ['$or' => [
                    ['visibility' => ['$in' => ['department', 'college']]], ['uploadedByUserId' => ['$in' => $ownIds]], ['accessUserIds' => ['$in' => $ownIds]],
                ]]]];
            }
            $conditions[] = ['$or' => $branches];
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
        return ArchiveDocument::whereNull('deletedAt')->whereRaw(['departmentId' => ['$in' => array_merge($ids, array_map('strval', $ids))], 'ownershipVerified' => true]);
    }

    private function auditEvidence(User $actor): array
    {
        $departments = $this->ids($actor->assignedDepartmentIds ?? []);
        $departments = array_merge($departments, array_map('strval', $departments));
        $actorIds = [new ObjectId((string) $actor->getKey()), (string) $actor->getKey()];
        $audits = Audit::whereIn('auditorId', $actorIds)->whereIn('departmentId', $departments)->where('status', '!=', 'cancelled')->get(['items', 'evidenceRequests']);
        $currentIds = $this->ids($audits->map(fn ($audit) => $audit->getKey())->all());
        $archives = ComplianceCheck::whereIn('auditorId', $actorIds)->whereIn('departmentId', $departments)
            ->where(fn ($query) => $query->whereIn('sourceAuditId', array_merge($currentIds, array_map('strval', $currentIds)))
                ->orWhereNull('sourceAuditId')) // Existing standalone CARs use their own explicit current auditor binding.
            ->get(['linkedArchiveIds'])->flatMap(fn ($check) => $check->linkedArchiveIds ?? [])->all();
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
        return app(QmsFolderAccess::class)->permissions($actor, $unit)['upload'];
    }

    public function canManage(User $actor, ArchiveDocument $document, bool $visibleChecked = false): bool
    {
        if (!$visibleChecked && !$this->documents($actor)->where('_id', $document->getKey())->exists()) { return false; }
        if ($this->isAuditTeamLeader($actor)) { return true; }
        $unit = app(QmsRequestReferences::class)->find(Department::class, $document->departmentId);
        $folder = $document->categoryId ? app(QmsRequestReferences::class)->find(\App\Models\QmsCategory::class, $document->categoryId) : null;
        return $unit && (in_array($actor->archiveResponsibility, ['department_head', 'college_secretary', 'college_dean', 'cluster_administrator'], true) || $folder?->folderPolicy === 'notes')
            && app(QmsFolderAccess::class)->permissions($actor, $unit, $folder)['upload'];
    }
}
