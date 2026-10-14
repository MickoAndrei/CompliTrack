<?php

namespace App\Services;

use App\Models\ArchiveDocument;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Carbon;

class ReportGeneratorService
{
    public function __construct(private readonly RoleScopeService $scopes)
    {
    }

    public function generate(User $actor, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $departments = $this->scopes->departments($actor)->where('isActive', true)->get();
        $checks = $this->scopes->checks($actor);
        if ($startDate) {
            $checks->where('createdAt', '>=', $startDate->copy()->utc());
        }
        if ($endDate) {
            $checks->where('createdAt', '<=', $endDate->copy()->utc());
        }

        $totalChecks = (clone $checks)->count();
        $compliantChecks = (clone $checks)->where('result', 'compliant')->count();
        $openCars = (clone $checks)->whereIn('correctiveStatus', ['open', 'in_progress'])->count();
        $openOverSevenDays = (clone $checks)->where('correctiveStatus', 'open')
            ->where('createdAt', '<', now()->utc()->subDays(7))
            ->count();
        $activeAudits = $openCars;

        $users = $this->scopes->users($actor);
        if ($startDate) {
            $users->where('createdAt', '>=', $startDate->copy()->utc());
        }
        if ($endDate) {
            $users->where('createdAt', '<=', $endDate->copy()->utc());
        }
        $userGrowth = $users->get(['createdAt'])
            ->groupBy(static fn (User $user) => $user->createdAt?->format('Y-m'))
            ->map(static fn ($group, $month) => ['month' => Carbon::createFromFormat('!Y-m', (string) $month)->format('M'), 'count' => $group->count()]);

        $departmentCompliance = $departments->map(function (Department $department) use ($checks) {
            $departmentChecks = clone $checks;
            $total = $departmentChecks->where('departmentId', $department->getKey())->count();
            $compliant = (clone $checks)
                ->where('departmentId', $department->getKey())
                ->where('result', 'compliant')
                ->count();

            return [
                'departmentCode' => $department->departmentCode,
                'compliancePercentage' => $total === 0 ? 0.0 : round(($compliant / $total) * 100, 1),
            ];
        })->values()->all();

        $storageBytes = $this->scopes->archives($actor)->sum('fileSize');

        return [
            'totalUsers' => $this->scopes->users($actor)->where('isActive', true)->count(),
            'totalDepts' => $departments->count(),
            'storageUsedGb' => round(((float) $storageBytes) / (1024 ** 3), 2),
            'activeAudits' => $activeAudits,
            'userGrowth' => $userGrowth->values()->all(),
            'deptCompliance' => $departmentCompliance,
            'complianceRate' => $totalChecks === 0 ? 0.0 : round(($compliantChecks / $totalChecks) * 100, 1),
            'openCars' => $openCars,
            'openOverSevenDays' => $openOverSevenDays,
            'totalAuditors' => $this->scopes->users($actor)->where('roleId', 2)->where('isActive', true)->count(),
            'totalAuditees' => $this->scopes->users($actor)->where('roleId', 3)->where('isActive', true)->count(),
        ];
    }
}
