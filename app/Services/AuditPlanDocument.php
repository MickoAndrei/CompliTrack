<?php

namespace App\Services;

use App\Models\ArchiveDocument;

/** Purpose is explicit metadata, independent of presentation method or filename. */
class AuditPlanDocument
{
    public static function eligible(ArchiveDocument $document): bool
    {
        $classification = $document->auditPlanClassification ?? [];
        return $document->ownershipVerified && filled($document->documentReference ?: $document->fileCode)
            && ($classification['purpose'] ?? '') === 'audit-plan'
            && filled($classification['verifiedBy'] ?? null) && filled($classification['reason'] ?? null)
            && (int) ($classification['documentVersion'] ?? 0) === (int) ($document->versionNumber ?: 1)
            && ($classification['checksum'] ?? null) === $document->checksum;
    }

    public static function card(ArchiveDocument $document): array
    {
        return ['id' => (string) $document->getKey(), 'title' => $document->title ?: $document->originalName,
            'code' => $document->documentReference ?: $document->fileCode, 'type' => $document->recordType,
            'version' => $document->versionNumber ?: 1, 'eligible' => self::eligible($document)];
    }
}
