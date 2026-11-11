<?php

// Explicit, backup-first maintenance. Only adds a non-unique index; never changes records.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Expected complitrack; no changes made.'); }
$users = $db->selectCollection('users');
$indexes = fn () => array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($users->listIndexes()));
$explain = function () use ($db): array {
    $plans = [];
    foreach (['all' => [], 'auditors' => ['roleId' => 2], 'active' => ['isActive' => true]] as $name => $filter) {
        $result = $db->command(['explain' => ['find' => 'users', 'filter' => (object) $filter, 'sort' => ['fullName' => 1], 'limit' => 20],
            'verbosity' => 'executionStats'])->toArray()[0];
        $stages = [];
        $walk = function ($value) use (&$walk, &$stages): void {
            if (!is_array($value) && !is_object($value)) { return; }
            foreach ($value as $key => $entry) {
                if ($key === 'stage') { $stages[] = $entry; }
                else { $walk($entry); }
            }
        };
        $walk($result->queryPlanner->winningPlan);
        $stats = $result->executionStats;
        $plans[$name] = ['stages' => array_values(array_unique($stages)), 'returned' => $stats->nReturned,
            'documentsExamined' => $stats->totalDocsExamined, 'keysExamined' => $stats->totalKeysExamined, 'serverMs' => $stats->executionTimeMillis];
    }
    return $plans;
};
$before = $indexes();
$result = ['beforeIndexes' => $before, 'beforeExplain' => $explain(), 'applied' => false];
$matching = false;
foreach ($before as $index) {
    if ($index['name'] === 'users_full_name' || (array) $index['key'] === ['fullName' => 1]) {
        if ((array) $index['key'] !== ['fullName' => 1] || ($index['unique'] ?? false)
            || ($index['sparse'] ?? false) || isset($index['partialFilterExpression']) || isset($index['collation'])) {
            throw new RuntimeException('Conflicting index definition; no changes made.');
        }
        $matching = true;
    }
}
if (in_array('--apply', $argv, true) && !$matching) {
    if (!in_array('SORT', $result['beforeExplain']['all']['stages'], true)) {
        throw new RuntimeException('No blocking sort observed; an additional index is not justified.');
    }
    $snapshot = function () use ($users): string {
        $data = '';
        foreach ($users->find([], ['sort' => ['_id' => 1]]) as $record) {
            $data .= \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($record))."\n";
        }
        return $data;
    };
    $records = $snapshot();
    $directory = storage_path('app/private/database-backups/user-list-index-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
    if (!mkdir($directory, 0700, true) || file_put_contents($directory.'/users.canonical.jsonl', $records) !== strlen($records)
        || hash_file('sha256', $directory.'/users.canonical.jsonl') !== hash('sha256', $records)
        || file_put_contents($directory.'/indexes-before.json', json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
        throw new RuntimeException('Backup failed; no index changes attempted.');
    }
    $users->createIndex(['fullName' => 1], ['name' => 'users_full_name']);
    $after = $indexes();
    foreach ($before as $old) {
        if (!in_array($old, $after, true)) { throw new RuntimeException('An existing index differs.'); }
    }
    $result += ['backupDirectory' => $directory, 'recordsUnchanged' => $records === $snapshot()];
    $result['applied'] = true;
    $result['afterIndexes'] = $after;
    $result['afterExplain'] = $explain();
    file_put_contents($directory.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    if (!$result['recordsUnchanged']) { throw new RuntimeException('Records changed during maintenance; inspect concurrent activity.'); }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
