<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\Schedule;
use App\Services\SystemSettingsService;
use App\Services\SystemBackupService;

Artisan::command('complitrack:backup', function () {
    try {
        $file = app(SystemBackupService::class)->create();
        $this->info('Encrypted backup completed and verified: '.$file);

        return 0;
    } catch (\Throwable $exception) {
        report($exception);
        $this->error('Backup failed. Check the Laravel log.');

        return 1;
    }
})->purpose('Create and verify an encrypted database and uploads backup');

Artisan::command('complitrack:backup-verify {file}', function () {
    $count = app(SystemBackupService::class)->verify($this->argument('file'));
    $this->info("Verified {$count} encrypted records.");
})->purpose('Verify backup authentication, record order and completeness');

Artisan::command('complitrack:backup-restore {file} {database} {directory}', function () {
    app(SystemBackupService::class)->restore($this->argument('file'), $this->argument('database'), $this->argument('directory'));
    $this->info('Backup restored into the new database and directory. Live configuration was not changed.');
})->purpose('Restore a backup into a NEW database and a NEW directory only');

Schedule::call(function () {
    touch(app(SystemSettingsService::class)->path('scheduler-heartbeat'));
})->everyMinute()->name('system-scheduler-heartbeat');

Schedule::command('complitrack:backup')
    ->dailyAt(config('system.backup_time'))
    ->timezone(config('system.timezone'))
    ->when(fn () => app(SystemSettingsService::class)->read()['backupsEnabled'] ?? false)
    ->withoutOverlapping(1440);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mongodb:check {--ping : Only check database connectivity, without inspecting change streams}', function () {
    try {
        $database = DB::connection('mongodb')->getMongoDB();
        if ($this->option('ping')) {
            $database->command(['ping' => 1])->toArray();
            $this->info('MongoDB is reachable.');

            return 0;
        }

        $hello = $database->command(['hello' => 1])->toArray()[0] ?? null;
        $replicaSetName = $hello->setName ?? null;

        $hasChangeStreams = false;
        try {
            $stream = $database->selectCollection('compliance_checks')->watch([], [
                'maxAwaitTimeMS' => 500,
            ]);
            unset($stream);
            $hasChangeStreams = true;
        } catch (\Throwable $e) {
            // Change streams require a replica set or mongos
        }

        if ($replicaSetName) {
            $this->info("MongoDB is reachable (Replica Set: {$replicaSetName}). Change streams: " . ($hasChangeStreams ? 'Available' : 'Unavailable'));
        } else {
            $this->info("MongoDB is reachable (Standalone). Change streams: " . ($hasChangeStreams ? 'Available' : 'Unavailable'));
        }

        return 0;
    } catch (\Throwable $exception) {
        $this->error('MongoDB connection check failed: '.$exception->getMessage());
        if ($exception instanceof \MongoDB\Driver\Exception\ConnectionException) {
            $this->warn('For MongoDB Atlas, check that this machine\'s current public IP (including any VPN egress IP) is in the project Network Access IP access list and that the cluster is available.');
            $this->warn('Check outbound TCP port 27017 and any VPN, proxy, firewall, or antivirus TLS inspection. For TLS errors, also verify the PHP MongoDB driver, CA certificates, and system clock.');
            $this->line('Troubleshooting: https://www.mongodb.com/docs/atlas/troubleshoot-connection/');
        }

        return 1;
    }
})->purpose('Check MongoDB connectivity and change-stream support');

Artisan::command('complitrack:logout-all', function () {
    $driver = config('session.driver');
    if ($driver === 'database' || $driver === 'mongodb') {
        DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->delete();
    } elseif ($driver === 'file') {
        File::cleanDirectory(config('session.files'));
    } else {
        $this->error("Automatic restart logout does not support SESSION_DRIVER=$driver. No sessions were cleared.");

        return 1;
    }

    User::query()->update(['remember_token' => null]);
    PersonalAccessToken::query()->delete();

    $this->info('All web sessions, remember-me logins, and API tokens were cleared.');

    return 0;
})->purpose('Log out all accounts after a CompliTrack server restart');
