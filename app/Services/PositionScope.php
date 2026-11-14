<?php

namespace App\Services;

use App\Models\{College, Department, User};

class PositionScope
{
    public function label(User $user): string
    {
        if ($user->archiveResponsibility === 'cluster_administrator' && $user->archiveCluster) {
            return config('qms.clusters')[$user->archiveCluster] ?? 'Cluster assignment unavailable';
        }
        $collegePosition = in_array($user->archiveResponsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true);
        $explicitUnits = $user->archiveUnitIds ?? [];
        $unitIds = in_array($user->archiveResponsibility, ['department_head', 'department_secretary'], true) && $explicitUnits !== []
            ? $explicitUnits : array_merge([$user->departmentId], $explicitUnits);
        $ids = app(QmsArchiveAccess::class)->ids($collegePosition ? ($user->archiveCollegeIds ?? []) : $unitIds);
        if ($ids === []) {
            return $collegePosition ? 'College not assigned' : 'Department / office not assigned';
        }

        // Both the header and profile card render this label during the same request.
        $cacheKey = 'profile.positionScope.'.($collegePosition ? 'college.' : 'unit.').implode(',', array_map('strval', $ids));
        $attributes = request()->attributes;
        if (!$attributes->has($cacheKey)) {
            $names = $collegePosition
                ? College::whereIn('_id', $ids)->orderBy('collegeName')->pluck('collegeName')
                : Department::whereIn('_id', $ids)->orderBy('departmentName')->pluck('departmentName');
            $attributes->set($cacheKey, $names->filter()->unique()->implode(', ') ?: 'Assignment unavailable');
        }

        return $attributes->get($cacheKey);
    }
}
