<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Audit, Campus, College, ComplianceCheck, Department, User};
use App\Support\MongoId;
use Illuminate\Http\Request;

class QmsAuditArchive
{
    public const PATH = ['planId', 'auditorId', 'campusId', 'collegeId', 'unitId'];

    public function applyContext($query, User $actor, Request $request)
    {
        if ($request->filled('planId') || $request->filled('auditorId') || $request->filled('campusId')) {
            $rules = array_fill_keys(self::PATH, 'required|string|regex:/^[a-f0-9]{24}$/i');
            $path = $request->validate($rules);
            [$rows, $depth] = $this->selection($actor, $path);
            abort_unless($depth === 5, 422, 'Open a department or office evidence folder first.');
            if ($request->filled('auditId')) { $rows = array_values(array_filter($rows, fn ($row) => (string) $row['audit']->getKey() === $request->query('auditId'))); }
            return $query->whereRaw($this->evidenceFilter(array_column($rows, 'audit')));
        }
        if ($request->filled('auditId')) {
            $request->validate(['auditId' => 'required|string|regex:/^[a-f0-9]{24}$/i']);
            $audit = $this->assignments($actor)->where('_id', MongoId::parse($request->query('auditId')))->firstOrFail();
            return $query->whereRaw($this->evidenceFilter([$audit]));
        }
        return $query;
    }

    public function assignments(User $actor)
    {
        $access = app(QmsArchiveAccess::class);
        $query = Audit::query();
        if (!$actor->isActive || !in_array((int) $actor->roleId, [0, 1, 2, 3], true)) { return $query->whereRaw(['_id' => null]); }
        $actorIds = [MongoId::parse((string) $actor->getKey()), (string) $actor->getKey()];
        $positions = ['department_head', 'department_secretary', 'college_dean', 'college_secretary', 'cluster_administrator'];
        if ((int) $actor->roleId === 1 && !in_array($actor->archiveResponsibility, $positions, true)) {
            $query->whereIn('reviewerId', $actorIds);
            $units = Department::where('isActive', true)->where('ownershipVerified', true);
        } elseif (in_array($actor->archiveResponsibility, ['department_head', 'department_secretary'], true) && (int) $actor->roleId !== 2) {
            $unitIds = ($actor->archiveUnitIds ?? []) ?: [$actor->departmentId];
            $units = Department::where('isActive', true)->where('ownershipVerified', true)->whereIn('_id', $access->ids($unitIds));
        } else {
            $units = app(QmsFolderAccess::class)->units($actor);
        }
        $activeColleges = College::where('isActive', true)->get(['_id'])->map(fn ($item) => $item->getKey())->all();
        $unitIds = $units->whereIn('collegeId', $access->ids($activeColleges))->get(['_id'])->map(fn ($unit) => $unit->getKey())->all();
        $ids = $access->ids($unitIds);
        $query->whereIn('departmentId', array_merge($ids, array_map('strval', $ids)));
        if ((int) $actor->roleId === 2) { $query->whereIn('auditorId', $actorIds); }
        return $query;
    }

    private function authorizationMatches(array $snapshot, array $authorization): bool
    {
        foreach (['status', 'approvedBy', 'approvedAt', 'approvalVersion', 'scope', 'criteria', 'scheduleStart', 'scheduleEnd', 'procedureReference'] as $field) {
            if ((string) ($snapshot[$field] ?? '') !== (string) ($authorization[$field] ?? '')) { return false; }
        }
        foreach (['unitIds', 'reviewerIds'] as $field) {
            $left = array_map('strval', $snapshot[$field] ?? []); $right = array_map('strval', $authorization[$field] ?? []);
            sort($left); sort($right); if ($left !== $right) { return false; }
        }
        return true;
    }

    private function approved(Audit $audit, ArchiveDocument $plan): bool
    {
        $snapshot = $audit->planAuthorizationSnapshot ?? [];
        if (!is_array($snapshot) || ($snapshot['status'] ?? '') !== 'authorized' || empty($snapshot['approvedBy']) || empty($snapshot['approvedAt'])
            || !in_array((string) $audit->departmentId, array_map('strval', $snapshot['unitIds'] ?? []), true)
            || !in_array((string) $audit->reviewerId, array_map('strval', $snapshot['reviewerIds'] ?? []), true)) { return false; }
        if (app(AuditAssignment::class)->complete($plan) && $this->authorizationMatches($snapshot, $plan->assignmentAuthorization ?? [])) { return true; }
        foreach (($plan->auditPlan ?? [])['history'] ?? [] as $event) {
            $previous = $event['previousAuthorization'] ?? [];
            if (($event['previousStatus'] ?? '') === 'accepted' && ($previous['status'] ?? '') === 'authorized'
                && $this->authorizationMatches($snapshot, $previous)) { return true; }
        }
        return false;
    }

