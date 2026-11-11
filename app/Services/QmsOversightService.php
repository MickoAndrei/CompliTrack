<?php
namespace App\Services;
use App\Models\{Audit, ComplianceCheck, User};
class QmsOversightService
{
    public function summary(User $actor): ?array
    {
        if (!$actor->isActive || (int) $actor->roleId !== 3 || !in_array($actor->archiveResponsibility,
            ['department_head', 'department_secretary', 'college_dean', 'college_secretary', 'cluster_administrator'], true)) { return null; }
        $access = app(QmsArchiveAccess::class);
        $units = $access->organizationalUnits($actor, true)->get(['departmentName']);
        $ids = $access->ids($units->map(fn ($unit) => $unit->getKey())->all());
        $audits = Audit::whereIn('departmentId', $ids)->get(['auditCode', 'departmentName', 'status']);
        $checks = ComplianceCheck::whereIn('departmentId', $ids)->get(['correctiveStatus']);
        return ['responsibility' => ucwords(str_replace('_', ' ', $actor->archiveResponsibility)), 'units' => $units->count(), 'audits' => $audits->count(),
            'openCars' => $checks->whereIn('correctiveStatus', ['open', 'in_progress'])->count(),
            'records' => $audits->take(8)->map(fn ($audit) => ['reference' => $audit->auditCode, 'department' => $audit->departmentName,
                'status' => match ($audit->status) { 'accepted' => 'Completed', 'returned' => 'Returned for revision', 'ready' => 'In progress', default => ucfirst($audit->status) }])->values()];
    }
}
