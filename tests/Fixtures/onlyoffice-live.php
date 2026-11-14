<?php
// Explicitly invoked integration fixture. Never loaded by application routes.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{ArchiveDocument, Department, QmsCategory, User};
use App\Services\{ArchiveEncryptionService, QmsArchiveMigration};
use Illuminate\Support\Facades\{DB, File};

$fixturePath = storage_path('framework/testing/onlyoffice-live.json');
if (($argv[1] ?? '') === 'verify') {
    $fixture = json_decode(file_get_contents($fixturePath), true);
    if (!preg_match('/^ct_oo_live_[a-f0-9]{10}$/', $fixture['database'])) { throw new RuntimeException('Unsafe database.'); }
    config(['database.connections.mongodb.database' => $fixture['database'], 'services.complitrack.encryption_key' => base64_encode(str_repeat('l', 32))]); DB::purge('mongodb');
    $directory = storage_path('framework/testing/'.$fixture['database']);
    $read = function ($document) use ($directory) {
        if (!preg_match('~^archives/[A-Za-z0-9._-]+\.enc$~', $document->encryptedPath)) { throw new RuntimeException('Unsafe test path.'); }
        $bytes = app(ArchiveEncryptionService::class)->decrypt(file_get_contents($directory.'/'.$document->encryptedPath), $document->encryptionIv, $document->encryptionTag);
        if (!hash_equals($document->checksum, hash('sha256', $bytes))) { throw new RuntimeException('Checksum mismatch.'); }
        return $bytes;
    };
    foreach ($fixture['documents'] as $extension => $id) {
        $source = ArchiveDocument::findOrFail($id); $original = $read($source);
        if ($original !== file_get_contents(storage_path('framework/testing/onlyoffice-templates/new.'.$extension))) { throw new RuntimeException('Original changed.'); }
        $latest = ArchiveDocument::where('originalName', 'isolated.'.$extension)->orderBy('versionNumber', 'desc')->firstOrFail();
        if ($latest->versionNumber < 2 || (string) $latest->departmentId !== (string) $source->departmentId || (string) $latest->uploadedByUserId !== (string) $source->uploadedByUserId) { throw new RuntimeException('Missing version or changed ownership.'); }
        $temporary = tempnam(sys_get_temp_dir(), 'ct-live-verify-'); file_put_contents($temporary, $read($latest));
        try {
            $zip = new ZipArchive(); if ($zip->open($temporary) !== true) { throw new RuntimeException('Invalid saved package.'); }
            $text = ''; $slides = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) { $name = $zip->getNameIndex($i); if (str_ends_with($name, '.xml')) { $text .= strip_tags($zip->getFromIndex($i)); } if (preg_match('~^ppt/slides/slide[0-9]+\.xml$~', $name)) { $slides++; } }
            $zip->close();
            if ($extension === 'pptx' ? $slides < 2 : !str_contains($text, 'CompliTrack isolated')) { throw new RuntimeException('Actual editing content was not persisted for '.$extension); }
        } finally { unlink($temporary); }
        echo 'PASS '.strtoupper($extension).': original bytes preserved, saved version '.$latest->versionNumber.' decrypted and edited content verified.'."\n";
    }
    exit;
}
if (($argv[1] ?? '') === 'inspect') {
    $fixture = json_decode(file_get_contents($fixturePath), true);
    if (!preg_match('/^ct_oo_live_[a-f0-9]{10}$/', $fixture['database'])) { throw new RuntimeException('Unsafe database.'); }
    config(['database.connections.mongodb.database' => $fixture['database']]); DB::purge('mongodb');
    foreach (ArchiveDocument::all() as $document) {
        echo json_encode(['name' => $document->originalName, 'version' => $document->versionNumber, 'editorStatus' => ($document->onlyofficeWorkspace ?? [])['lastStatus'] ?? null,
            'workspaceCurrentVersion' => !empty(($document->onlyofficeWorkspace ?? [])['current']) ? ArchiveDocument::find($document->onlyofficeWorkspace['current'])?->versionNumber : null,
            'closed' => ($document->onlyofficeWorkspace ?? [])['closed'] ?? null,
            'error' => ($document->onlyofficeWorkspace ?? [])['lastError'] ?? null])."\n";
    }
    exit;
}
if (($argv[1] ?? '') === 'cleanup') {
    $fixture = json_decode(file_get_contents($fixturePath), true);
    if (!preg_match('/^ct_oo_live_[a-f0-9]{10}$/', $fixture['database'])) { throw new RuntimeException('Refusing unsafe cleanup.'); }
    config(['database.connections.mongodb.database' => $fixture['database']]); DB::purge('mongodb');
    $db = DB::connection('mongodb')->getMongoDB();
    if ($db->getDatabaseName() !== $fixture['database']) { throw new RuntimeException('Database mismatch.'); }
    $db->drop();
    $directory = storage_path('framework/testing/'.$fixture['database']);
    if (is_dir($directory) && str_starts_with(realpath($directory), realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR)) { File::deleteDirectory($directory); }
    unlink($fixturePath); echo "Isolated ONLYOFFICE records and encrypted test files removed.\n"; exit;
}
if (file_exists($fixturePath)) { throw new RuntimeException('Clean up the existing isolated fixture before generating another.'); }
$database = 'ct_oo_live_'.bin2hex(random_bytes(5)); $directory = storage_path('framework/testing/'.$database);
File::ensureDirectoryExists($directory.'/archives'); File::ensureDirectoryExists($directory.'/sessions');
config(['database.connections.mongodb.database' => $database, 'filesystems.disks.local.root' => $directory,
    'qms.backup_directory' => $directory.'/backups', 'services.complitrack.encryption_key' => base64_encode(str_repeat('l', 32))]); DB::purge('mongodb');