    public function rows(User $actor): array
    {
        $key = 'qms.auditArchive.'.(string) $actor->getKey();
        if (request()->attributes->has($key)) { return request()->attributes->get($key); }
        $audits = $this->assignments($actor)->whereNotNull('planId')->get();
        $access = app(QmsArchiveAccess::class);
        $plans = ArchiveDocument::whereIn('_id', $access->ids($audits->pluck('planId')->all()))->where('ownershipVerified', true)->get()->keyBy(fn ($item) => (string) $item->getKey());
        $units = Department::whereIn('_id', $access->ids($audits->pluck('departmentId')->all()))->get()->keyBy(fn ($item) => (string) $item->getKey());
        $colleges = College::whereIn('_id', $access->ids($units->pluck('collegeId')->all()))->get()->keyBy(fn ($item) => (string) $item->getKey());
        $campuses = Campus::whereIn('_id', $access->ids($colleges->pluck('campusId')->all()))->where('isActive', true)->get()->keyBy(fn ($item) => (string) $item->getKey());
        $people = User::whereIn('_id', $access->ids($audits->pluck('auditorId')->all()))->get()->keyBy(fn ($item) => (string) $item->getKey());
        $rows = [];
        foreach ($audits as $audit) {
            $plan = $plans->get((string) $audit->planId); $unit = $units->get((string) $audit->departmentId);
            $college = $unit ? $colleges->get((string) $unit->collegeId) : null;
            $campus = $college ? $campuses->get((string) $college->campusId) : null;
            if (!$plan || !$unit || !$college || !$campus || !$this->approved($audit, $plan)) { continue; }
            if ($unit->campusId && (string) $unit->campusId !== (string) $campus->getKey()) { continue; }
            if ($unit->campusName && app(QmsCampusReferences::class)->officialName($unit->campusName) !== app(QmsCampusReferences::class)->officialName($college->campusName)) { continue; }
            $reference = $plan->documentReference ?: (($plan->sourceMetadata ?? [])['documentCode'] ?? $plan->fileCode);
            $title = $plan->title ?: $plan->originalName;
            if (!$title) { continue; } // Never substitute an assignment title or a generated season.
            $rows[] = ['audit' => $audit, 'planId' => (string) $plan->getKey(), 'auditorId' => (string) $audit->auditorId,
                'campusId' => (string) $campus->getKey(), 'collegeId' => (string) $college->getKey(), 'unitId' => (string) $unit->getKey(),
                'nodes' => [
                    ['_id' => (string) $plan->getKey(), 'name' => $title, 'reference' => $reference, 'kind' => 'Audit Plan'],
                    ['_id' => (string) $audit->auditorId, 'name' => $people->get((string) $audit->auditorId)?->fullName ?: 'Assigned auditor', 'kind' => 'Assigned Auditor'],
                    ['_id' => (string) $campus->getKey(), 'name' => $campus->campusName, 'kind' => 'Campus'],
                    ['_id' => (string) $college->getKey(), 'name' => $college->collegeName, 'kind' => 'College'],
                    ['_id' => (string) $unit->getKey(), 'name' => $unit->departmentName, 'kind' => $unit->unitType === 'college_office' ? 'Office' : 'Department'],
                ]];
        }
        request()->attributes->set($key, $rows);
        return $rows;
    }

    public function selection(User $actor, array $path): array
    {
        $rows = $this->rows($actor); $depth = 0; $missing = false;
        foreach (self::PATH as $field) {
            if (empty($path[$field])) { $missing = true; continue; }
            abort_if($missing, 422, 'Choose the parent archive folder first.');
            $rows = array_values(array_filter($rows, fn ($row) => $row[$field] === $path[$field]));
            abort_if($rows === [], 404, 'This audit archive path is unavailable.');
            $depth++;
        }
        return [$rows, $depth];
    }

    public function tree(User $actor, array $path): array
    {
        [$rows, $depth] = $this->selection($actor, $path);
        $breadcrumbs = [['key' => 'audit-root', 'name' => 'Audit Plans', 'path' => []]]; $parent = [];
        for ($i = 0; $i < $depth; $i++) {
            $parent[self::PATH[$i]] = $rows[0][self::PATH[$i]];
            $node = $rows[0]['nodes'][$i];
            $breadcrumbs[] = ['key' => self::PATH[$i].':'.$node['_id'], 'name' => $node['name'], 'path' => $parent];
        }
        $folders = [];
        if ($depth < 5) {
            foreach ($rows as $row) {
                $node = $row['nodes'][$depth];
                $folders[$node['_id']] = $node + ['path' => $parent + [self::PATH[$depth] => $node['_id']], 'systemManaged' => true];
            }
        }
        return ['folders' => array_values($folders), 'breadcrumbs' => $breadcrumbs, 'leaf' => $depth === 5,
            'notice' => $rows === [] ? 'No authorized Audit Plan assignments are available. Evidence without a verified plan reference remains in My Documents.' : null];
    }

    public function evidenceFilter(array $audits): array
    {
        $branches = []; $access = app(QmsArchiveAccess::class);
        foreach ($audits as $audit) {
            $references = [];
            foreach ($audit->items ?? [] as $item) {
                foreach ($item['evidence'] ?? [] as $evidence) { if (!empty($evidence['archiveId'])) { $references[] = $evidence['archiveId']; } }
            }
            foreach ($audit->evidenceRequests ?? [] as $request) {
                foreach ($request['responses'] ?? [] as $response) { if (!empty($response['archiveId'])) { $references[] = $response['archiveId']; } }
            }
            $auditValues = [MongoId::parse((string) $audit->getKey()), (string) $audit->getKey()];
            $references = array_merge($references, ComplianceCheck::whereIn('sourceAuditId', $auditValues)->get(['linkedArchiveIds'])->flatMap(fn ($check) => $check->linkedArchiveIds ?? [])->all());
            $branches[] = ['$and' => [['departmentId' => ['$in' => [MongoId::parse((string) $audit->departmentId), (string) $audit->departmentId]]],
                ['$or' => [['sharedAuditIds' => ['$in' => $auditValues]], ['auditId' => ['$in' => $auditValues]], ['_id' => ['$in' => $access->ids($references)]]]]]];
        }
        return ['$or' => $branches ?: [['_id' => null]]];
    }
}
