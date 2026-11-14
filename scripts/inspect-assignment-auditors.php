<?php

// Read-only eligibility diagnostics. No personal names, credentials or record writes.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$assignment = app(\App\Services\AuditAssignment::class);
$scope = app(\App\Services\RoleScopeService::class);
if (isset($argv[1])) {
    $auditor = \App\Models\User::where('userCode', $argv[1])->firstOrFail();
    $describe = function ($id) {
        $unit = $id ? \App\Models\Department::find($id) : null;
        return $unit ? ['name' => $unit->departmentName, 'type' => $unit->unitType,
            'college' => $unit->collegeName, 'active' => (bool) $unit->isActive,
            'verified' => (bool) $unit->ownershipVerified] : ['missing' => true];
    };
    echo json_encode(['userCode' => $auditor->userCode, 'home' => $describe($auditor->departmentId),
        'authorizedUnits' => array_map($describe, $auditor->assignedDepartmentIds ?? []), 'databaseWrites' => 0], JSON_PRETTY_PRINT), PHP_EOL;
    exit;
}
$rows = [];
foreach (\App\Models\User::where('roleId', 1)->where('isActive', true)->get() as $actor) {
    $request = \Illuminate\Http\Request::create('/audits'); $request->setUserResolver(fn () => $actor); $app->instance('request', $request);
    $auditors = $scope->users($actor)->where('roleId', 2)->where('isActive', true)->get();
    $rows[] = ['scopedActiveAuditors' => $auditors->count(), 'authorizedPlans' => $assignment->plans($actor)->count(),
        'units' => $assignment->units()->map(function ($unit) use ($assignment, $auditors) {
            $id = (string) $unit->getKey();
            return ['unit' => $unit->departmentName, 'eligible' => $auditors->filter(fn ($auditor) => $assignment->eligible($auditor, $unit))->count(),
                'homeUnitConflicts' => $auditors->filter(fn ($auditor) => (string) $auditor->departmentId === $id)->count(),
                'notAuthorizedForUnit' => $auditors->filter(fn ($auditor) => !in_array($id, array_map('strval', $auditor->assignedDepartmentIds ?? []), true))->count(),
                'recordedConflicts' => $auditors->filter(fn ($auditor) => in_array($id, array_map('strval', $auditor->conflictDepartmentIds ?? []), true))->count()];
        })->values()];
}
echo json_encode(['activeAuditors' => \App\Models\User::where('roleId', 2)->where('isActive', true)->count(), 'atlScopes' => $rows, 'databaseWrites' => 0], JSON_PRETTY_PRINT), PHP_EOL;
