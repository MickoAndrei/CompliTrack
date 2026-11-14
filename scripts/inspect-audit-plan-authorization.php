<?php

// Read-only inventory. No account changes, approval grants, or archive updates.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'roles' => \App\Models\Role::orderBy('roleId')->get()->map(fn ($role) => [
        'roleId' => $role->roleId, 'explicitApprovalPermission' => in_array(\App\Services\AuditPlanPermissions::APPROVE, $role->permissions ?? [], true),
        'otherPermissionsPreserved' => array_values(array_filter($role->permissions ?? [], fn ($permission) => $permission !== \App\Services\AuditPlanPermissions::APPROVE)),
    ]),
    'users' => \App\Models\User::count(), 'audits' => \App\Models\Audit::count(),
    'archives' => \App\Models\ArchiveDocument::count(),
    'workflowPlans' => \App\Models\ArchiveDocument::whereNotNull('auditPlan')->count(),
    'legacyAuthorizationRecords' => \App\Models\ArchiveDocument::whereNotNull('assignmentAuthorization')->count(),
    'databaseWrites' => 0,
], JSON_PRETTY_PRINT), PHP_EOL;
