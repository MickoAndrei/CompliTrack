<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Unexpected database.'); }
$id = new \MongoDB\BSON\ObjectId('6aeac5b68ce89fe741003b38');
$collection = $db->selectCollection('departments');
$original = $collection->findOne(['_id' => $id, 'departmentCode' => 'DEMO-IT', 'departmentName' => '[DEMO] Information Technology', 'isActive' => true]);
if (!$original || !in_array($original['collegeName'], ['Collage of Engineering and Technology', 'CET'], true)) {
    throw new RuntimeException('Department identity/value differs from authorized correction.');
}
$auditor = \App\Models\User::where('email', 'demo.auditor.it@example.test')->firstOrFail();
$admin = \App\Models\User::where('email', 'demo.admin@example.test')->firstOrFail();
if ((int) $auditor->roleId !== 2 || !$auditor->isActive || (string) $auditor->departmentId !== (string) $id
    || !in_array((string) $id, array_map('strval', app(\App\Services\RoleScopeService::class)->assignedDepartmentIds($auditor)), true)
    || (int) $admin->roleId !== 1 || !$admin->isActive) { throw new RuntimeException('Demo account relationships differ.'); }
$references = [];
$hashes = function () use ($db, $id, &$references) {
    $result = [];
    foreach ($db->listCollectionNames() as $name) {
        $context = hash_init('sha256'); $count = 0;
        foreach ($db->selectCollection($name)->find([], ['sort' => ['_id' => 1]]) as $doc) {
            if ($name === 'departments' && (string) $doc['_id'] === (string) $id) { continue; }
            hash_update($context, \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($doc))."\n"); $count++;
        }
        $result[$name] = ['count' => $count, 'hash' => hash_final($context)];
        $references[$name] = $db->selectCollection($name)->countDocuments(['$or' => [
            ['departmentId' => ['$in' => [$id, (string) $id]]], ['assignedDepartmentIds' => ['$in' => [$id, (string) $id]]],
        ]]);
    }
    ksort($result); return $result;
};
$before = $hashes();
$indexesBefore = array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($db->selectCollection('audits')->listIndexes()));
$report = ['departmentId' => (string) $id, 'departmentName' => $original['departmentName'], 'oldCollege' => $original['collegeName'],
    'expectedCollege' => 'CET', 'references' => $references, 'beforeRecordsExceptTarget' => $before, 'indexesBefore' => $indexesBefore];
if (!in_array('--apply', $argv, true)) {
    $request = \Illuminate\Http\Request::create('/audits'); $request->setUserResolver(fn () => $admin);
    \Illuminate\Support\Facades\Auth::setUser($admin);
    view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    $view = app(\App\Http\Controllers\Web\AuditController::class)->index($request);
    $report['adminDepartmentSelectable'] = $view->getData()['departments']->contains(fn ($department) => (string) $department->getKey() === (string) $id);
    $report['demoAuditorSelectable'] = $view->getData()['auditors']->contains(fn ($user) => (string) $user->getKey() === (string) $auditor->getKey());
    $report['assignmentPageRendered'] = str_contains($view->render(), '[DEMO] Information Technology');
    echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL; exit;
}
$directory = storage_path('app/private/database-backups/demo-it-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)));
if (!mkdir($directory, 0700, true)) { throw new RuntimeException('Cannot create backup directory.'); }
$backup = \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($original))."\n";
if (file_put_contents($directory.'/department.canonical.jsonl', $backup, LOCK_EX) !== strlen($backup)
    || hash_file('sha256', $directory.'/department.canonical.jsonl') !== hash('sha256', $backup)
    || file_put_contents($directory.'/manifest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Backup failed; no update attempted.');
}
if ($original['collegeName'] !== 'CET') {
    $updated = $collection->updateOne(['_id' => $id, 'departmentCode' => 'DEMO-IT', 'collegeName' => 'Collage of Engineering and Technology'], ['$set' => ['collegeName' => 'CET']]);
    if ($updated->getModifiedCount() !== 1) { throw new RuntimeException('Concurrent change detected.'); }
}
$after = $collection->findOne(['_id' => $id]);
$expected = clone $original; $expected['collegeName'] = 'CET';
$report['onlyCollegeChanged'] = \MongoDB\BSON\fromPHP($after) === \MongoDB\BSON\fromPHP($expected);
$report['allOtherRecordsUnchanged'] = $before === $hashes();
$report['indexesUnchanged'] = $indexesBefore === array_map(fn ($index) => $index->__debugInfo(), iterator_to_array($db->selectCollection('audits')->listIndexes()));
$request = \Illuminate\Http\Request::create('/audits'); $request->setUserResolver(fn () => $admin);
\Illuminate\Support\Facades\Auth::setUser($admin);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());
$view = app(\App\Http\Controllers\Web\AuditController::class)->index($request);
$report['adminDepartmentSelectable'] = $view->getData()['departments']->contains(fn ($department) => (string) $department->getKey() === (string) $id);
$report['demoAuditorSelectable'] = $view->getData()['auditors']->contains(fn ($user) => (string) $user->getKey() === (string) $auditor->getKey());
$report['assignmentPageRendered'] = str_contains($view->render(), '[DEMO] Information Technology');
$report['backupDirectory'] = $directory;
file_put_contents($directory.'/result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
foreach (['onlyCollegeChanged', 'allOtherRecordsUnchanged', 'indexesUnchanged', 'adminDepartmentSelectable', 'demoAuditorSelectable', 'assignmentPageRendered'] as $key) {
    if (!$report[$key]) { throw new RuntimeException('Verification failed: '.$key); }
}
