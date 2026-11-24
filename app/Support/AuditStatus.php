<?php

namespace App\Support;

class AuditStatus
{
    public static function label(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft', 'ready', 'in_progress' => 'In Progress',
            'submitted' => 'Submitted — Awaiting ATL Review', 'returned' => 'Returned for Revision',
            'accepted' => 'Accepted / Completed', default => 'Unknown audit state',
        };
    }
}
