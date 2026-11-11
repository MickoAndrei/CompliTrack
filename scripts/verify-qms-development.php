<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
    if ($db->getDatabaseName() !== 'complitrack') { throw new RuntimeException('Expected complitrack.'); }
    $report = json_decode(file_get_contents(storage_path('app/private/qms-development/report.json')), true, flags: JSON_THROW_ON_ERROR);
    $backup = json_decode(file_get_contents($report['backup']), true, flags: JSON_THROW_ON_ERROR);
    $encryption = app(App\Services\ArchiveEncryptionService::class);
    $plaintext = $encryption->decrypt(base64_decode($backup['ciphertext']), $backup['iv'], $backup['tag']);
    if (!hash_equals($backup['sha256'], hash('sha256', $plaintext))) { throw new RuntimeException('Backup failed verification.'); }
    $snapshot = json_decode($plaintext, true, flags: JSON_THROW_ON_ERROR);
    $preserved = [];
    foreach (['users', 'compliance_checks', 'roles'] as $name) {
        $preserved[$name] = 0;
        foreach ($snapshot['documents'][$name] as $json) {
            $old = MongoDB\BSON\toPHP(MongoDB\BSON\fromJSON($json));
            $current = $db->$name->findOne(['_id' => $old->_id]);
            if (!$current || MongoDB\BSON\toCanonicalExtendedJSON(MongoDB\BSON\fromPHP($current)) !== $json) { throw new RuntimeException('A pre-existing account, role, or CAR was changed.'); }
            $preserved[$name]++;
        }
    }
    $files = 0; $metadata = 0;
    foreach ($report['documents'] as $entry) {
        $archive = App\Models\ArchiveDocument::findOrFail($entry['id']);
        if (!$archive->ownershipVerified) { throw new RuntimeException('Unverified archive in seed manifest.'); }
        if ($archive->recordType !== 'file') { $metadata++; continue; }
        if (!preg_match('/^archives\/[A-Za-z0-9._-]+\.enc$/', $archive->encryptedPath)) { throw new RuntimeException('Invalid private file path.'); }
        $path = rtrim(config('filesystems.disks.local.root'), '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $archive->encryptedPath);
        $bytes = $encryption->decrypt(file_get_contents($path), $archive->encryptionIv, $archive->encryptionTag);
        if (!hash_equals($archive->checksum, hash('sha256', $bytes))) { throw new RuntimeException('Archive checksum failed.'); }
        $files++;
    }
    $atl = App\Models\User::findOrFail($report['atl']['id']);
    foreach (App\Models\Audit::where('mockSeedKey', 'regex', '/^'.preg_quote(App\Services\QmsDevelopmentData::KEY, '/').':/')->get() as $audit) {
        if ((string) $audit->reviewerId !== (string) $atl->getKey()) { throw new RuntimeException('Wrong seeded ATL reference.'); }
        if (in_array($audit->status, ['submitted', 'returned', 'accepted'], true)) { App\Services\AuditChecklist::validateSubmission($audit->items); }
    }
    echo json_encode(['database' => $db->getDatabaseName(), 'backupVerified' => true, 'existingRecordsUnchanged' => $preserved,
        'atl' => $report['atl'], 'primaryAdminCount' => App\Models\User::where('roleId', 1)->count(), 'createdAccounts' => $report['createdAccounts'],
        'reusedAccounts' => $report['reusedAccounts'], 'verifiedEncryptedFiles' => $files, 'metadataOnlyRecords' => $metadata,
        'seededAudits' => count($report['audits']), 'counts' => app(App\Services\QmsDevelopmentData::class)->counts()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) { fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL); exit(1); }
