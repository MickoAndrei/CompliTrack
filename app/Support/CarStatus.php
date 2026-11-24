<?php

namespace App\Support;

/** Normalize spelling only. Evidence and legacy progress never imply verification. */
class CarStatus
{
    public static function storedValues(array $states): array
    {
        return array_map(static fn ($state) => new \MongoDB\BSON\Regex('^\s*(?:'.match ($state) {
            'in_progress' => 'in[ _-]?progress',
            'returned_for_revision' => 'returned([ _-]for[ _-]revision)?|needs[ _-]revision',
            default => str_replace('_', '[ _-]', preg_quote($state, '/')),
        }.')\s*$', 'i'), $states);
    }
    public static function canonical(?string $value): ?string
    {
        if ($value === null) { return null; }
        $key = strtolower(trim(str_replace(['-', ' '], '_', $value)));
        return match ($key) {
            'open', 'in_progress', 'closed', 'awaiting_verification', 'returned_for_revision' => $key,
            'inprogress' => 'in_progress', 'returned', 'needs_revision' => 'returned_for_revision',
            default => $value,
        };
    }

    public static function known(?string $value): bool
    {
        return in_array(self::canonical($value), ['open', 'in_progress', 'closed', 'awaiting_verification', 'returned_for_revision'], true);
    }

    public static function nextAction(?string $value): string
    {
        return match (self::canonical($value)) {
            'open', 'in_progress', 'returned_for_revision' => 'Assigned auditee: provide corrective evidence. Assigned auditor: review and record verification before closure.',
            'awaiting_verification' => 'Assigned auditor: review corrective evidence and record verification, or return to In Progress.',
            'closed' => 'Closed. Consult the recorded history for verification details. Assigned auditor may reopen with a reason.',
            default => 'Unrecognized legacy state. Assigned auditor must inspect the history and reopen with an explanatory reason; no closure is inferred.',
        };
    }
}
