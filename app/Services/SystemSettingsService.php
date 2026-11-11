<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SystemSettingsService
{
    public function path(string $name = ''): string
    {
        $root = config('system.path');
        File::ensureDirectoryExists($root, 0700);

        return $root.($name === '' ? '' : DIRECTORY_SEPARATOR.$name);
    }

    public function read(): array
    {
        $path = $this->path('settings.enc');
        if (! is_file($path)) {
            return [];
        }

        return json_decode(Crypt::decryptString(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
    }

    public function update(callable $change): array
    {
        $lock = fopen($this->path('settings.lock'), 'c+');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock system settings.');
        }
        try {
            $settings = $change($this->read());
            $encoded = Crypt::encryptString(json_encode($settings, JSON_THROW_ON_ERROR));
            File::replace($this->path('settings.enc'), $encoded, 0600);

            return $settings;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function publicState(): array
    {
        $settings = $this->read();
        $heartbeat = $this->path('scheduler-heartbeat');

        return [
            'backupsEnabled' => $settings['backupsEnabled'] ?? false,
            'backupTime' => config('system.backup_time'),
            'timezone' => config('system.timezone'),
            'schedulerRunning' => is_file($heartbeat) && filemtime($heartbeat) > time() - 150,
            'lastBackup' => $settings['lastBackup'] ?? null,
            'backupError' => $settings['backupError'] ?? null,
            'maintenanceEnabled' => $settings['maintenanceEnabled'] ?? false,
            'lastRotation' => $settings['lastRotation'] ?? null,
            'rotationPending' => $settings['rotationPending'] ?? false,
        ];
    }
}
