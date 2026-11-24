<?php
namespace App\Services;

use App\Models\{Audit, College, Department, QmsCategory, User};
use App\Support\MongoId;

class QmsFolderAccess
{
    public const ACTIONS = ['view', 'create', 'rename', 'move', 'delete', 'manage', 'upload'];

    public function allowedVisibility(QmsCategory $folder): array
    {
        return match ($folder->classification ?: 'department') {
            'restricted' => ['restricted', 'owner'], 'department' => ['restricted', 'owner', 'department'],
            'college' => ['restricted', 'owner', 'department', 'college'], default => [],
        };
    }

    public function units(User $actor)
    {
        $query = Department::where('isActive', true)->where('ownershipVerified', true)->where('unitType', '!=', 'legacy_unclassified');
        $access = app(QmsArchiveAccess::class);
        if (!$actor->isActive || !in_array((int) $actor->roleId, [0, 1, 2, 3], true)) { return $query->whereRaw(['_id' => null]); }
        if ($access->isAuditTeamLeader($actor)) { return app(QmsRepositoryPolicy::class)->units(); }
        $query->whereIn('collegeId', $access->ids(College::where('isActive', true)->get(['_id'])->map(fn ($college) => $college->getKey())->all()));
        if ($actor->archiveResponsibility === 'cluster_administrator') {
            $colleges = College::where('isActive', true);
            if ($actor->archiveCluster) {
                $colleges->where(fn ($q) => $q->where('clusterId', $actor->archiveCluster)
                    ->orWhereIn('campusName', config('qms.clusterCampuses.'.$actor->archiveCluster, [])));
            } else { $colleges->whereIn('_id', $access->ids($actor->archiveCollegeIds ?? [])); }
            return $query->whereIn('collegeId', $access->ids($colleges->get(['_id'])->map(fn ($college) => $college->getKey())->all()));
        }
        if (in_array($actor->archiveResponsibility, ['college_dean', 'college_secretary'], true)) {
            return $query->whereIn('collegeId', $access->ids(College::whereIn('_id', $access->ids($actor->archiveCollegeIds ?? []))->where('isActive', true)->get(['_id'])->map(fn ($college) => $college->getKey())->all()));
        }
        if ((int) $actor->roleId === 0) { return $query->whereRaw(['_id' => null]); }
        if (in_array($actor->archiveResponsibility, ['department_head'], true)) {
            if ((int) $actor->roleId === 2) { return $query->whereIn('_id', $access->ids(array_merge(($actor->archiveUnitIds ?? []) ?: [$actor->departmentId], $actor->assignedDepartmentIds ?? []))); }
            return $query->whereIn('_id', $access->ids(($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]));
        }
        if ((int) $actor->roleId === 1) {
            return $query->whereIn('_id', $access->ids(Audit::whereIn('reviewerId', [MongoId::parse((string) $actor->getKey()), (string) $actor->getKey()])
                ->whereNotIn('status', ['accepted', 'cancelled', 'archived'])->pluck('departmentId')->all()));
        }
        if ((int) $actor->roleId === 2) { return $query->whereIn('_id', $access->ids($actor->assignedDepartmentIds ?? [])); }
        $explicit = $actor->archiveUnitIds ?? [];
        $ids = in_array($actor->archiveResponsibility, ['department_head'], true) && $explicit !== [] ? $explicit : [$actor->departmentId];
        return $query->whereIn('_id', $access->ids($ids));
    }

