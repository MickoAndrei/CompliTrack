<?php
namespace App\Services;

use App\Http\Controllers\Api\V1\ArchiveController;
use App\Models\{ArchiveDocument, Department, QmsCategory, User};
use App\Support\MongoId;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MockArchiveImporter
{
    public function run(string $directory, User $actor, bool $apply = false): array
    {
        if (!$actor->isActive || (int) $actor->roleId !== 0) { throw new RuntimeException('An active Super Admin must own this import.'); }
        $root = realpath($directory);
        if (!$root) { throw new RuntimeException('Dataset directory not found.'); }
        $dataset = json_decode(file_get_contents($root.'/complitrack_cet_demo.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($dataset['isMock'] ?? false) !== true || ($dataset['departments'][0]['departmentCode'] ?? '') !== 'IT'
            || count($dataset['departments']) !== 1) { throw new RuntimeException('Expected the explicitly scoped mock IT dataset.'); }
        $unit = Department::where('departmentCode', 'CET-IT')->where('ownershipVerified', true)->where('isActive', true)->firstOrFail();
        $plan = []; $seen = [];
        foreach ($dataset['documents'] as $source) {
            if (($source['isMock'] ?? false) !== true || $source['departmentId'] !== $dataset['departments'][0]['departmentId']) {
                throw new RuntimeException('Document ownership or mock classification is unverified.');
            }
            $key = $dataset['datasetId'].':'.$source['documentId'];
            if (isset($seen[$key])) { throw new RuntimeException('Duplicate source document ID.'); } $seen[$key] = true;
            $path = realpath($root.'/'.$source['relativePath']);
            if (!$path || !str_starts_with(strtolower($path), strtolower($root.DIRECTORY_SEPARATOR)) || !is_file($path)
                || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'docx' || filesize($path) > 20 * 1024 * 1024) {
                throw new RuntimeException('Invalid source document path or size: '.$source['documentId']);
            }
            if (!hash_equals($source['sha256'], hash_file('sha256', $path))) { throw new RuntimeException('Source checksum mismatch: '.$source['documentId']); }
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) { throw new RuntimeException('Invalid Word package.'); }
            $valid = $zip->locateName('[Content_Types].xml') !== false && $zip->locateName('word/document.xml') !== false;
            $zip->close(); if (!$valid) { throw new RuntimeException('Invalid Word document structure.'); }
            $category = QmsCategory::where('collegeId', MongoId::parse((string) $unit->collegeId))->where('code', $this->category($source))->where('isActive', true)->firstOrFail();
            $existing = ArchiveDocument::where('sourceImportKey', $key)->first();
            if (!$existing) {
                $matches = ArchiveDocument::where('departmentId', MongoId::parse((string) $unit->getKey()))->where('checksum', $source['sha256'])->where('originalName', basename($path))->get();
                if ($matches->count() > 1) { throw new RuntimeException('Ambiguous existing file: '.$source['documentId']); }
                $existing = $matches->first();
            }
            if ($existing && ($existing->checksum !== $source['sha256'] || (string) $existing->departmentId !== (string) $unit->getKey())) {
                throw new RuntimeException('Existing import differs; create an authorized document version instead: '.$source['documentId']);
            }
            $restricted = in_array($source['category'], ['Audit plan', 'Audit checklist', 'Audit report', 'Corrective action request', 'Corrective action report log', 'Audit meeting record'], true);
            $plan[] = compact('source', 'key', 'path', 'category', 'existing', 'restricted');
        }
        $report = ['database' => DB::connection('mongodb')->getDatabaseName(), 'dataset' => $dataset['datasetId'], 'department' => $unit->departmentName,
            'documents' => count($plan), 'toImport' => count(array_filter($plan, fn ($item) => !$item['existing'])),
            'existing' => count(array_filter($plan, fn ($item) => (bool) $item['existing'])), 'restricted' => count(array_filter($plan, fn ($item) => $item['restricted'])),
            'applied' => false, 'records' => []];
        if (!$apply) { return $report; }
        $private = config('qms.import_directory', storage_path('app/private/mock-imports'));
        if (!is_dir($private) && !mkdir($private, 0700, true)) { throw new RuntimeException('Cannot create private import directory.'); }
        $lock = fopen($private.'/archive-import.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another archive import is running.'); }
        try {
            if ($report['toImport'] > 0) { $report['backup'] = $this->backup($private); }
            foreach ($plan as $item) {
                ['source' => $source, 'path' => $path, 'existing' => $archive] = $item;
                if (!$archive) {
                    $description = 'Fictional training document from '.$dataset['datasetId'].'. Source role: '.$source['ownerId'].'. Record date: '.$source['recordDate'].'. Revision: '.$source['revision'].'. Not an official approved QMS record.';
                    $request = Request::create('/api/v1/archives/upload', 'POST', [
                        'purpose' => 'qms', 'unitId' => (string) $unit->getKey(), 'categoryId' => (string) $item['category']->getKey(),
                        'title' => '[MOCK] '.$source['title'].(!empty($source['recordReference']) ? ' — '.$source['recordReference'] : ''),
                        'description' => $description, 'recordType' => 'file', 'documentReference' => $source['documentCode'],
                        'visibility' => $item['restricted'] ? 'restricted' : 'department', 'downloadAllowed' => true, 'copyAuthorized' => true,
                    ], [], ['file' => new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true)]);
                    $request->setUserResolver(fn () => $actor);
                    $response = app(ArchiveController::class)->upload($request)->getData(true);
                    $archive = ArchiveDocument::findOrFail($response['data']['_id']);
                    $archive->forceFill(['sourceImportKey' => $item['key'], 'sourceMetadata' => $source + ['datasetId' => $dataset['datasetId']],
                        'tags' => ['MOCK', $dataset['datasetId'], $source['documentId']]])->save();
                }
                $this->verify($archive);
                $report['records'][] = ['sourceId' => $source['documentId'], 'archiveId' => (string) $archive->getKey(),
                    'fileCode' => $archive->fileCode, 'visibility' => $archive->visibility, 'status' => $item['existing'] ? 'already_imported_verified' : 'imported_verified'];
            }
            $report['applied'] = true;
            file_put_contents($private.'/latest-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
            return $report;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function category(array $source): string
    {
        $path = $source['relativePath'];
        if (str_contains($path, '1.2_Approved_OBE_Syllabi') || str_contains($source['title'], 'Table of Specifications')) { return '1.0.2'; }
        if (str_contains($path, '1.3_Instructional_Materials')) { return '1.0.3'; }
        if (preg_match('/Laboratory|Maintenance|Fault/', $source['title'])) { return '2.0.2'; }
        if (str_starts_with($source['category'], 'Audit') || str_starts_with($source['category'], 'Corrective') || str_contains($source['documentId'], 'FM-05')) { return '3.0.3'; }
        return '1.0';
    }

    private function verify(ArchiveDocument $archive): void
    {
        if (!preg_match('/^archives\/[A-Za-z0-9._-]+\.enc$/', $archive->encryptedPath ?? '')) { throw new RuntimeException('Invalid archive storage path.'); }
        $path = rtrim(config('filesystems.disks.local.root'), '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archive->encryptedPath);
        if (!is_file($path)) { throw new RuntimeException('Imported archive content is missing.'); }
        $plaintext = app(ArchiveEncryptionService::class)->decrypt(file_get_contents($path), $archive->encryptionIv, $archive->encryptionTag);
        if (!hash_equals($archive->checksum, hash('sha256', $plaintext))) { throw new RuntimeException('Imported file checksum failed.'); }
    }

    private function backup(string $directory): string
    {
        $db = DB::connection('mongodb')->getMongoDB(); $snapshot = [];
        foreach (['archive_documents', 'counters', 'audit_logs'] as $collection) {
            $snapshot[$collection] = [];
            foreach ($db->$collection->find() as $document) { $snapshot[$collection][] = \MongoDB\BSON\toCanonicalExtendedJSON(\MongoDB\BSON\fromPHP($document)); }
        }
        $plaintext = json_encode(['database' => $db->getDatabaseName(), 'documents' => $snapshot], JSON_THROW_ON_ERROR);
        $encryption = app(ArchiveEncryptionService::class); $encrypted = $encryption->encrypt($plaintext);
        $payload = json_encode(['ciphertext' => base64_encode($encrypted['ciphertext']), 'iv' => $encrypted['iv'], 'tag' => $encrypted['tag'], 'sha256' => hash('sha256', $plaintext)], JSON_THROW_ON_ERROR);
        $path = $directory.'/before-import-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.enc.json';
        if (file_put_contents($path, $payload, LOCK_EX) !== strlen($payload)) { throw new RuntimeException('Unable to save import backup.'); }
        $saved = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!hash_equals($saved['sha256'], hash('sha256', $encryption->decrypt(base64_decode($saved['ciphertext']), $saved['iv'], $saved['tag'])))) { throw new RuntimeException('Import backup verification failed.'); }
        return $path;
    }
}
