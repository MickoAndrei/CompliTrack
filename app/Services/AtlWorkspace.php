<?php

namespace App\Services;

use App\Models\{Audit, ComplianceCheck, User};
use App\Support\MongoId;
use Illuminate\Support\Carbon;

class AtlWorkspace
{
    public function __construct(private RoleScopeService $scopes, private QmsArchiveAccess $archives) {}

    // The same assignment boundary is used by dashboards, review reads and review writes.
    public function audits(User $actor)
    {
        abort_unless($actor->isActive && in_array((int) $actor->roleId, [1, 2], true), 403);
        $ids = (int) $actor->roleId === 2 ? $this->scopes->assignedDepartmentIds($actor)
            : \App\Models\Department::where('collegeName', 'CET')->where('unitType', '!=', 'legacy_unclassified')->get(['_id'])->map(fn ($d) => MongoId::parse((string) $d->getKey()))->all();
        return Audit::whereIn((int) $actor->roleId === 2 ? 'auditorId' : 'reviewerId', [MongoId::parse((string) $actor->getKey()), (string) $actor->getKey()])
            ->whereIn('departmentId', array_merge($ids, array_map('strval', $ids)));
    }

    public function cars(User $actor)
    {
        $this->authorize($actor);
        return $this->scopes->checks($actor)->where('result', 'non_compliant')
            ->whereIn('correctiveStatus', ['open', 'in_progress', 'closed']);
    }

    public function dashboard(User $actor, int $page = 1): array
    {
        $this->authorize($actor);
        $query = $this->audits($actor);
        $audits = (clone $query)->get(['auditCode', 'title', 'departmentId', 'departmentName', 'auditorId', 'schedule', 'status', 'items', 'updatedAt']);
        $departments = $this->scopes->departments($actor)->where('unitType', 'academic_department')->where('ownershipVerified', true)
            ->whereIn('departmentCode', array_keys(config('qms.departments')))->orderBy('departmentName')->get();
        $names = $this->scopes->departments($actor)->get(['departmentName'])->mapWithKeys(fn ($d) => [(string) $d->getKey() => $d->departmentName]);
        $people = User::whereIn('_id', $this->archives->ids($audits->pluck('auditorId')->all()))->get(['fullName'])
            ->mapWithKeys(fn ($u) => [(string) $u->getKey() => $u->fullName]);
        $assignments = $audits->sortByDesc('updatedAt')->map(fn ($audit) => [
            'id' => (string) $audit->getKey(), 'reference' => $audit->auditCode, 'title' => $audit->title,
            'department' => $names[(string) $audit->departmentId] ?? $audit->departmentName ?? 'Department unavailable',
            'auditor' => $people[(string) $audit->auditorId] ?? 'Account unavailable', 'status' => $audit->status,
            'schedule' => $audit->schedule, 'progress' => AuditChecklist::progress($audit->items ?? []),
        ])->values();
        $cars = $this->cars($actor)->get(['checkCode', 'departmentId', 'correctiveStatus', 'dueDate']);
        $openCars = $cars->whereIn('correctiveStatus', ['open', 'in_progress']);
        $overdue = $openCars->filter(fn ($car) => $this->isOverdue($car));
        $attention = $assignments->whereIn('status', ['submitted', 'returned'])->sortBy(fn ($a) => $a['status'] === 'submitted' ? 0 : 1)
            ->take(10)->map(fn ($audit) => ['kind' => 'audit', 'id' => $audit['id'], 'reference' => $audit['reference'],
                'department' => $audit['department'], 'reason' => $audit['status'] === 'submitted' ? 'Pending audit review' : 'Returned submission — follow up with auditor', 'status' => $audit['status']]);
        $attention = $attention->concat($overdue->sortBy('dueDate')->take(10)->map(fn ($car) => [
            'kind' => 'car', 'id' => (string) $car->getKey(), 'reference' => $car->checkCode,
            'department' => $names[(string) $car->departmentId] ?? 'Department unavailable',
            'reason' => 'Overdue corrective action', 'status' => $car->correctiveStatus,
        ]))->values();
        $comparison = $departments->map(function ($department) use ($audits) {
            $completed = $audits->filter(fn ($a) => $a->status === 'accepted' && (string) $a->departmentId === (string) $department->getKey());
            $counts = ['N' => 0, 'C' => 0, 'O' => 0];
            foreach ($completed as $audit) {
                foreach ($audit->items ?? [] as $item) { if (isset($counts[$item['result'] ?? ''])) { $counts[$item['result']]++; } }
            }
            $applicable = $counts['C'] + $counts['N'];
            return ['id' => (string) $department->getKey(), 'name' => $department->departmentName, 'counts' => $counts,
                'completedAudits' => $completed->count(), 'percentage' => $applicable ? round(100 * $counts['C'] / $applicable, 1) : null];
        })->values();
        return ['stats' => ['activeAudits' => $audits->whereIn('status', ['draft', 'ready', 'submitted', 'returned'])->count(),
            'pendingReviews' => $audits->where('status', 'submitted')->count(), 'openCars' => $openCars->count(), 'overdueCars' => $overdue->count()],
            'people' => ['auditors' => $this->scopes->users($actor)->where('roleId', 2)->where('isActive', true)->count(),
                'auditees' => $this->scopes->users($actor)->where('roleId', 3)->where('isActive', true)->count()],
            'attention' => $attention, 'assignments' => $assignments->slice(($page - 1) * 20, 20)->values(),
            'assignmentPages' => max(1, (int) ceil($assignments->count() / 20)), 'departmentCompliance' => $comparison,
            'generatedAt' => now()->toIso8601String()];
    }

    public function isOverdue(ComplianceCheck $car): bool
    {
        return in_array($car->correctiveStatus, ['open', 'in_progress'], true) && $car->dueDate
            && $car->dueDate->copy()->timezone(config('system.timezone', config('app.timezone')))->endOfDay()->isPast();
    }

    public function verification(ComplianceCheck $car): string
    {
        if ($car->correctiveStatus !== 'closed') {
            return $car->correctiveStatus === 'in_progress' ? 'Awaiting auditor verification' : 'Action pending';
        }
        $closure = collect($car->statusHistory ?? [])->where('status', 'closed')->last();
        return trim($closure['remarks'] ?? '') !== '' ? 'Verification recorded by auditor' : 'Closed; verification not recorded';
    }

    public function evidence(User $actor, array $ids): array
    {
        $ids = $this->archives->ids($ids);
        if (!$ids) { return []; }
        $downloadable = $this->archives->documents($actor, true)->whereIn('_id', $ids)->get(['_id'])->map(fn ($a) => (string) $a->getKey())->all();
        return $this->archives->documents($actor)->whereIn('_id', $ids)->get(['title', 'originalName', 'fileCode'])
            ->map(fn ($a) => ['id' => (string) $a->getKey(), 'name' => $a->title ?: $a->originalName ?: $a->fileCode,
                'canDownload' => in_array((string) $a->getKey(), $downloadable, true)])->all();
    }

    public static function date(mixed $value): string
    {
        if (!$value) { return 'Not recorded'; }
        try {
            if ($value instanceof \MongoDB\BSON\UTCDateTime) { $value = $value->toDateTime(); }
            return Carbon::parse($value)->timezone(config('system.timezone', config('app.timezone')))->format('M j, Y g:i A');
        } catch (\Throwable) { return 'Not recorded'; }
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->isActive && (int) $actor->roleId === 1, 403);
    }
}
