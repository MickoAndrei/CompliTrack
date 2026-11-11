<?php

namespace App\Support;

class EvidenceRequestStatus
{
    public static function label(string $status, string $auditStatus): string
    {
        return match ($status) {
            'requested', 'needs_information' => 'Awaiting Response',
            'response_submitted' => in_array($auditStatus, ['submitted', 'accepted'], true) ? 'Under Review' : 'Submitted',
            'reviewed' => 'Completed',
            default => 'Unknown status',
        };
    }
}
