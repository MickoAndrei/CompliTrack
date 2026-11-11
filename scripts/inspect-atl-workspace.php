<?php
// Read-only inventory; never prints credentials, evidence, or personal contact details.
try {
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
$counts = [];
foreach (['users', 'roles', 'departments', 'audits', 'compliance_checks', 'archive_documents', 'notifications', 'audit_logs'] as $name) {
    $counts[$name] = $db->selectCollection($name)->countDocuments();
}
$admins = App\Models\User::where('roleId', 1)->get(['isActive'])->map(fn ($user) => [
    'active' => (bool) $user->isActive,
    'assignedReviews' => App\Models\Audit::whereIn('reviewerId', [new MongoDB\BSON\ObjectId((string) $user->getKey()), (string) $user->getKey()])->count(),
]);
$departments = App\Models\Department::where('collegeName', 'CET')->where('unitType', 'academic_department')
    ->get(['departmentCode', 'departmentName', 'ownershipVerified', 'isActive']);
$states = [];
foreach (['draft', 'ready', 'submitted', 'returned', 'accepted'] as $state) { $states[$state] = App\Models\Audit::where('status', $state)->count(); }
echo json_encode(['collections' => $counts, 'adminAccounts' => $admins, 'cetDepartments' => $departments->map(fn ($d) => [
    'code' => $d->departmentCode, 'name' => $d->departmentName, 'verified' => $d->ownershipVerified, 'active' => $d->isActive,
]), 'auditStates' => $states, 'carsWithDueDate' => App\Models\ComplianceCheck::where('result', 'non_compliant')->whereNotNull('dueDate')->count()], JSON_PRETTY_PRINT), PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Read-only inventory failed. Check MongoDB connectivity; no connection credentials are printed.'.PHP_EOL);
    exit(1);
}
