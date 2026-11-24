<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AuditChecklist
{
    public static function readiness(array $items, array $requests = []): array
    {
        $missing = ['decisions' => 0, 'remarks' => 0, 'evidence' => 0, 'findings' => 0];
        $incomplete = [];
        foreach ($items as $index => $item) {
            $reasons = [];
            foreach ([
                'decisions' => !in_array($item['result'] ?? null, ['N', 'C', 'O'], true),
                'remarks' => trim($item['remarks'] ?? '') === '',
                'evidence' => empty($item['evidence']),
                'findings' => ($item['result'] ?? '') === 'N' && trim($item['finding'] ?? '') === '',
            ] as $field => $needed) {
                if ($needed) { $missing[$field]++; $reasons[] = $field; }
            }
            if ($reasons) { $incomplete[$index] = $reasons; }
        }
        $outstanding = self::outstandingRequests($requests);
        return $missing + ['outstandingRequests' => $outstanding, 'pendingRequests' => count($outstanding), 'incomplete' => $incomplete, 'eligible' => count($items) > 0 && !$incomplete && !$outstanding];
    }

    public static function outstandingRequests(array $requests): array
    {
        return array_values(array_filter($requests, static function ($entry) {
            $responses = $entry['responses'] ?? [];
            $last = $responses ? $responses[array_key_last($responses)] : [];
            return ($entry['status'] ?? '') !== 'reviewed'
                || ($last['review']['decision'] ?? '') !== 'reviewed'
                || empty($last['review']['author']) || empty($last['review']['at']);
        }));
    }

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

    public static function validateSubmission(array $items, array $requests = []): void
    {
        if (self::outstandingRequests($requests)) {
            throw ValidationException::withMessages(['evidenceRequests' => 'Resolve outstanding evidence requests and review each latest response before submission.']);
        }
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