app(QmsArchiveMigration::class)->run(true);
$unit = Department::where('departmentCode', 'CET-IT')->firstOrFail();
$category = QmsCategory::where('unitType', 'academic_department')->firstOrFail();
$users = [];
foreach (['chair' => 'department_head', 'secretary' => 'department_secretary', 'reader' => 'faculty_staff'] as $name => $position) {
    $users[$name] = (string) User::create(['fullName' => 'ONLYOFFICE isolated '.$name, 'email' => $name.'@onlyoffice.example.test',
        'password' => bcrypt(bin2hex(random_bytes(32))), 'roleId' => 3, 'isActive' => true, 'departmentId' => $unit->getKey(), 'archiveResponsibility' => $position])->getKey();
}
$documents = [];
foreach (['docx','xlsx','pptx'] as $extension) {
    $bytes = file_get_contents(storage_path('framework/testing/onlyoffice-templates/new.'.$extension));
    $encrypted = app(ArchiveEncryptionService::class)->encrypt($bytes);
    file_put_contents($directory.'/archives/live-'.$extension.'.enc', $encrypted['ciphertext']);
    $documents[$extension] = (string) ArchiveDocument::create(['fileCode' => 'OO-LIVE-'.$extension, 'title' => 'Isolated '.$extension.' editing test',
        'originalName' => 'isolated.'.$extension, 'recordType' => 'file', 'departmentId' => $unit->getKey(), 'collegeId' => $unit->collegeId,
        'categoryId' => $category->getKey(), 'ownershipVerified' => true, 'uploadedByUserId' => $users['chair'], 'visibility' => 'department',
        'versionNumber' => 1, 'encryptedPath' => 'archives/live-'.$extension.'.enc', 'checksum' => hash('sha256', $bytes), 'isEncrypted' => true,
        'encryptionIv' => $encrypted['iv'], 'encryptionTag' => $encrypted['tag'], 'encryptionKeyId' => $encrypted['keyId']])->getKey();
}
file_put_contents($fixturePath, json_encode(['database' => $database, 'users' => $users, 'documents' => $documents, 'secret' => bin2hex(random_bytes(32))]));
echo "Isolated ONLYOFFICE fixture prepared; no application database records changed.\n";
