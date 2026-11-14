<?php

namespace App\Services;

use App\Models\{College, Department};
use Illuminate\Validation\ValidationException;

class UserOrganization
{
    public function validate(int $role, ?string $responsibility, array $unitIds, array $collegeIds, ?string $cluster = null): array
    {
        $fail = fn ($field, $message) => throw ValidationException::withMessages([$field => $message]);
        if ($responsibility !== null && !in_array($responsibility, config('qms.responsibilities'), true)) {
            $fail('archiveResponsibility', 'Select a valid position.');
        }
        $access = app(QmsArchiveAccess::class);
        $units = $access->ids($unitIds);
        $colleges = $access->ids($collegeIds);
        if (count($units) !== count($unitIds) || count($colleges) !== count($collegeIds)) {
            $fail('archiveUnitIds', 'Organizational assignments must contain unique valid IDs.');
        }
        if (Department::whereIn('_id', $units)->where('ownershipVerified', true)->where('isActive', true)
            ->where('unitType', '!=', 'legacy_unclassified')->count() !== count($units)) {
            $fail('archiveUnitIds', 'Select active, verified departments or offices.');
        }
        if (College::whereIn('_id', $colleges)->where('isActive', true)->where('collegeCode', 'CET')->count() !== count($colleges)) {
            $fail('archiveCollegeIds', 'Select an active CET college.');
        }
        if ($responsibility === 'cluster_administrator' && !array_key_exists($cluster ?? '', config('qms.clusters'))) {
            $fail('archiveCluster', 'Select Cluster A (Claveria Group) or Cluster B (Cagayan de Oro/Main Group).');
        }
        if ($responsibility !== 'cluster_administrator' && $cluster !== null) {
            $fail('archiveCluster', 'A cluster can only be assigned to a Cluster Administrator.');
        }
        if ($responsibility === 'cluster_administrator' && ($units !== [] || $colleges !== [])) {
            $fail('archiveCluster', 'Cluster Administrators use a cluster assignment instead of a college or department.');
        }
        $collegeRole = in_array($responsibility, ['college_dean', 'college_secretary'], true);
        if ($collegeRole ? count($colleges) !== 1 || $units !== [] : $colleges !== []) {
            $fail('archiveCollegeIds', 'College positions require one college and no department assignment.');
        }
        if (in_array($responsibility, ['department_head', 'department_secretary'], true)) {
            $unit = count($units) === 1 ? Department::whereIn('_id', $units)->where('unitType', 'academic_department')->where('collegeName', 'CET')->first() : null;
            if (!$unit) {
                $fail('archiveUnitIds', 'Select one active, verified CET academic department for this position.');
            }
        }
        if ($responsibility === null && ($units !== [] || $colleges !== [])) {
            $fail('archiveResponsibility', 'Select a position for the organizational assignment.');
        }
        return ['archiveResponsibility' => $responsibility, 'archiveUnitIds' => $units, 'archiveCollegeIds' => $colleges, 'archiveCluster' => $cluster];
    }
}
