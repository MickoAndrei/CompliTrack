<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AuditChecklist
{
    public static function progress(array $items): array
    {
        $counts = ['N' => 0, 'C' => 0, 'O' => 0];
        foreach ($items as $item) {
            if (isset($counts[$item['result'] ?? ''])) {
                $counts[$item['result']]++;
            }
        }
        $evaluated = array_sum($counts);
        return $counts + ['total' => count($items), 'evaluated' => $evaluated,
            'pending' => count($items) - $evaluated,
            'percent' => count($items) ? round($evaluated / count($items) * 100) : 0];
    }

    public static function validateSubmission(array $items): void
    {
        if (!$items) {
            throw ValidationException::withMessages(['checklist' => 'Prepare at least one check item.']);
        }
        foreach ($items as $item) {
            if (!in_array($item['result'] ?? null, ['N', 'C', 'O'], true)
                || trim($item['remarks'] ?? '') === '' || empty($item['evidence'])) {
                throw ValidationException::withMessages(['checklist' => 'Every item needs N/C/O, audit remarks, and at least one evidence review. No upload is required.']);
            }
            if ($item['result'] === 'N' && trim($item['finding'] ?? '') === '') {
                throw ValidationException::withMessages(['finding' => 'Describe the nonconformity for every N item.']);
            }
        }
    }
}
