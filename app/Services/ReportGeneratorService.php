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
            $departmentChecks = (clone $checks)
                ->where('departmentId', $department->getKey())
                ->get(['createdAt', 'result', 'correctiveStatus']);
            $total = $departmentChecks->count();
            $compliant = $departmentChecks->where('result', 'compliant')->count();
            $openChecks = $departmentChecks->whereIn('correctiveStatus', ['open', 'in_progress']);
            $agingDays = $openChecks->isEmpty()
                ? 0
                : (int) round($openChecks->avg(
                    static fn (ComplianceCheck $check) => $check->createdAt?->diffInDays(now()),
                ));

            return [
                'departmentName' => $department->departmentName,
                'departmentCode' => $department->departmentCode,
                'compliancePercentage' => $total === 0 ? 0.0 : round(($compliant / $total) * 100, 1),
                'openCarAgingDays' => $agingDays,
            ];
        })->values()->all();

        $trendStart = now()->utc()->startOfMonth()->subMonths(5);
        $trendChecks = $this->scopes->checks($actor)
            ->where('createdAt', '>=', $trendStart)
            ->get(['createdAt', 'result']);
        $monthlyComplianceTrend = collect(range(0, 5))
            ->map(function (int $offset) use ($trendStart, $trendChecks) {
                $month = $trendStart->copy()->addMonths($offset);
                $monthChecks = $trendChecks->filter(
                    static fn (ComplianceCheck $check) => $check->createdAt?->format('Y-m') === $month->format('Y-m'),
                );
                $total = $monthChecks->count();
                $compliant = $monthChecks->where('result', 'compliant')->count();

                return [
                    'month' => $month->format('M'),
                    'compliancePercentage' => $total === 0
                        ? 0.0
                        : round(($compliant / $total) * 100, 1),
                ];
            })
            ->values()
            ->all();

        $storageBytes = $this->scopes->archives($actor)->sum('fileSize');

        return [
            'totalChecks' => $totalChecks,
            'compliantChecks' => $compliantChecks,
            'totalUsers' => $this->scopes->users($actor)->where('isActive', true)->count(),
            'totalDepts' => $departments->count(),
            'storageUsedGb' => round(((float) $storageBytes) / (1024 ** 3), 2),
            'activeAudits' => $activeAudits,
            'userGrowth' => $userGrowth->values()->all(),
            'deptCompliance' => $departmentCompliance,
            'monthlyComplianceTrend' => $monthlyComplianceTrend,
            'complianceRate' => $totalChecks === 0 ? 0.0 : round(($compliantChecks / $totalChecks) * 100, 1),
            'openCars' => $openCars,
            'openOverSevenDays' => $openOverSevenDays,
            'totalAuditors' => $this->scopes->users($actor)->where('roleId', 2)->where('isActive', true)->count(),
            'totalAuditees' => $this->scopes->users($actor)->where('roleId', 3)->where('isActive', true)->count(),
        ];
    }
}
