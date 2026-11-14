<?php

namespace App\Services;

use App\Models\{Audit, AuditLog, College, Department, Role, User};
use App\Support\MongoId;

class UserManagementPresenter
{
    public function summary(): array
    {
        $rows = User::raw(fn ($collection) => $collection->aggregate([
            ['$group' => ['_id' => null, 'total' => ['$sum' => 1],
                'active' => ['$sum' => ['$cond' => [['$eq' => ['$isActive', true]], 1, 0]]],
                'auditors' => ['$sum' => ['$cond' => [['$eq' => ['$roleId', 2]], 1, 0]]],
                'auditees' => ['$sum' => ['$cond' => [['$eq' => ['$roleId', 3]], 1, 0]]]]],
        ])->toArray());
        $row = $rows[0] ?? [];

        return ['total' => (int) ($row['total'] ?? 0), 'active' => (int) ($row['active'] ?? 0),
            'auditors' => (int) ($row['auditors'] ?? 0), 'auditees' => (int) ($row['auditees'] ?? 0)];
    }

    public function options(): array
    {
        $auditableIds = app(AuditAssignment::class)->units()->map(fn ($unit) => (string) $unit->getKey())->all();
        return [
            'departments' => Department::orderBy('departmentName')->get(['departmentName', 'departmentCode', 'unitType', 'collegeId', 'ownershipVerified', 'isActive'])
                ->map(fn ($unit) => ['_id' => (string) $unit->getKey(), 'name' => $unit->departmentName, 'code' => $unit->departmentCode,
                    'unitType' => $unit->unitType, 'collegeId' => (string) $unit->collegeId, 'verified' => (bool) $unit->ownershipVerified, 'active' => (bool) $unit->isActive,
                    'auditEligible' => in_array((string) $unit->getKey(), $auditableIds, true),
                    'authorizedCet' => $unit->unitType === 'academic_department' && $unit->ownershipVerified && $unit->isActive
                        && (config('qms.departments')[$unit->departmentCode] ?? null) === $unit->departmentName])->values()->all(),
            'colleges' => College::where('isActive', true)->where('collegeCode', 'CET')->get()->map(fn ($college) => [
                '_id' => (string) $college->getKey(), 'name' => $college->collegeName, 'code' => $college->collegeCode])->all(),
            'responsibilities' => config('qms.responsibilities'),
        ];
    }

    public function details(User $user): array
    {
        $access = app(QmsArchiveAccess::class);
        $id = MongoId::parse((string) $user->getKey());
        $values = [$id, (string) $id];
        $query = Audit::query();
        $role = (int) $user->roleId;
        $assigned = app(RoleScopeService::class)->assignedDepartmentIds($user);
        $auditIds = $role === 2 ? (clone $query)->whereIn('auditorId', $values)->whereIn('departmentId', $assigned)->get(['auditCode']) : collect();
        $activity = AuditLog::where(fn ($q) => $q->whereIn('userId', $values)->orWhere(fn ($q) => $q->where('entityType', 'user')->whereIn('entityId', $values)))
            ->orderBy('createdAt', 'desc')->limit(20)->get(['action', 'userName', 'createdAt']);
        return [
            'scope' => [
                'primaryRole' => ['Super Admin', 'Admin / Audit Team Leader', 'Auditor', 'Auditee'][$role],
                'rolePermissions' => Role::where('roleId', $role)->first()?->permissions ?? [],
                'policy' => match ($role) {
                    0 => 'System administration and user management. Super Admin cannot perform auditor checklist actions.',
                    1 => 'CET account and department management; review only audits assigned to this Admin as Audit Team Leader.',
                    2 => 'Only explicitly assigned audits and their permitted evidence. Department membership alone grants no access to unassigned audits.',
                    3 => 'Own compliance records, addressed evidence requests, and archives permitted by explicit organizational scope and document visibility.',
                },
                'active' => (bool) $user->isActive,
                'archiveUnits' => $access->organizationalUnits($user, true)->get(['departmentName'])->map(fn ($unit) => $unit->departmentName)->values(),
                'accessibleArchiveCount' => $access->documents($user)->count(),
                'assignedAudits' => $auditIds->map(fn ($audit) => ['id' => (string) $audit->getKey(), 'reference' => $audit->auditCode])->values(),
                'reviewAuditCount' => $role === 1 ? (clone $query)->whereIn('reviewerId', $values)
                    ->whereIn('departmentId', app(RoleScopeService::class)->departments($user)->get(['_id'])->pluck('_id')->all())->count() : 0,
            ],
            'activity' => $activity->map(fn ($entry) => ['action' => $entry->action, 'actor' => $entry->userName, 'at' => $entry->createdAt])->values(),
        ];
    }
}
