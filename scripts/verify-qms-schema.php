<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Expected the main development database.'); }
$migration = $db->migrations->findOne(['migration' => App\Services\QmsArchiveMigration::NAME]);
if (!$migration) { throw new RuntimeException('QMS migration has not been applied.'); }
$backup = json_decode(file_get_contents($migration['backupPath']), true, flags: JSON_THROW_ON_ERROR);
$plaintext = app(App\Services\ArchiveEncryptionService::class)->decrypt(base64_decode($backup['ciphertext']), $backup['iv'], $backup['tag']);
if (!hash_equals($backup['sha256'], hash('sha256', $plaintext))) { throw new RuntimeException('Backup checksum mismatch.'); }
$snapshot = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
$preserved = [];
foreach (['departments', 'users', 'archive_documents'] as $collection) {
    $preserved[$collection] = 0;
    foreach ($snapshot['documents'][$collection] as $json) {
        $old = MongoDB\BSON\toPHP(MongoDB\BSON\fromJSON($json));
        $current = $db->$collection->findOne(['_id' => $old->_id]);
        if (!$current) { throw new RuntimeException('An existing record was removed.'); }
        foreach (['departmentId', 'uploadedByUserId', 'assignedDepartmentIds', 'auditId', 'requestId', 'encryptedPath', 'checksum', 'password'] as $field) {
            if (isset($old->$field) && json_encode($old->$field) !== json_encode($current[$field] ?? null)) { throw new RuntimeException('An existing protected relationship or value changed.'); }
        }
        $preserved[$collection]++;
    }
}
$academic = $db->departments->find(['collegeName' => 'CET', 'unitType' => 'academic_department'])->toArray();
$names = array_map(fn ($unit) => $unit['departmentName'], $academic); $expected = array_values(config('qms.departments'));
sort($names); sort($expected);
if ($names !== $expected) { throw new RuntimeException('CET must contain exactly five approved academic departments.'); }
$report = ['database' => $db->getDatabaseName(), 'backupVerified' => true, 'preservedExistingRecords' => $preserved,
    'academicDepartments' => $names, 'collegeOffices' => $db->departments->countDocuments(['unitType' => 'college_office']),
    'provisionalCategories' => $db->qms_categories->countDocuments(['provisional' => true]),
    'verifiedArchives' => $db->archive_documents->countDocuments(['ownershipVerified' => true]),
    'archivesPendingOwnershipApproval' => $db->archive_documents->countDocuments(['ownershipVerified' => false]),
    'unresolvedUnits' => array_map(fn ($unit) => ['id' => (string) $unit['_id'], 'name' => $unit['departmentName']], $db->departments->find(['unitType' => 'legacy_unclassified'])->toArray()),
    'archiveIndexes' => array_map(fn ($index) => $index->getName(), iterator_to_array($db->archive_documents->listIndexes()))];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
