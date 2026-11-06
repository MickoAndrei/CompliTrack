<?php

// Explicitly invoked development maintenance. Never seeds, updates or deletes documents.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') {
    throw new RuntimeException('Expected complitrack; no changes made.');
}
$db->command(['ping' => 1]);
$apply = in_array('--apply', $argv, true);
$definitions = [
    ['key' => ['auditCode' => 1], 'name' => 'audits_code_unique', 'unique' => true],
    ['key' => ['assignmentKey' => 1], 'name' => 'audits_assignment_key_unique', 'unique' => true, 'sparse' => true],
    ['key' => ['auditorId' => 1, 'departmentId' => 1, 'createdAt' => -1], 'name' => 'audits_auditor_department'],
    ['key' => ['reviewerId' => 1, 'status' => 1], 'name' => 'audits_reviewer_status'],
];
$names = iterator_to_array($db->listCollectionNames());
$exists = in_array('audits', $names, true);
$collection = $db->selectCollection('audits');
$beforeIndexes = $exists ? array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes())) : [];
foreach ($definitions as $definition) {
    foreach ($beforeIndexes as $index) {
        $key = (array) $index['key'];
        if ($index['name'] === $definition['name'] || $key === $definition['key']) {
            if ($key !== $definition['key'] || (bool) ($index['unique'] ?? false) !== (bool) ($definition['unique'] ?? false)
                || (bool) ($index['sparse'] ?? false) !== (bool) ($definition['sparse'] ?? false)
                || isset($index['partialFilterExpression']) || isset($index['collation'])) {
                throw new RuntimeException('Conflicting existing index; no changes made.');
            }
        }
    }
}
// Unique indexes must not force deletion or rewriting of existing records.
foreach (['auditCode', 'assignmentKey'] as $field) {
    $pipeline = [];
    if ($field === 'assignmentKey') { $pipeline[] = ['$match' => [$field => ['$exists' => true]]]; }
    $pipeline[] = ['$group' => ['_id' => '$'.$field, 'count' => ['$sum' => 1]]];
    $pipeline[] = ['$match' => ['count' => ['$gt' => 1]]];
    $pipeline[] = ['$limit' => 1];
    if (iterator_to_array($collection->aggregate($pipeline))) {
        throw new RuntimeException('Duplicate '.$field.' values; no changes made.');
    }
}
$fingerprints = function () use ($db) {
    $result = [];
    foreach ($db->listCollectionNames() as $name) {
        $hash = hash_init('sha256'); $count = 0;
        foreach ($db->selectCollection($name)->find([], ['sort' => ['_id' => 1]]) as $document) {
            hash_update($hash, \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document))."\n");
            $count++;
        }
        $result[$name] = ['count' => $count, 'sha256' => hash_final($hash)];
    }
    ksort($result);
    return $result;
};
$before = $fingerprints();
$department = \App\Models\Department::where('departmentCode', 'DEMO-IT')->first();
$report = ['database' => $db->getDatabaseName(), 'connectivity' => 'ok', 'mode' => $apply ? 'apply' : 'preflight',
    'auditsCollectionExisted' => $exists, 'beforeIndexes' => $beforeIndexes, 'beforeRecords' => $before,
    'demoITCollege' => $department?->collegeName, 'expectedCollege' => 'CET'];
if (in_array('--verify', $argv, true)) {
    $directories = glob(storage_path('app/private/database-backups/*/manifest.json'));
    rsort($directories);
    if (!$directories) { throw new RuntimeException('No pre-change manifest found.'); }
    $manifest = json_decode(file_get_contents($directories[0]), true, flags: JSON_THROW_ON_ERROR);
    $unchanged = true;
    foreach ($manifest['beforeRecords'] as $name => $fingerprint) {
        if (($before[$name] ?? null) !== $fingerprint) { $unchanged = false; }
    }
    $verified = true;
    foreach ($definitions as $required) {
        if (!array_filter($beforeIndexes, fn ($index) => (array) $index['key'] === $required['key']
            && (bool) ($index['unique'] ?? false) === (bool) ($required['unique'] ?? false)
            && (bool) ($index['sparse'] ?? false) === (bool) ($required['sparse'] ?? false))) { $verified = false; }
    }
    $report['mode'] = 'verify';
    $report['backup'] = $manifest['backup'];
    $report['existingRecordsUnchanged'] = $unchanged;
    $report['requiredIndexesVerified'] = $verified;
    file_put_contents(dirname($directories[0]).'/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
    if (!$unchanged || !$verified) { throw new RuntimeException('Verification failed.'); }
    exit;
}
if (!$apply) { echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL; exit; }
$directory = storage_path('app/private/database-backups/'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
if (!mkdir($directory, 0700, true)) { throw new RuntimeException('Cannot create backup directory.'); }
$path = $directory.'/audits.canonical.jsonl';
$handle = fopen($path, 'xb');
if (!$handle) { throw new RuntimeException('Cannot create backup.'); }
$count = 0;
foreach ($collection->find([], ['sort' => ['_id' => 1]]) as $document) {
    $line = \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document))."\n";
    if (fwrite($handle, $line) !== strlen($line)) { throw new RuntimeException('Incomplete backup; no indexes applied.'); }
    $count++;
}
fclose($handle);
if ($count !== ($before['audits']['count'] ?? 0) || hash_file('sha256', $path) !== ($before['audits']['sha256'] ?? hash('sha256', ''))) {
    throw new RuntimeException('Backup differs from preflight; no indexes applied.');
}
$report['backup'] = ['directory' => $directory, 'count' => $count, 'sha256' => hash_file('sha256', $path), 'format' => 'Canonical Extended JSON lines'];
if (file_put_contents($directory.'/manifest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot write backup manifest; no indexes applied.');
}
foreach ($definitions as $definition) {
    $key = $definition['key']; unset($definition['key']);
    $collection->createIndex($key, $definition);
}
$report['afterIndexes'] = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($collection->listIndexes()));
foreach ($definitions as $required) {
    $matching = array_filter($report['afterIndexes'], fn ($index) => (array) $index['key'] === $required['key']
        && (bool) ($index['unique'] ?? false) === (bool) ($required['unique'] ?? false)
        && (bool) ($index['sparse'] ?? false) === (bool) ($required['sparse'] ?? false));
    if (!$matching) { throw new RuntimeException('Required index verification failed.'); }
}
$report['requiredIndexesVerified'] = true;
$report['afterRecords'] = $fingerprints();
$preserved = true;
foreach ($before as $name => $fingerprint) {
    if (($report['afterRecords'][$name] ?? null) !== $fingerprint) { $preserved = false; }
}
$report['existingRecordsUnchanged'] = $preserved;
file_put_contents($directory.'/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
if (!$preserved) { throw new RuntimeException('Record fingerprint changed during operation; inspect concurrent application activity.'); }
