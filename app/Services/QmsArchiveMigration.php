<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class QmsArchiveMigration
{
    public const NAME = '2026_11_08_qms_archives_v1';

    public function run(bool $apply = false): array
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $units = $db->departments->find()->toArray();
        $proposal = ['database' => $db->getDatabaseName(), 'canonical' => [], 'unresolved' => [], 'applied' => false];
        $selected = [];
        foreach (config('qms.departments') as $code => $name) {
            $candidates = array_values(array_filter($units, fn ($unit) => $this->isCet($unit)
                && !str_starts_with($unit['departmentName'] ?? '', '[DEMO]')
                && in_array($unit['departmentName'] ?? '', [$name, $name === 'Agricultural and Biosystem Engineering' ? 'Agricultural and Biosystems Engineering' : $name], true)));
            if (count($candidates) > 1) { throw new \RuntimeException('Multiple canonical candidates for '.$name.'. Resolve ownership with approval before applying.'); }
            $existing = $candidates[0] ?? null;
            if (!$existing && $db->departments->countDocuments(['departmentCode' => $code])) {
                throw new \RuntimeException('Department code collision for '.$code.'. No records were modified.');
            }
            $selected[$code] = $existing;
            $proposal['canonical'][] = ['name' => $name, 'id' => $existing ? (string) $existing['_id'] : null, 'action' => $existing ? 'preserve_id_and_standardize' : 'create'];
        }
        $selectedIds = array_filter(array_map(fn ($unit) => $unit ? (string) $unit['_id'] : null, $selected));
        foreach ($units as $unit) {
            if ($this->isCet($unit) && !in_array((string) $unit['_id'], $selectedIds, true)
                && ($unit['unitType'] ?? '') !== 'college_office') {
                $proposal['unresolved'][] = ['id' => (string) $unit['_id'], 'name' => $unit['departmentName'], 'action' => 'preserve_relationships_flag_for_review'];
            }
        }
        if (!$apply) { return $proposal; }
        if ($db->migrations->findOne(['migration' => self::NAME])) { return $proposal + ['alreadyApplied' => true]; }
        // Back up every affected collection, including index definitions, before the first write.
        $snapshot = []; $indexes = [];
        $existingCollections = iterator_to_array($db->listCollectionNames());
        foreach (['departments', 'users', 'archive_documents', 'colleges', 'qms_categories', 'migrations'] as $collection) {
            $snapshot[$collection] = [];
            foreach ($db->$collection->find() as $document) {
                $snapshot[$collection][] = \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document));
            }
            $indexes[$collection] = [];
            if (in_array($collection, $existingCollections, true)) {
                foreach ($db->$collection->listIndexes() as $index) { $indexes[$collection][] = $index->__debugInfo(); }
            }
        }
        $plaintext = json_encode(['database' => $db->getDatabaseName(), 'migration' => self::NAME, 'documents' => $snapshot, 'indexes' => $indexes], JSON_THROW_ON_ERROR);
        $encryption = app(ArchiveEncryptionService::class);
        $encrypted = $encryption->encrypt($plaintext);
        if (!hash_equals(hash('sha256', $plaintext), hash('sha256', $encryption->decrypt($encrypted['ciphertext'], $encrypted['iv'], $encrypted['tag'])))) {
            throw new \RuntimeException('Migration backup verification failed.');
        }
        $directory = config('qms.backup_directory', storage_path('app/private/qms-migration-backups'));
        if (!is_dir($directory)) { mkdir($directory, 0700, true); }
        $backup = $directory.'/'.self::NAME.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.enc.json';
        $payload = json_encode(['ciphertext' => base64_encode($encrypted['ciphertext']), 'iv' => $encrypted['iv'], 'tag' => $encrypted['tag'], 'sha256' => hash('sha256', $plaintext)], JSON_THROW_ON_ERROR);
        if (file_put_contents($backup, $payload, LOCK_EX) !== strlen($payload)) { throw new \RuntimeException('Could not save migration backup.'); }
        $now = new UTCDateTime();
        $college = $db->colleges->findOne(['collegeCode' => 'CET']);
        $collegeId = $college['_id'] ?? new ObjectId();
        $db->colleges->updateOne(['_id' => $collegeId], ['$setOnInsert' => ['collegeCode' => 'CET', 'collegeName' => config('qms.collegeName'), 'campusName' => config('qms.campus'), 'isActive' => true, 'createdAt' => $now]], ['upsert' => true]);
        foreach (config('qms.departments') as $code => $name) {
            $unit = $selected[$code];
            $id = $unit['_id'] ?? new ObjectId();
            $db->departments->updateOne(['_id' => $id], ['$set' => ['departmentName' => $name, 'collegeName' => 'CET', 'collegeId' => $collegeId, 'unitType' => 'academic_department', 'ownershipVerified' => true],
                '$setOnInsert' => ['departmentCode' => $code, 'campusName' => 'Claveria', 'isActive' => true, 'createdAt' => $now]], ['upsert' => true]);
        }
        $dean = $db->departments->findOne(['collegeId' => $collegeId, 'unitType' => 'college_office', 'departmentName' => 'Office of the Dean']);
        $db->departments->updateOne(['_id' => $dean['_id'] ?? new ObjectId()], ['$setOnInsert' => ['departmentCode' => 'CET-DEAN', 'departmentName' => 'Office of the Dean', 'collegeName' => 'CET', 'collegeId' => $collegeId, 'campusName' => 'Claveria', 'unitType' => 'college_office', 'ownershipVerified' => true, 'isActive' => true, 'createdAt' => $now]], ['upsert' => true]);
        foreach ($proposal['unresolved'] as $unit) {
            $db->departments->updateOne(['_id' => new ObjectId($unit['id'])], ['$set' => ['unitType' => 'legacy_unclassified', 'ownershipVerified' => false]]);
        }
        foreach ($db->archive_documents->find() as $archive) {
            $department = null;
            try { $department = $db->departments->findOne(['_id' => new ObjectId((string) ($archive['departmentId'] ?? ''))]); } catch (\Throwable) {}
            $verified = ($department['ownershipVerified'] ?? false) === true;
            $changes = ['ownershipVerified' => $verified];
            if ($verified) { $changes['collegeId'] = $department['collegeId']; }
            // Never alter existing owners, permissions, files, or linked audit/CAR IDs.
            $db->archive_documents->updateOne(['_id' => $archive['_id']], ['$set' => $changes]);
        }
        foreach (config('qms.categories') as $group) {
            $parent = $db->qms_categories->findOne(['collegeId' => $collegeId, 'code' => $group['code']]);
            $parentId = $parent['_id'] ?? new ObjectId();
            $db->qms_categories->updateOne(['_id' => $parentId], ['$setOnInsert' => ['collegeId' => $collegeId, 'code' => $group['code'], 'name' => $group['name'], 'unitType' => $group['scope'], 'parentId' => null, 'provisional' => true, 'isActive' => true]], ['upsert' => true]);
            foreach ($group['children'] as $index => $name) {
                $db->qms_categories->updateOne(['collegeId' => $collegeId, 'code' => $group['code'].'.'.($index + 1)], ['$setOnInsert' => ['name' => $name, 'unitType' => $group['scope'], 'parentId' => $parentId, 'provisional' => true, 'isActive' => true]], ['upsert' => true]);
            }
        }
        $this->indexes();
        $db->migrations->insertOne(['migration' => self::NAME, 'batch' => 1, 'appliedAt' => $now, 'backupPath' => $backup, 'unresolvedUnitIds' => array_column($proposal['unresolved'], 'id')]);
        $proposal['applied'] = true;
        $proposal['backup'] = $backup;
        return $proposal;
    }

    public function indexes(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $db->colleges->createIndex(['collegeCode' => 1], ['unique' => true]);
        $db->departments->createIndex(['collegeId' => 1, 'unitType' => 1, 'ownershipVerified' => 1]);
        $db->qms_categories->createIndex(['collegeId' => 1, 'code' => 1], ['unique' => true]);
        $db->archive_documents->createIndex(['departmentId' => 1, 'categoryId' => 1, 'createdAt' => -1]);
        $db->archive_documents->createIndex(['sharedAuditIds' => 1, 'departmentId' => 1]);
        $db->archive_documents->createIndex(['accessUserIds' => 1, 'departmentId' => 1]);
        $db->archive_documents->createIndex(['versionGroupId' => 1, 'versionNumber' => 1], ['unique' => true, 'partialFilterExpression' => ['versionGroupId' => ['$type' => 'objectId']]]);
    }

    private function isCet($unit): bool
    {
        return in_array(strtolower(trim($unit['collegeName'] ?? '')), ['cet', 'college of engineering and technology', 'collage of engineering and technology'], true);
    }
}
