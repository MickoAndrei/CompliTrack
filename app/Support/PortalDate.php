<?php

namespace App\Support;

class PortalDate
{
    public static function format(mixed $value, bool $dateOnly = false): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC')->format('M j, Y');
        }
        $date = CarTimeline::date($value)?->timezone('Asia/Manila');
        return $date ? $date->format($dateOnly ? 'M j, Y' : 'M j, Y g:i:s A').($dateOnly ? '' : ' PHT (UTC+08:00)') : 'Not recorded';
    }
}
