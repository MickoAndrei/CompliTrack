<?php

namespace Tests\Support;

use App\Models\{ArchiveDocument, Department, User};

trait AuthorizesAuditPlan
{
    private function authorizedPlan(User $admin, User $auditor, Department $unit, string $scope, string $criteria): string
    {
        // Isolated test records only: the production application never invents approvals.
        $unit->update(['unitType' => 'academic_department', 'departmentCode' => 'CET-IT', 'departmentName' => 'Information Technology']);
        $home = Department::firstOrCreate(['departmentCode' => 'CET-ENV', 'collegeName' => 'CET'], [
            'departmentName' => 'Environmental Engineering', 'unitType' => 'academic_department', 'ownershipVerified' => true, 'isActive' => true]);
        $auditor->update(['departmentId' => $home->getKey()]);
        $plan = ArchiveDocument::create(['title' => 'Isolated authorized audit plan', 'ownershipVerified' => true,
            'departmentId' => $unit->getKey(), 'uploadedByUserId' => $admin->getKey(), 'recordType' => 'physical_reference']);
        $at = now()->utc()->toIso8601String();
        $header = ['unitIds' => [(string) $unit->getKey()], 'reviewerIds' => [(string) $admin->getKey()], 'scope' => $scope, 'criteria' => $criteria,
            'scheduleStart' => '2020-01-01', 'scheduleEnd' => '2030-12-31', 'procedureReference' => 'Isolated test independence procedure'];
        $plan->forceFill(['auditPlan' => ['status' => 'accepted', 'version' => 1, 'approvedBy' => $admin->getKey(), 'approvedAt' => $at, 'header' => $header,
            'documentVersion' => $plan->versionNumber ?: 1, 'documentChecksum' => $plan->checksum],
            'assignmentAuthorization' => $header + ['status' => 'authorized', 'approvedBy' => $admin->getKey(), 'approvedAt' => $at, 'approvalVersion' => 1]])->save();
        return (string) $plan->getKey();
    }
}
