<?php
namespace App\Services;
use App\Models\{ArchiveDocument, Department, User};
use Illuminate\Support\Arr;
class QmsArchivePresenter
{
    private array $unitNames = [];
    private array $ownerNames = [];
    public function document(ArchiveDocument $document, User $actor): array
    {
        $data = Arr::only($document->toArray(), ['fileCode', 'originalName', 'title', 'description', 'referenceStandard', 'createdAt', 'fileSize', 'mimeType',
            'departmentId', 'collegeId', 'recordType', 'categoryId', 'documentReference', 'physicalLocation', 'signatureRequired', 'signatureStatus',
            'visibility', 'versionNumber', 'versionGroupId', 'previousVersionId', 'auditId', 'requestId', 'isEncrypted']);
        $data['_id'] = (string) $document->getKey();
        $data['title'] = $document->title ?: $document->originalName;
        $data['recordType'] = $document->recordType ?: 'file';
        $data['versionNumber'] = $document->versionNumber ?: 1;
        $source = $document->sourceMetadata ?? [];
        $data['documentCode'] = $source['documentCode'] ?? $document->documentReference;
        $data['revisionLabel'] = $source['revision'] ?? null;
        $data['effectiveDate'] = $source['effectiveDate'] ?? null;
        $key = (string) $document->departmentId;
        $data['departmentName'] = $this->unitNames[$key] ??= Department::find($document->departmentId)?->departmentName ?? 'Unverified ownership';
        $owner = (string) $document->uploadedByUserId;
        $data['uploadedBy'] = $this->ownerNames[$owner] ??= User::find($document->uploadedByUserId)?->fullName ?? 'Not recorded';
        $access = app(QmsArchiveAccess::class);
        $data['canDownload'] = (int) $actor->roleId !== 0 && $data['recordType'] === 'file'
            && ($access->isAuditTeamLeader($actor) || $document->downloadAllowed !== false);
        $data['canManage'] = $access->canManage($actor, $document);
        if ($data['canManage']) {
            $data['accessUserIds'] = array_map('strval', $document->accessUserIds ?? []);
            $data['sharedAuditIds'] = array_map('strval', $document->sharedAuditIds ?? []);
            $data['downloadAllowed'] = $document->downloadAllowed !== false;
        }
        return $data;
    }
}
