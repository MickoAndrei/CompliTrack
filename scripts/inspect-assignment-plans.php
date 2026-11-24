<?php

// Read-only inventory; does not print credentials, file contents or personal data.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
$units = \App\Models\Department::where('collegeName', 'CET')->get(['departmentName', 'departmentCode', 'unitType', 'ownershipVerified', 'isActive']);
$admins = \App\Models\User::where('roleId', 1)->where('isActive', true)->get();
$available = [];
foreach ($admins as $admin) {
    $request = \Illuminate\Http\Request::create('/audits');
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);
    $available[] = app(\App\Services\AuditAssignment::class)->plans($admin)->count();
}
echo json_encode([
    'activeAdminCount' => $admins->count(), 'authorizedPlanCountsPerAdmin' => $available,
    'auditCount' => $db->selectCollection('audits')->countDocuments(),
    'archiveCount' => $db->selectCollection('archive_documents')->countDocuments(),
    'archivesWithAssignmentAuthorization' => $db->selectCollection('archive_documents')->countDocuments(['assignmentAuthorization' => ['$exists' => true]]),
    'cetUnits' => $units->map(fn ($u) => $u->only(['departmentName', 'departmentCode', 'unitType', 'ownershipVerified', 'isActive']))->all(),
], JSON_PRETTY_PRINT), PHP_EOL;
