<?php
namespace App\Services;

use App\Models\{ArchiveDocument, Department, User};
use Illuminate\Http\Request;

class DocumentEditorAccess
{
    public const TYPES = ['docx' => 'word', 'xlsx' => 'cell', 'pptx' => 'slide', 'pdf' => 'pdf'];

    public function visible(User $actor, ArchiveDocument $document, array $context = []): bool
    {
        $request = Request::create('/', 'GET', $context);
        $query = app(QmsAuditArchive::class)->applyContext(app(QmsArchiveAccess::class)->documents($actor, true), $actor, $request);
        if (!$query->where('_id', $document->getKey())->exists()) { return false; }
        return $this->classificationAllowed($actor, $document) && $this->scopeAllowed($actor, $document);
    }

    private function units(User $actor, bool $editing)
    {
        $key = 'onlyoffice.units.'.(string) $actor->getKey().'.'.(int) $editing;
        if (!request()->attributes->has($key)) {
            if (app(QmsArchiveAccess::class)->isAuditTeamLeader($actor)) {
                $units = app(QmsRepositoryPolicy::class)->units();
            } elseif (in_array($actor->archiveResponsibility, ['department_head'], true)) {
                $units = Department::where('isActive', true)->where('ownershipVerified', true)->whereIn('_id', app(QmsArchiveAccess::class)->ids(($actor->archiveUnitIds ?? []) ?: [$actor->departmentId]));
            } elseif ((int) $actor->roleId === 1 && !in_array($actor->archiveResponsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true)) {
                $audits = app(QmsAuditArchive::class)->assignments($actor);
                if ($editing) { $audits->whereNotIn('status', ['accepted', 'archived', 'cancelled']); }
                $units = Department::whereIn('_id', app(QmsArchiveAccess::class)->ids($audits->pluck('departmentId')->all()));
            } else { $units = app(QmsFolderAccess::class)->units($actor); }
            $colleges = \App\Models\College::where('isActive', true)->get(['_id'])->map(fn ($college) => $college->getKey())->all();
            request()->attributes->set($key, $units->whereIn('collegeId', app(QmsArchiveAccess::class)->ids($colleges))->get());
        }
        return request()->attributes->get($key);
    }

    public function scopeAllowed(User $actor, ArchiveDocument $document): bool
    {
        return $actor->isActive && ((int) $actor->roleId === 2 || $this->units($actor, false)->contains(fn ($unit) => (string) $unit->getKey() === (string) $document->departmentId));
    }

    public function classificationAllowed(User $actor, ArchiveDocument $document): bool
    {
        if (app(QmsArchiveAccess::class)->isAuditTeamLeader($actor)) { return true; }
        // Administrative roles do not override a protected classification in the editor.
        if (in_array($document->visibility, ['owner', 'restricted'], true) && (int) $actor->roleId !== 2) {
            return (string) $document->uploadedByUserId === (string) $actor->getKey()
                || in_array((string) $actor->getKey(), array_map('strval', $document->accessUserIds ?? []), true);
        }
        return true;
    }

    public function canEdit(User $actor, ArchiveDocument $document, bool $visibleChecked = false): bool
    {
        $folder = $document->categoryId ? app(QmsRequestReferences::class)->find(\App\Models\QmsCategory::class, $document->categoryId) : null;
        if ($folder?->folderPolicy === 'notes') {
            return $this->visible($actor, $document) && app(QmsRepositoryPolicy::class)->permissions($actor, $folder)['upload'];
        }
        if (!$actor->isActive || (!in_array((int) $actor->roleId, [1, 3], true) && $actor->archiveResponsibility !== 'department_head') || (!$visibleChecked && !$this->visible($actor, $document))) { return false; }
        $unit = $this->units($actor, true)->first(fn ($unit) => (string) $unit->getKey() === (string) $document->departmentId);
        if (!$unit) { return false; }
        if (in_array($actor->archiveResponsibility, ['department_head', 'college_secretary', 'cluster_administrator'], true)) {
            $folder = $document->categoryId ? app(QmsRequestReferences::class)->find(\App\Models\QmsCategory::class, $document->categoryId) : null;
            return app(QmsFolderAccess::class)->permissions($actor, $unit, $folder)['upload'];
        }
        // The existing ATL assignment is an explicit unit scope, not a global archive grant.
        return (int) $actor->roleId === 1 && $actor->archiveResponsibility !== 'college_dean';
    }
}
