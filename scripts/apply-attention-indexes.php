<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection('mongodb')->getMongoDB();
// Additive indexes only. No records, collections, or existing indexes are removed.
$definitions = [
    ['notifications', ['recipientUserId' => 1, 'createdAt' => -1, '_id' => -1], 'notifications_recipient_created_id'],
    ['audit_logs', ['createdAt' => -1, '_id' => -1], 'audit_logs_created'],
    ['audit_logs', ['category' => 1, 'outcome' => 1, 'createdAt' => -1, '_id' => -1], 'audit_logs_category_outcome_created'],
];
foreach ($definitions as [$collection, $keys, $name]) {
    $database->selectCollection($collection)->createIndex($keys, ['name' => $name]);
    echo $name.PHP_EOL;
}
