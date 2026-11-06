<?php

// Read-only diagnostics. No credentials, tokens, or evidence contents are printed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = \App\Models\User::where('email', 'demo.auditor.it@example.test')->firstOrFail();
$scope = app(\App\Services\RoleScopeService::class);
$db = \Illuminate\Support\Facades\DB::connection('mongodb')->getMongoDB();
$request = \Illuminate\Http\Request::create('/audits', 'GET');
$request->setUserResolver(fn () => $user);
\Illuminate\Support\Facades\Auth::setUser($user);
$controller = app(\App\Http\Controllers\Web\AuditController::class);
$view = $controller->index($request);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());
$rawAssignments = $db->selectCollection('audits')->countDocuments(['auditorId' => new \MongoDB\BSON\ObjectId((string) $user->getKey())]);
$query = (new \ReflectionMethod($controller, 'query'))->invoke($controller, $request)->toMql();
$containsAuditorObjectId = function (array $value) use (&$containsAuditorObjectId): bool {
    foreach ($value as $key => $item) {
        if ($key === 'auditorId' && $item instanceof \MongoDB\BSON\ObjectId) { return true; }
        if (is_array($item) && $containsAuditorObjectId($item)) { return true; }
    }
    return false;
};
echo json_encode([
    'name' => $user->fullName, 'id' => (string) $user->getKey(), 'keyType' => get_debug_type($user->getKey()),
    'roleId' => $user->roleId, 'isActive' => $user->isActive, 'departmentId' => (string) $user->departmentId,
    'assignedDepartmentIds' => array_map('strval', $scope->assignedDepartmentIds($user)),
    'departments' => $scope->departments($user)->get(['departmentName','collegeName','isActive'])->toArray(),
    'totalAudits' => $db->selectCollection('audits')->countDocuments(),
    'rawObjectIdAssignments' => $rawAssignments,
    'rawStringAssignments' => $db->selectCollection('audits')->countDocuments(['auditorId' => (string) $user->getKey()]),
    'visibleAssignments' => $view->getData()['audits']->total(),
    'listRendered' => str_contains($view->render(), 'No Assigned Audits Yet'),
    'metricsStatus' => $controller->metrics($request)->getStatusCode(),
    'assignmentQueryUsesObjectId' => $containsAuditorObjectId($query),
], JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), PHP_EOL;
