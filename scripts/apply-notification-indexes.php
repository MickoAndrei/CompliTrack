<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Unexpected database.'); }
$collection = $db->selectCollection('notifications');
$duplicates = iterator_to_array($collection->aggregate([
    ['$match' => ['dedupeKey' => ['$exists' => true]]], ['$group' => ['_id' => '$dedupeKey', 'count' => ['$sum' => 1]]],
    ['$match' => ['count' => ['$gt' => 1]]], ['$limit' => 1],
]));
if ($duplicates) { throw new RuntimeException('Duplicate keys found; no changes made.'); }
$indexes = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes()));
$encode = function () use ($collection) {
    $contents = '';
    foreach ($collection->find([], ['sort' => ['_id' => 1]]) as $document) {
        $contents .= \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document))."\n";
    }
    return $contents;
};
$backup = $encode();
$directory = storage_path('app/private/database-backups/notifications-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
if (!mkdir($directory, 0700, true)) { throw new RuntimeException('Backup directory failed.'); }
if (file_put_contents($directory.'/notifications.canonical.jsonl', $backup) !== strlen($backup)
    || hash_file('sha256', $directory.'/notifications.canonical.jsonl') !== hash('sha256', $backup)
    || file_put_contents($directory.'/indexes-before.json', json_encode($indexes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Backup verification failed.');
}
$collection->createIndex(['dedupeKey' => 1], ['name' => 'notifications_dedupe_unique', 'unique' => true, 'sparse' => true]);
$collection->createIndex(['realtimeDeliveredAt' => 1, 'realtimeLeaseUntil' => 1, 'createdAt' => 1], ['name' => 'notifications_realtime_pending']);
$result = ['backupDirectory' => $directory, 'recordsUnchanged' => $backup === $encode(),
    'indexes' => array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes()))];
file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
if (!$result['recordsUnchanged']) { throw new RuntimeException('Records changed during index maintenance; investigate concurrent activity.'); }
