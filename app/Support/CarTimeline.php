<?php

namespace App\Support;

use App\Models\ComplianceCheck;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use MongoDB\BSON\UTCDateTime;

/** Read-only presentation of the existing CAR and its recorded events. */
class CarTimeline
{
    public static function label(?string $status): string
    {
        return match ($status) {
            'open' => 'Open', 'in_progress' => 'In Progress', 'closed' => 'Closed',
            default => $status ? ucfirst(str_replace(['_', '-'], ' ', $status)) : 'No CAR',
        };
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        try {
            if ($value instanceof UTCDateTime) { return CarbonImmutable::instance($value->toDateTime())->utc(); }
            if ($value instanceof DateTimeInterface) { return CarbonImmutable::instance($value)->utc(); }
            if (is_array($value)) {
                $value = $value['$date'] ?? null;
                if (is_array($value)) { $value = $value['$numberLong'] ?? null; }
                if (is_numeric($value)) { return CarbonImmutable::createFromTimestampMsUTC($value); }
            }
            return is_string($value) && trim($value) !== '' ? CarbonImmutable::parse($value)->utc() : null;
        } catch (\Throwable) { return null; }
    }

    public static function forCheck(ComplianceCheck $check, array $roles = []): array
    {
        if ($check->result !== 'non_compliant' || !$check->correctiveStatus) { return []; }
        $responsible = function ($id) use ($check, $roles): ?string {
            if (!$id) { return null; }
            if (isset($roles[(string) $id])) {
                return match ((int) $roles[(string) $id]) { 0 => 'Super Admin', 1 => 'Admin', 2 => 'Auditor', 3 => 'Auditee', default => null };
            }
            if ((string) $id === (string) $check->auditorId) { return 'Assigned auditor'; }
            if ((string) $id === (string) $check->auditeeId) { return 'Assigned auditee'; }
            return null;
        };
        $events = [];
        foreach ($check->statusHistory ?? [] as $index => $event) {
            if (empty($event['status'])) { continue; }
            $events[] = ['kind' => 'status', 'status' => $event['status'], 'label' => self::label($event['status']),
                'at' => self::date($event['timestamp'] ?? null), 'role' => $responsible($event['changedByUserId'] ?? null),
                'state' => 'completed', 'order' => count($events)];
        }
        foreach ($check->evidenceResponses ?? [] as $response) {
            $events[] = ['kind' => 'evidence', 'status' => null, 'label' => 'Corrective evidence submitted',
                'at' => self::date($response['at'] ?? null), 'role' => $responsible($response['author'] ?? null),
                'state' => 'completed', 'order' => count($events)];
        }
        // Missing timestamps remain undated. Preserve source order for ties and unknown dates.
        usort($events, fn ($a, $b) => ($a['at']?->getTimestampMs() ?? PHP_INT_MAX) <=> ($b['at']?->getTimestampMs() ?? PHP_INT_MAX)
            ?: $a['order'] <=> $b['order']);
        $current = null;
        $lastStatus = null;
        $submissions = 0;
        foreach ($events as $index => &$event) {
            if ($event['kind'] === 'status') { $lastStatus = $index; }
            else { $event['label'] = ++$submissions > 1 ? 'Corrective evidence resubmitted' : 'Corrective evidence submitted'; }
        }
        unset($event);
        if ($lastStatus !== null && $events[$lastStatus]['status'] === $check->correctiveStatus) { $current = $lastStatus; }
        if ($current === null) {
            $current = count($events);
            $events[] = ['kind' => 'status', 'status' => $check->correctiveStatus, 'label' => self::label($check->correctiveStatus),
                'at' => null, 'role' => null, 'state' => 'current', 'order' => $current];
        }
        $events[$current]['state'] = 'current';
        // These are expected future stages, never recorded events or proof of completion.
        $upcoming = match ($check->correctiveStatus) {
            'open' => [['in_progress', 'In Progress'], ['closed', 'Awaiting auditor verification']],
            'in_progress' => [['closed', 'Awaiting auditor verification']],
            default => [],
        };
        foreach ($upcoming as [$status, $label]) {
            $events[] = ['kind' => 'upcoming', 'status' => $status, 'label' => $label, 'at' => null, 'role' => 'Auditor', 'state' => 'upcoming'];
        }
        return $events;
    }
}
