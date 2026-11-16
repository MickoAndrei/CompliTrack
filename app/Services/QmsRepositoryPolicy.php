<?php
namespace App\Services;

use App\Models\{Audit, College, Department, QmsCategory, User};

/** Access to manually organized folders. Labels never participate in authorization. */
class QmsRepositoryPolicy
{
    public const POLICIES = ['department', 'evidence', 'notes', 'administration'];

    public function college(): ?College
    {
        $attributes = request()->attributes;
        if (!$attributes->has('qms.repository.college')) {
            $attributes->set('qms.repository.college', College::where('collegeCode', 'CET')->where('isActive', true)
                ->whereIn('campusName', ['Claveria', 'USTP Claveria Campus'])->first());
        }
        return $attributes->get('qms.repository.college');
    }

    public function units()
    {
        $college = $this->college();
        if (!$college) { return Department::whereRaw(['_id' => null]); }
        return Department::where('collegeId', $college ? \App\Support\MongoId::parse((string) $college->getKey()) : null)->where('ownershipVerified', true)
            ->where('isActive', true)->whereIn('unitType', ['academic_department', 'college_office']);
    }

    public function leader(User $actor): bool
    {
        return app(QmsArchiveAccess::class)->isAuditTeamLeader($actor);
    }

    public function unit(string $id): ?Department
    {
        $key = 'qms.repository.units';
        if (!request()->attributes->has($key)) {
            request()->attributes->set($key, $this->units()->get()->keyBy(fn ($unit) => (string) $unit->getKey()));
        }
        return request()->attributes->get($key)->get($id);
    }

    public function departmentMember(User $actor, string $unitId): bool
    {
        if (!$actor->isActive || (int) $actor->roleId === 0) { return false; }
        $ids = $actor->archiveResponsibility === 'department_head'
            ? (($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]) : [$actor->departmentId];
        return in_array($unitId, array_map('strval', $ids), true)
            && ((int) $actor->roleId === 3 || $actor->archiveResponsibility === 'department_head');
    }

    public function assignment(User $actor, ?string $id): ?Audit
    {
        if (!$id || !preg_match('/^[a-f0-9]{24}$/i', $id)) { return null; }
        $audit = app(QmsRequestReferences::class)->find(Audit::class, $id);
        if (!$audit || !$this->unit((string) $audit->departmentId)
            || in_array($audit->status, ['cancelled'], true)) { return null; }
        return $audit;
    }

    public function assigned(User $actor, Audit $audit): bool
    {
        return $actor->isActive && (string) $audit->auditorId === (string) $actor->getKey()
            && in_array((string) $audit->departmentId, array_map('strval', $actor->assignedDepartmentIds ?? []), true);
    }

    public function permissions(User $actor, QmsCategory $folder): array
    {
        $permissions = array_fill_keys(QmsFolderAccess::ACTIONS, false);
        if (!$actor->isActive || !$folder->isActive || (string) $folder->collegeId !== (string) $this->college()?->getKey()) { return $permissions; }
        if ($this->leader($actor)) { return array_fill_keys(QmsFolderAccess::ACTIONS, true); }
        $unit = $folder->departmentId ? $this->unit((string) $folder->departmentId) : null;
        if (!$unit) { return $permissions; }
        $member = $this->departmentMember($actor, (string) $unit->getKey());
        $manager = $member && $actor->archiveResponsibility === 'department_head';
        if ($folder->folderPolicy === 'department') {
            $permissions['view'] = $member;
            foreach (QmsFolderAccess::ACTIONS as $action) { if ($action !== 'view') { $permissions[$action] = $manager; } }
            // Explicit grants may authorize uploads by department auditees.
            $grant = collect($actor->qmsFolderGrants ?? [])->first(fn ($g) => (string) ($g['unitId'] ?? '') === (string) $unit->getKey());
            if ($grant) { foreach ($permissions as $action => $allowed) { $permissions[$action] = $member && in_array($action, $grant['actions'] ?? [], true); } }
        } elseif (in_array($folder->folderPolicy, ['evidence', 'notes'], true)) {
            $audit = $this->assignment($actor, (string) $folder->auditAssignmentId);
            if (!$audit || (string) $audit->departmentId !== (string) $folder->departmentId
                || (string) $audit->auditorId !== (string) $folder->auditorId) { return $permissions; }
            $assigned = $this->assigned($actor, $audit);
            if ($folder->folderPolicy === 'notes') {
                return array_fill_keys(QmsFolderAccess::ACTIONS, $assigned);
            }
            $permissions['view'] = $member || $assigned;
            foreach (['create', 'rename', 'move', 'delete', 'manage', 'upload'] as $action) { $permissions[$action] = $manager; }
            $grant = collect($actor->qmsFolderGrants ?? [])->first(fn ($g) => (string) ($g['unitId'] ?? '') === (string) $unit->getKey());
            if ($member && $grant) { $permissions['upload'] = in_array('upload', $grant['actions'] ?? [], true) && in_array('view', $grant['actions'] ?? [], true); }
        }
        return $permissions;
    }

