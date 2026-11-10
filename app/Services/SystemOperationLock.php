<?php

namespace App\Services;

class SystemOperationLock
{
    public function run(callable $action, bool $exclusive = true): mixed
    {
        $handle = fopen(app(SystemSettingsService::class)->path('operations.lock'), 'c+');
        if (! $handle || ! flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            abort(423, 'A system operation is in progress. Please try again shortly.');
        }
        try {
            return $action();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
