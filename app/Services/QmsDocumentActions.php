<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, User};
use App\Support\MongoId;

class QmsDocumentActions
{
    /** Batch read-only hints for a page. Mutation endpoints always call capabilities() afresh. */
    public function presentationCapabilities($documents): array
    {
        $groups = $documents->map(fn ($d) => (string) ($d->versionGroupId ?: $d->getKey()))->unique()->all();
        if (!$groups) { return []; }
        $ids = app(QmsArchiveAccess::class)->ids($groups);
        $versions = ArchiveDocument::where(fn ($q) => $q->whereIn('versionGroupId', array_merge($ids, $groups))->orWhereIn('_id', $ids))
            ->orderBy('versionNumber', 'desc')->orderBy('createdAt', 'desc')
            ->get(['_id', 'versionGroupId', 'versionNumber', 'createdAt', 'deletedAt', 'auditId', 'sharedAuditIds', 'assignmentAuthorization', 'onlyofficeWorkspace']);
        $versionIds = $versions->map(fn ($d) => $d->getKey())->all();
        $values = array_merge($versionIds, array_map('strval', $versionIds));
        $referenced = [];
        $audits = Audit::whereRaw(['$or' => [['planId' => ['$in' => $values]], ['items.evidence.archiveId' => ['$in' => $values]],
            ['evidenceRequests.responses.archiveId' => ['$in' => $values]]]])
            ->get(['planId', 'items.evidence.archiveId', 'evidenceRequests.responses.archiveId']);
        foreach ($audits as $audit) {
            $referenced[] = (string) $audit->planId;
            foreach ($audit->items ?? [] as $item) {
                foreach ($item['evidence'] ?? [] as $evidence) { $referenced[] = (string) ($evidence['archiveId'] ?? ''); }
            }
            foreach ($audit->evidenceRequests ?? [] as $request) {
                foreach ($request['responses'] ?? [] as $response) { $referenced[] = (string) ($response['archiveId'] ?? ''); }
            }
        }
        foreach (ComplianceCheck::whereIn('linkedArchiveIds', $values)->get(['linkedArchiveIds']) as $check) {
            foreach ($check->linkedArchiveIds ?? [] as $id) { $referenced[] = (string) $id; }
        }
        $references = array_fill_keys($referenced, true); $latest = []; $protected = [];
        foreach ($versions as $version) {
            $group = (string) ($version->versionGroupId ?: $version->getKey());
            if (!$version->deletedAt) { $latest[$group] ??= (string) $version->getKey(); }
            $workspace = $version->onlyofficeWorkspace ?? [];
            if ($version->auditId || !empty($version->sharedAuditIds) || !empty($version->assignmentAuthorization)
                || (!empty($workspace) && !($workspace['closed'] ?? true) && ($workspace['expires'] ?? 0) > time())
                || isset($references[(string) $version->getKey()])) { $protected[$group] = true; }
        }
        return $documents->mapWithKeys(function ($document) use ($latest, $protected) {
            $group = (string) ($document->versionGroupId ?: $document->getKey());
            $mutable = ($latest[$group] ?? null) === (string) $document->getKey() && !isset($protected[$group]);
            return [(string) $document->getKey() => ['canRename' => $mutable, 'canMove' => $mutable, 'canDelete' => $mutable]];
        })->all();
    }

    public function chain(ArchiveDocument $document)
    {
        $group = MongoId::parse((string) ($document->versionGroupId ?: $document->getKey()));
        return ArchiveDocument::where(fn ($q) => $q->whereIn('versionGroupId', [$group, (string) $group])->orWhere('_id', $group));
    }

    public function protected(ArchiveDocument $document): bool
    {
        $chain = $this->chain($document)->get();
        if ($chain->contains(fn ($d) => $d->auditId || !empty($d->sharedAuditIds) || !empty($d->assignmentAuthorization)
            || (!empty($d->onlyofficeWorkspace) && !($d->onlyofficeWorkspace['closed'] ?? true) && ($d->onlyofficeWorkspace['expires'] ?? 0) > time()))) { return true; }
        $ids = $chain->map(fn ($d) => $d->getKey())->all(); $values = array_merge($ids, array_map('strval', $ids));
        return Audit::whereRaw(['$or' => [['planId' => ['$in' => $values]], ['items.evidence.archiveId' => ['$in' => $values]],
            ['evidenceRequests.responses.archiveId' => ['$in' => $values]]]])->exists()
            || ComplianceCheck::whereIn('linkedArchiveIds', $values)->exists();
    }

    public function capabilities(User $actor, ArchiveDocument $document, ?bool $manage = null): array
    {
        $manage ??= app(QmsArchiveAccess::class)->canManage($actor, $document);
        if (!$manage) { return ['canRename' => false, 'canMove' => false, 'canDelete' => false]; }
        $latest = (string) $this->chain($document)->whereNull('deletedAt')->orderBy('versionNumber', 'desc')->orderBy('createdAt', 'desc')->first()?->getKey() === (string) $document->getKey();
        $mutable = $manage && $latest && !$this->protected($document);
        return ['canRename' => $mutable, 'canMove' => $mutable, 'canDelete' => $mutable];
    }

    public function lock(ArchiveDocument $document): void
    {
        $anchor = $this->chain($document)->orderBy('versionNumber')->orderBy('createdAt')->firstOrFail();
        ArchiveDocument::where('_id', $anchor->getKey())->increment('qmsMutationRevision');
        // A later writer shares this anchor with ONLYOFFICE; MongoDB retries conflicting transactions.
        $document->refresh();
    }

    public function move(User $actor, ArchiveDocument $document, string $folderId): void
    {
        [$folder, $unit, $permissions] = app(QmsBrowser::class)->folder($actor, $folderId, (string) $document->departmentId);
        abort_unless($permissions['upload'] ?? false, 403);
        // Moving changes location only. Organizational, classification and audit bindings stay intact.
        abort_unless($unit && (string) $unit->getKey() === (string) $document->departmentId, 422, 'Choose a folder in the same department or office.');
        abort_unless(in_array($document->visibility ?: 'owner', app(QmsFolderAccess::class)->allowedVisibility($folder), true), 422, 'The destination cannot change document classification.');
        $current = $document->categoryId ? \App\Models\QmsCategory::find($document->categoryId) : null;
        abort_unless(($current?->folderPolicy ?: 'department') === ($folder->folderPolicy ?: 'department')
            && (string) $current?->auditAssignmentId === (string) $folder->auditAssignmentId, 422, 'The destination must retain the audit and folder scope.');
        $this->chain($document)->update(['categoryId' => $folder->getKey(), 'updatedAt' => now()->utc()]);
        $document->refresh();
    }
}
