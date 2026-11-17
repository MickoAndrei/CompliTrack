<?php

namespace App\Support;

class EvidenceRequestStatus
{
    public static function label(string $status, string $auditStatus): string
    {
        return match ($status) {
            'requested' => 'Awaiting Response', 'needs_information' => 'More Information Requested',
            'response_submitted' => 'Response Submitted — Awaiting Auditor Review',
            'reviewed' => 'Response Reviewed',
            default => 'Unknown status',
        };
    }
}
