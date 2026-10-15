<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

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
