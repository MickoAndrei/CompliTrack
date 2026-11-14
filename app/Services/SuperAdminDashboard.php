<?php

namespace App\Services;

use App\Models\{Audit, Department, User};
use Illuminate\Support\Carbon;

class SuperAdminDashboard
{
    public function __construct(private RoleScopeService $scopes, private QmsArchiveAccess $archives) {}

    public function summary(User $actor): array
    {
        abort_unless((int) $actor->roleId === 0 && $actor->isActive, 403);
        $departments = Department::where('collegeName', 'CET')->where('unitType', 'academic_department')
            ->where('ownershipVerified', true)
            ->orderBy('departmentName')->get();
        $completed = Audit::where('status', 'accepted')->get(['departmentId', 'items']);
        $comparison = $departments->map(function ($department) use ($completed) {
            $counts = ['N' => 0, 'C' => 0, 'O' => 0];
            $audits = $completed->filter(fn ($audit) => (string) $audit->departmentId === (string) $department->getKey());
            foreach ($audits as $audit) {
                foreach ($audit->items ?? [] as $item) {
                    if (array_key_exists($item['result'] ?? '', $counts)) { $counts[$item['result']]++; }
                }
            }
            // Observations are reported separately; they are neither conformities nor nonconformities.
            $applicable = $counts['C'] + $counts['N'];
            return ['id' => (string) $department->getKey(), 'name' => $department->departmentName,
                'code' => $department->departmentCode, 'active' => $department->isActive,
                'completedAudits' => $audits->count(), 'counts' => $counts, 'applicable' => $applicable,
                'percentage' => $applicable ? round(100 * $counts['C'] / $applicable, 1) : null];
        })->values();

        $checks = $this->scopes->checks($actor);
        $cars = (clone $checks)->where('result', 'non_compliant')->whereIn('correctiveStatus', ['open', 'in_progress']);
        $attention = (clone $cars)->get(['checkCode', 'departmentId', 'auditeeId', 'correctiveStatus', 'dueDate', 'createdAt', 'sourceAuditId']);
        $names = Department::get(['departmentName'])->mapWithKeys(fn ($unit) => [(string) $unit->getKey() => $unit->departmentName]);
        $people = User::whereIn('_id', $this->archives->ids($attention->pluck('auditeeId')->all()))
            ->get(['fullName'])->mapWithKeys(fn ($user) => [(string) $user->getKey() => $user->fullName]);
        $attention = $attention->map(function ($car) use ($names, $people) {
            $due = $car->dueDate;
            return ['id' => (string) $car->getKey(), 'reference' => $car->checkCode ?: 'Unnumbered CAR',
                'department' => $names[(string) $car->departmentId] ?? 'Unassigned department',
                'assignee' => $people[(string) $car->auditeeId] ?? 'Unassigned account', 'status' => $car->correctiveStatus,
                'dueDate' => $due?->toIso8601String(), 'overdue' => $due ? $due->copy()->endOfDay()->isPast() : false,
                'source' => $car->sourceAuditId ? 'Checklist-linked CAR' : 'Legacy check CAR'];
        })->sort(function ($a, $b) {
            return ($b['overdue'] <=> $a['overdue']) ?: strcmp($a['dueDate'] ?? '9999', $b['dueDate'] ?? '9999')
                ?: strcmp($a['reference'], $b['reference']);
        })->take(6)->values();
        $archiveQuery = $this->archives->summaryDocuments($actor);
        $archiveBytes = (int) (clone $archiveQuery)->where(fn ($query) => $query->where('recordType', 'file')->orWhereNull('recordType'))->sum('fileSize');
        $recent = Audit::orderBy('updatedAt', 'desc')->limit(6)->get(['auditCode', 'departmentId', 'status', 'updatedAt', 'createdAt']);
        return [
            'stats' => ['totalUsers' => $this->scopes->users($actor)->count(), 'cetDepartments' => $departments->count(),
                'activeAudits' => Audit::whereIn('status', ['draft', 'ready', 'submitted', 'returned'])->count(),
                'openCars' => (clone $cars)->count(), 'archivedDocuments' => (clone $archiveQuery)->count(),
                'archiveStorageBytes' => $archiveBytes],
            'departmentCompliance' => $comparison,
            'recentAuditActivity' => $recent->map(fn ($audit) => ['id' => (string) $audit->getKey(),
                'reference' => $audit->auditCode ?: 'Unnumbered audit', 'department' => $names[(string) $audit->departmentId] ?? 'Unassigned department',
                'status' => $audit->status, 'at' => ($audit->updatedAt ?? $audit->createdAt)?->toIso8601String()])->values(),
            'correctiveAttention' => $attention,
            'recordSummary' => ['completedAudits' => $completed->count(),
                'legacyChecks' => (clone $checks)->whereNull('sourceAuditId')->count(),
                'checklistLinkedCars' => (clone $checks)->whereNotNull('sourceAuditId')->where('result', 'non_compliant')
                    ->whereIn('correctiveStatus', ['open', 'in_progress', 'closed'])->count()],
            'userGrowth' => $this->userGrowth($actor),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    private function userGrowth(User $actor): array
    {
        $dates = $this->scopes->users($actor)->get(['createdAt', 'created_at'])->map(function ($user) {
            try {
                $value = $user->createdAt ?? $user->created_at;
                return $value ? Carbon::parse($value)->utc() : null;
            } catch (\Throwable) { return null; }
        })->filter();
        $start = now()->utc()->startOfMonth()->subMonths(5);
        return collect(range(0, 5))->map(function ($offset) use ($dates, $start) {
            $month = $start->copy()->addMonths($offset);
            return ['month' => $month->format('M Y'), 'count' => $dates->filter(fn ($date) => $date->lt($month->copy()->addMonth()))->count()];
        })->all();
    }
}
