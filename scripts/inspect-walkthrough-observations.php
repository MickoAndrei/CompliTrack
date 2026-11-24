<?php

// Read-only inspection of only the two record references supplied in the walkthrough.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $client = new MongoDB\Client(config('database.connections.mongodb.dsn'), ['serverSelectionTimeoutMS' => 5000, 'connectTimeoutMS' => 5000]);
    $db = $client->selectDatabase(config('database.connections.mongodb.database'));
    $check = $db->selectCollection('compliance_checks')->findOne(['checkCode' => 'DEMO-CHK-ENG-PROGRESS']);
    $audit = $db->selectCollection('audits')->findOne(['auditCode' => 'IQA-2026-0002']);
    $permissions = $db->selectCollection('roles')->findOne(['roleId' => 2]);
    $files = $check ? $db->selectCollection('archive_documents')->find(['_id' => ['$in' => array_map(fn ($id) => new MongoDB\BSON\ObjectId((string) $id), iterator_to_array($check['linkedArchiveIds'] ?? new ArrayIterator()))]])->toArray() : [];
    echo json_encode(['check' => $check ? ['storedStatus' => $check['correctiveStatus'] ?? null,
        'historyStatuses' => array_column((array) ($check['statusHistory'] ?? []), 'status'),
        'evidenceResponses' => count($check['evidenceResponses'] ?? []), 'linkedFiles' => count($check['linkedArchiveIds'] ?? []),
        'linkedFileMetadata' => array_map(fn ($file) => ['auditeeUploaded' => (string) ($file['uploadedByUserId'] ?? '') === (string) $check['auditeeId'],
            'sameDepartment' => (string) ($file['departmentId'] ?? '') === (string) $check['departmentId']], $files),
        'auditorVerifyPermission' => in_array('cars.verify', (array) ($permissions['permissions'] ?? []), true),
        'auditorClosePermission' => in_array('cars.close', (array) ($permissions['permissions'] ?? []), true)] : 'Not found',
        'audit' => $audit ? ['status' => $audit['status'] ?? null, 'requestStatuses' => array_map(fn ($r) => $r['status'] ?? null, iterator_to_array($audit['evidenceRequests'] ?? new ArrayIterator())),
            'items' => count($audit['items'] ?? [])] : 'Not found'], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    // Never echo a connection exception: it can include the configured private URI.
    fwrite(STDERR, "Read-only inspection could not connect to the configured database. No data changed.\n");
    exit(1);
}
