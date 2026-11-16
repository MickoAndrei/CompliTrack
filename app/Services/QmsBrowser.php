<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Audit, College, Department, QmsCategory, User};

/** Presentation and view narrowing; never replaces the authorized base query. */
class QmsBrowser
{
    public function departmentIds(User $actor): array
    {
        $ids = $actor->archiveResponsibility === 'department_head'
            ? (($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]) : [$actor->departmentId];
        return array_values(array_filter(array_unique(array_map('strval', $ids))));
    }

    public function departmentUnits(User $actor)
    {
        $query = app(QmsArchiveAccess::class)->organizationalUnits($actor, true);
        $ids = $this->departmentIds($actor);
        if ($ids !== [] || !app(QmsArchiveAccess::class)->isAuditTeamLeader($actor)) {
            $query->whereIn('_id', app(QmsArchiveAccess::class)->ids($ids));
        }
        return $query;
    }

    public function folderMetadata($folder, User $actor): array
    {
        $owner = $folder instanceof Department ? $folder->departmentName : null;
        $references = app(QmsRequestReferences::class);
        if (!$owner && $folder->departmentId) { $owner = $references->find(Department::class, $folder->departmentId)?->departmentName; }
        if (!$owner && $folder->createdByUserId) { $owner = $references->find(User::class, $folder->createdByUserId)?->fullName; }
        if (!$owner && $folder->collegeId) { $owner = $references->find(College::class, $folder->collegeId)?->collegeName; }
        return ['ownerName' => $owner, 'createdAt' => $folder->createdAt?->toIso8601String(),
            'updatedAt' => $folder->updatedAt?->toIso8601String(),
            'auditStatus' => $folder->auditAssignmentId ? $references->find(Audit::class, $folder->auditAssignmentId)?->status : null,
            'isStarred' => in_array((string) $folder->getKey(), array_map('strval', $actor->qmsStarredFolderIds ?? []), true)];
    }

    public function auditFolderIds(array $folders): array
    {
        $ids = array_column(array_filter($folders, fn ($f) => !empty($f['auditRelated']) || in_array($f['folderPolicy'], ['administration', 'evidence', 'notes'], true) || !empty($f['auditAssignmentId'])), '_id');
        do {
            $before = count($ids);
            foreach ($folders as $folder) {
                if (in_array($folder['parentId'], $ids, true) && !in_array($folder['_id'], $ids, true)) { $ids[] = $folder['_id']; }
            }
        } while ($before !== count($ids));
        return $ids;
    }

    public function isAuditFolder(QmsCategory $folder): bool
    {
        $seen = [];
        while ($folder && !in_array((string) $folder->getKey(), $seen, true) && count($seen) < 12) {
            if (in_array($folder->folderPolicy, ['administration', 'evidence', 'notes'], true) || $folder->auditAssignmentId) { return true; }
            $seen[] = (string) $folder->getKey();
            $folder = $folder->parentId ? app(QmsRequestReferences::class)->find(QmsCategory::class, $folder->parentId) : null;
        }
        return false;
    }

    public function assignments(User $actor)
    {
        $ids = app(QmsArchiveAccess::class)->ids(app(QmsRepositoryPolicy::class)->units()->get(['_id'])->map(fn ($u) => $u->getKey())->all());
        return Audit::whereIn('departmentId', array_merge($ids, array_map('strval', $ids)))->where('status', '!=', 'cancelled')->get()
            ->filter(fn ($audit) => app(QmsArchiveAccess::class)->isAuditTeamLeader($actor) || app(QmsRepositoryPolicy::class)->assigned($actor, $audit))->values();
    }

    public function latest($query)
    {
        // Deduplicate by the recorded identity, never by title. An evidence-only user may
        // see a historical revision without gaining access to the newer private revision.
        $latest = (clone $query)->orderBy('versionNumber', 'desc')->orderBy('createdAt', 'desc')->orderBy('_id', 'desc')
            ->get(['_id', 'versionGroupId', 'versionNumber', 'createdAt'])->unique(fn ($doc) => (string) ($doc->versionGroupId ?: $doc->getKey()));
        return $query->whereIn('_id', $latest->map(fn ($doc) => $doc->getKey())->all());
    }

    public function starredGroups(User $actor): array
    {
        $saved = app(QmsArchiveAccess::class)->ids($actor->qmsStarredIds ?? []);
        return ArchiveDocument::whereIn('_id', $saved)->get(['_id', 'versionGroupId'])
            ->map(fn ($doc) => (string) ($doc->versionGroupId ?: $doc->getKey()))->unique()->values()->all();
    }

    public function folder(User $actor, string $id, ?string $unitId = null): array
    {
        $folder = QmsCategory::where('_id', \App\Support\MongoId::parse($id))->where('isActive', true)->firstOrFail();
        $unit = $folder->departmentId ? Department::find($folder->departmentId) : ($unitId ? Department::find(\App\Support\MongoId::parse($unitId)) : null);
        $permissions = $folder->folderPolicy ? app(QmsRepositoryPolicy::class)->permissions($actor, $folder)
            : ($unit ? app(QmsFolderAccess::class)->permissions($actor, $unit, $folder) : []);
        abort_unless($permissions['view'] ?? false, 404);
        return [$folder, $unit, $permissions];
    }
}
