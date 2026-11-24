<?php
namespace App\Services;
use App\Models\{ArchiveDocument, Department, User};
use Illuminate\Support\Arr;
class QmsArchivePresenter
{
    public function document(ArchiveDocument $document, User $actor, bool $visibleChecked = false, ?array $capabilities = null): array
    {
        $data = Arr::only($document->toArray(), ['fileCode', 'originalName', 'title', 'description', 'referenceStandard', 'createdAt', 'updatedAt', 'fileSize', 'mimeType',
            'departmentId', 'collegeId', 'recordType', 'categoryId', 'documentReference', 'physicalLocation', 'signatureRequired', 'signatureStatus',
            'visibility', 'versionNumber', 'versionGroupId', 'previousVersionId', 'auditId', 'requestId', 'isEncrypted']);
        $data['_id'] = (string) $document->getKey();
        $data['title'] = $document->title ?: $document->originalName;
        $data['recordType'] = $document->recordType ?: 'file';
        $starKey = 'qms.starred.groups.'.(string) $actor->getKey();
        if (!request()->attributes->has($starKey)) { request()->attributes->set($starKey, app(QmsBrowser::class)->starredGroups($actor)); }
        $data['isStarred'] = in_array((string) ($document->versionGroupId ?: $document->getKey()), request()->attributes->get($starKey), true);
        $data['versionNumber'] = $document->versionNumber ?: 1;
        $source = $document->sourceMetadata ?? [];
        $data['documentCode'] = $source['documentCode'] ?? $document->documentReference;
        $data['revisionLabel'] = $source['revision'] ?? null;
        $data['effectiveDate'] = $source['effectiveDate'] ?? null;
        $data['departmentName'] = app(QmsRequestReferences::class)->find(Department::class, $document->departmentId)?->departmentName ?? 'Unverified ownership';
        $data['uploadedBy'] = app(QmsRequestReferences::class)->find(User::class, $document->uploadedByUserId)?->fullName ?? 'Not recorded';
        $access = app(QmsArchiveAccess::class);
        $data['canDownload'] = (int) $actor->roleId !== 0 && $data['recordType'] === 'file'
            && ($access->isAuditTeamLeader($actor) || $document->downloadAllowed !== false);
        $data['canManage'] = $access->canManage($actor, $document, $visibleChecked);
        $data += !$data['canManage'] ? ['canRename' => false, 'canMove' => false, 'canDelete' => false]
            : ($capabilities ?? app(QmsDocumentActions::class)->capabilities($actor, $document, true));
        $data['location'] = $data['departmentName'];
        if ($document->categoryId) {
            $folder = app(QmsRequestReferences::class)->find(\App\Models\QmsCategory::class, $document->categoryId);
            if ($folder) { $data['location'] .= ' / '.$folder->name; }
        }
        $supportedEditor = $data['recordType'] === 'file' && isset(DocumentEditorAccess::TYPES[strtolower(pathinfo($document->originalName ?? '', PATHINFO_EXTENSION))]);
        $data['canOpenEditor'] = $supportedEditor && $data['canDownload'] && app(DocumentEditorAccess::class)->classificationAllowed($actor, $document)
            && app(DocumentEditorAccess::class)->scopeAllowed($actor, $document);
        $data['canEdit'] = $data['canOpenEditor'] && app(DocumentEditorAccess::class)->canEdit($actor, $document, true);
        if ($data['canManage']) {
            $data['accessUserIds'] = array_map('strval', $document->accessUserIds ?? []);
            $data['sharedAuditIds'] = array_map('strval', $document->sharedAuditIds ?? []);
            $data['downloadAllowed'] = $document->downloadAllowed !== false;
        }
        return $data;
    }
}