    public function options(User $actor): array
    {
        $leader = $this->leader($actor);
        $units = $this->units()->get()->filter(fn ($unit) => $leader || ($this->departmentMember($actor, (string) $unit->getKey()) && $actor->archiveResponsibility === 'department_head'));
        $audits = Audit::whereIn('departmentId', app(QmsArchiveAccess::class)->ids($this->units()->get(['_id'])->map(fn ($unit) => $unit->getKey())->all()))->where('status', '!=', 'cancelled')->get()
            ->filter(fn ($audit) => $leader || $this->assigned($actor, $audit) || $units->contains(fn ($u) => (string) $u->getKey() === (string) $audit->departmentId));
        return ['leader' => $leader, 'units' => $units->map(fn ($u) => ['_id' => (string) $u->getKey(), 'name' => $u->departmentName])->values()->all(),
            'assignments' => $audits->map(fn ($a) => ['_id' => (string) $a->getKey(), 'name' => $a->title ?: $a->auditCode ?: 'Audit assignment',
                'unitId' => (string) $a->departmentId, 'auditorId' => (string) $a->auditorId, 'canNotes' => $leader || $this->assigned($actor, $a)])->values()->all()];
    }

    public function navigation(User $actor): array
    {
        // Virtual roots expose authorized descendants without returning confidential ancestors or siblings.
        $folders = QmsCategory::whereNotNull('folderPolicy')->where('isActive', true)->get();
        app(QmsRequestReferences::class)->remember($folders);
        app(QmsRequestReferences::class)->prime(Audit::class, $folders->pluck('auditAssignmentId')->all());
        $folders = $folders->filter(fn ($folder) => $this->permissions($actor, $folder)['view']);
        $visible = $folders->map(fn ($f) => (string) $f->getKey())->all();
        $navigation = $folders->map(fn ($f) => ['_id' => (string) $f->getKey(), 'name' => $f->name, 'folderPolicy' => $f->folderPolicy,
            'parentId' => in_array((string) $f->parentId, $visible, true) ? (string) $f->parentId : null,
            'virtualRoot' => $f->parentId && !in_array((string) $f->parentId, $visible, true),
            'collegeId' => (string) $f->collegeId, 'departmentId' => $f->departmentId ? (string) $f->departmentId : null,
            'auditAssignmentId' => $f->auditAssignmentId ? (string) $f->auditAssignmentId : null,
            'auditRelated' => app(QmsBrowser::class)->isAuditFolder($f),
            'permissions' => $this->permissions($actor, $f),
            ...app(QmsBrowser::class)->folderMetadata($f, $actor)])->values()->all();
        $auditIds = app(QmsBrowser::class)->auditFolderIds($navigation);
        return array_map(fn ($folder) => [...$folder, 'auditRelated' => in_array($folder['_id'], $auditIds, true)], $navigation);
    }
}
