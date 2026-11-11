<?php

namespace App\Services;

use App\Models\{College, Department};
use Illuminate\Validation\ValidationException;

class UserOrganization
{
    public function validate(int $role, ?string $responsibility, array $unitIds, array $collegeIds): array
    {
        $fail = fn ($field, $message) => throw ValidationException::withMessages([$field => $message]);
        if ($responsibility !== null && !in_array($responsibility, config('qms.responsibilities'), true)) {
            $fail('archiveResponsibility', 'Select a valid organizational responsibility.');
        }
        if ($role === 2 && ($responsibility !== null || $unitIds !== [] || $collegeIds !== [])) {
            $fail('archiveResponsibility', 'Auditor access comes from assigned audits and explicitly shared evidence, not organizational responsibilities.');
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
        $collegeRole = in_array($responsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true);
        if ($collegeRole ? count($colleges) !== 1 || $units !== [] : $colleges !== []) {
            $fail('archiveCollegeIds', 'College responsibilities require one college and no department assignment.');
        }
        if (in_array($responsibility, ['department_head', 'department_secretary'], true)) {
            $unit = count($units) === 1 ? Department::whereIn('_id', $units)->where('unitType', 'academic_department')->where('collegeName', 'CET')->first() : null;
            if (!$unit || (config('qms.departments')[$unit->departmentCode] ?? null) !== $unit->departmentName) {
                $fail('archiveUnitIds', 'Department Heads and Secretaries must belong to one of the five authorized CET departments.');
            }
        }
        if ($responsibility === null && ($units !== [] || $colleges !== [])) {
            $fail('archiveResponsibility', 'Select a responsibility for the organizational assignment.');
        }
        return ['archiveResponsibility' => $responsibility, 'archiveUnitIds' => $units, 'archiveCollegeIds' => $colleges];
    }
}
