<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\PersonalAccessToken;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mongodb:check', function () {
    try {
        $database = DB::connection('mongodb')->getMongoDB();
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
