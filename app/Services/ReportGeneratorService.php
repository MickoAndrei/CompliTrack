<?php

namespace App\Services;

use DateTimeInterface;
use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\User;
use App\Models\Audit;
use App\Support\MongoId;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        $auditQuery = match ((int) $actor->roleId) {
            0 => Audit::query(),
            1, 2 => app(AtlWorkspace::class)->audits($actor),
            default => Audit::whereRaw(['_id' => null]),
        };
        $activeAudits = $auditQuery->whereIn('status', ['draft', 'ready', 'submitted', 'returned'])->count();

        // MongoDB can contain both BSON dates and legacy ISO date strings. Read
        // timestamped users and normalize dates in PHP so mixed representations
        // do not silently disappear from the growth series.
        $userDates = $this->scopes->users($actor)
            ->where(static function ($query): void {
                $query->whereNotNull('createdAt')->orWhereNotNull('created_at');
            })
            ->get(['createdAt', 'created_at'])
            ->map(static fn (User $user) => self::userCreationDate($user))
            ->filter();

        if ($startDate || $endDate) {
            $userGrowth = $userDates
                ->filter(static function (Carbon $date) use ($startDate, $endDate): bool {
                    return (! $startDate || $date->greaterThanOrEqualTo($startDate->copy()->utc()))
                        && (! $endDate || $date->lessThanOrEqualTo($endDate->copy()->utc()));
                })
                ->groupBy(static fn (Carbon $date) => $date->format('Y-m'))
                ->map(static fn ($group, $month) => [
                    'month' => Carbon::createFromFormat('!Y-m', (string) $month)->format('M'),
                    'count' => $group->count(),
                ]);
        } else {
            // Show six complete months and a cumulative user count so the dashboard
            // trend is meaningful even when no one signed up in a particular month.
            $trendStart = now()->utc()->startOfMonth()->subMonths(5);
            $userGrowth = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $userDates) {
                $month = $trendStart->copy()->addMonths($offset);
                $nextMonth = $month->copy()->addMonth();

                return [
                    'month' => $month->format('M'),
                    'count' => $userDates->filter(static fn ($createdAt) => $createdAt < $nextMonth)->count(),
                ];
            });
        }

        $departmentCompliance = $departments->map(function (Department $department) use ($checks) {
            $departmentChecks = (clone $checks)
                ->whereIn('departmentId', [MongoId::parse((string) $department->getKey()), (string) $department->getKey()])
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
                'assessedChecks' => $total,
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
                    'assessedChecks' => $total,
                    'compliancePercentage' => $total === 0
                        ? 0.0
                        : round(($compliant / $total) * 100, 1),
                ];
            })
            ->values()
            ->all();

        $databaseStats = DB::connection('mongodb')->getMongoDB()->command(['dbStats' => 1])->toArray()[0] ?? null;
        $mongoStorageBytes = (float) ($databaseStats->storageSize ?? 0) + (float) ($databaseStats->indexSize ?? 0);
        $archiveFileBytes = (float) $this->scopes->archives($actor)->sum('fileSize');

        return [
            'totalChecks' => $totalChecks,
            'compliantChecks' => $compliantChecks,
            'totalUsers' => $this->scopes->users($actor)->where('isActive', true)->count(),
            'totalDepts' => $departments->count(),
            'storageUsedGb' => round($mongoStorageBytes / (1024 ** 3), 2),
            'archiveFileSizeGb' => round($archiveFileBytes / (1024 ** 3), 2),
            'databaseStats' => [
                'database' => config('database.connections.mongodb.database'),
                'dataSizeBytes' => (int) ($databaseStats->dataSize ?? 0),
                'storageSizeBytes' => (int) ($databaseStats->storageSize ?? 0),
                'indexSizeBytes' => (int) ($databaseStats->indexSize ?? 0),
                'collections' => (int) ($databaseStats->collections ?? 0),
                'objects' => (int) ($databaseStats->objects ?? 0),
            ],
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

    private static function userCreationDate(User $user): ?Carbon
    {
        $createdAt = $user->createdAt ?? $user->getAttribute('created_at');
        if ($createdAt instanceof DateTimeInterface) {
            return Carbon::instance($createdAt)->utc();
        }

        if (! is_string($createdAt) || trim($createdAt) === '') {
            return null;
        }

        try {
            return Carbon::parse($createdAt)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}

