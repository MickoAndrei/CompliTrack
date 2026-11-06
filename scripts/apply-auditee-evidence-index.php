<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Unexpected database.'); }
$collection = $db->selectCollection('audits');
$encode = function () use ($collection) {
    $contents = '';
    foreach ($collection->find([], ['sort' => ['_id' => 1]]) as $record) { $contents .= \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($record))."\n"; }
    return $contents;
};
$before = $encode();
$indexes = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes()));
$directory = storage_path('app/private/database-backups/auditee-index-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
if (!mkdir($directory, 0700, true)
    || file_put_contents($directory.'/audits.canonical.jsonl', $before) !== strlen($before)
    || hash_file('sha256', $directory.'/audits.canonical.jsonl') !== hash('sha256', $before)
    || file_put_contents($directory.'/indexes-before.json', json_encode($indexes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Backup failed; no index changes attempted.');
}
$collection->createIndex(['departmentId' => 1, 'evidenceRequests.auditeeId' => 1, 'updatedAt' => -1], ['name' => 'audits_auditee_requests']);
$after = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes()));
foreach ($indexes as $old) {
    if (!in_array($old, $after, true)) { throw new RuntimeException('Existing index differs.'); }
}
$result = ['database' => $db->getDatabaseName(), 'backupDirectory' => $directory,
    'recordsUnchanged' => $before === $encode(), 'existingIndexesPreserved' => true,
    'newIndex' => 'audits_auditee_requests', 'indexKeys' => ['departmentId' => 1, 'evidenceRequests.auditeeId' => 1, 'updatedAt' => -1]];
file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
if (!$result['recordsUnchanged']) { throw new RuntimeException('Records changed during maintenance; inspect concurrent activity.'); }