    public function permissions(User $actor, Department $unit, ?QmsCategory $folder = null): array
    {
        if ($folder?->folderPolicy) {
            if ($folder->folderPolicy === 'administration') {
                return array_fill_keys(self::ACTIONS, app(QmsRepositoryPolicy::class)->leader($actor)
                    && (string) $unit->collegeId === (string) $folder->collegeId && $folder->isActive);
            }
            if ((string) $folder->departmentId !== (string) $unit->getKey()) { return array_fill_keys(self::ACTIONS, false); }
            return app(QmsRepositoryPolicy::class)->permissions($actor, $folder);
        }
        if (app(QmsArchiveAccess::class)->isAuditTeamLeader($actor) && app(QmsRepositoryPolicy::class)->unit((string) $unit->getKey())) {
            $matches = !$folder || ($folder->isActive && (string) $folder->collegeId === (string) $unit->collegeId && $folder->unitType === $unit->unitType
                && (!$folder->userManaged || (string) $folder->departmentId === (string) $unit->getKey()));
            $permissions = array_fill_keys(self::ACTIONS, $matches);
            if (!$folder?->userManaged) { foreach (['rename', 'move', 'delete', 'manage'] as $action) { $permissions[$action] = false; } }
            return $permissions;
        }
        $key = 'qms.folder.scope.'.(string) $actor->getKey();
        if (!request()->attributes->has($key)) { request()->attributes->set($key, $this->units($actor)->get(['_id'])->map(fn ($unit) => (string) $unit->getKey())->all()); }
        $scope = in_array((string) $unit->getKey(), request()->attributes->get($key), true);
        $matches = !$folder || ($folder->isActive && (string) $folder->collegeId === (string) $unit->collegeId
            && $folder->unitType === $unit->unitType && (!$folder->userManaged || (string) $folder->departmentId === (string) $unit->getKey()));
        $grant = collect($actor->qmsFolderGrants ?? [])->first(fn ($grant) => (string) ($grant['unitId'] ?? '') === (string) $unit->getKey());
        $classification = $folder?->classification ?: 'department';
        $classified = $classification === 'department' || ($grant && in_array($classification, $grant['classifications'] ?? [], true));
        $manager = in_array($actor->archiveResponsibility, ['department_head', 'college_secretary', 'cluster_administrator'], true)
            || ((int) $actor->roleId === 1 && $actor->archiveResponsibility !== 'college_dean');
        if ((int) $actor->roleId === 2) { $manager = $actor->archiveResponsibility === 'department_head' && app(QmsRepositoryPolicy::class)->departmentMember($actor, (string) $unit->getKey()); }
        $actions = $grant ? ($grant['actions'] ?? []) : ($manager ? self::ACTIONS : ['view']);
        if (((int) $actor->roleId === 2 && !$manager) || ((int) $actor->roleId === 3 && !in_array($actor->archiveResponsibility, ['department_head', 'college_secretary', 'college_dean', 'cluster_administrator'], true))) {
            $actions = array_intersect($actions, $grant && (int) $actor->roleId === 3 ? ['view', 'upload'] : ['view']);
        }
        $view = $scope && $matches && $classified && in_array('view', $actions, true);
        $permissions = array_fill_keys(self::ACTIONS, false);
        foreach (self::ACTIONS as $action) { $permissions[$action] = $view && in_array($action, $actions, true); }
        $permissions['view'] = $view;
        if ((int) $actor->roleId === 0) { $permissions['upload'] = false; }
        // Organizational and configured QMS nodes are immutable through folder-management APIs.
        if (!$folder?->userManaged) { foreach (['rename', 'move', 'delete', 'manage'] as $action) { $permissions[$action] = false; } }
        // Finding authorization permits corrective files at a visible department root only.
        // It does not grant general folder uploads or access to nested folders.
        if (!$folder && $view && in_array((int) $actor->roleId, [2, 3], true)) {
            $key = 'qms.corrective.units.'.(string) $actor->getKey();
            if (!request()->attributes->has($key)) {
                $ids = app(RoleScopeService::class)->checks($actor)->where('result', 'non_compliant')
                    ->whereIn('correctiveStatus', \App\Support\CarStatus::storedValues(['open', 'in_progress', 'returned_for_revision']))->pluck('departmentId')->map(fn ($id) => (string) $id)->unique()->all();
                request()->attributes->set($key, $ids);
            }
            $permissions['correctiveUpload'] = in_array((string) $unit->getKey(), request()->attributes->get($key), true);
        }
        return $permissions;
    }

    public function parent(Department $unit, ?string $id): ?QmsCategory
    {
        if (!$id) { return null; }
        return QmsCategory::where('_id', MongoId::parse($id))->where('isActive', true)->where('collegeId', MongoId::parse((string) $unit->collegeId))
            ->where('unitType', $unit->unitType)->where(fn ($q) => $q->where('userManaged', '!=', true)->orWhere('departmentId', MongoId::parse((string) $unit->getKey())))->firstOrFail();
    }

    public function hiddenIds(User $actor): array
    {
        $key = 'qms.folder.hidden.'.(string) $actor->getKey();
        if (request()->attributes->has($key)) { return request()->attributes->get($key); }
        $ids = [];
        $folders = QmsCategory::where('userManaged', true)->get();
        app(QmsRequestReferences::class)->remember($folders);
        app(QmsRequestReferences::class)->prime(Audit::class, $folders->pluck('auditAssignmentId')->all());
        $units = Department::whereIn('_id', app(QmsArchiveAccess::class)->ids($folders->pluck('departmentId')->all()))->get()->keyBy(fn ($unit) => (string) $unit->getKey());
        foreach ($folders as $folder) {
            if ($folder->folderPolicy) {
                if (!app(QmsRepositoryPolicy::class)->permissions($actor, $folder)['view']) { $ids[] = $folder->getKey(); }
                continue;
            }
            $unit = $units->get((string) $folder->departmentId);
            if (!$unit || !$this->permissions($actor, $unit, $folder)['view']) { $ids[] = $folder->getKey(); }
        }
        request()->attributes->set($key, $ids);
        return $ids;
    }
}
