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

        if (($hello->setName ?? null) !== 'rs0') {
            $this->error('MongoDB is reachable but is not running as replica set rs0.');

            return 1;
        }

        $stream = $database->selectCollection('compliance_checks')->watch([], [
            'maxAwaitTimeMS' => 500,
        ]);
        $stream->close();
    } catch (\Throwable $exception) {
        $this->error('MongoDB replica-set or change-stream check failed: '.$exception->getMessage());

        return 1;
    }

    $this->info('MongoDB replica set rs0 and change streams are available.');

    return 0;
})->purpose('Check MongoDB replica-set and change-stream connectivity');

Artisan::command('complitrack:logout-all', function () {
    $driver = config('session.driver');
    if ($driver === 'database') {
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
