<?php

namespace App\Services;

use App\Models\{Audit, User};
use App\Support\CarTimeline;

class AnnualAuditReport
{
    public function generate(User $actor, int $year, ?string $planId = null): array
    {
        abort_unless($actor->isActive && in_array((int) $actor->roleId, [0, 1], true), 403);
        $scopes = app(RoleScopeService::class);
        // Assignment scope includes the Dean's office; the five-department directory intentionally does not.
        // This is the same verified CET unit set used by plan preparation and authorized audit assignments.
        $units = app(AuditAssignment::class)->units();
        $unitIds = $units->map(fn ($unit) => (string) $unit->getKey())->all();
        $query = (int) $actor->roleId === 0 ? Audit::query() : app(AtlWorkspace::class)->audits($actor);
        $audits = $query->get()->filter(fn ($audit) => in_array((string) $audit->departmentId, $unitIds, true));
        $knownPlanIds = $audits->pluck('planId')->map('strval')->all();
        $plans = app(AuditPlanWorkflow::class)->approvalQuery()->whereNotNull('auditPlan')->get()
            ->filter(fn ($plan) => (int) $actor->roleId === 0
                || in_array((string) $actor->getKey(), array_map('strval', $plan->auditPlan['header']['reviewerIds'] ?? []), true)
                || in_array((string) $plan->getKey(), $knownPlanIds, true));
        if ($planId) { abort_unless($plans->contains(fn ($p) => (string) $p->getKey() === $planId), 404); }
        // Year means scheduled audit year; plan coverage means its approved date range overlaps the year.
        $selectedPlans = $plans->filter(function ($plan) use ($year, $planId) {
            $header = $plan->auditPlan['header'] ?? [];
            return (!$planId || (string) $plan->getKey() === $planId)
                && ($header['scheduleStart'] ?? '9999') <= "$year-12-31"
                && ($header['scheduleEnd'] ?? '') >= "$year-01-01";
        });
        $audits = $audits->filter(fn ($a) => (!$planId || (string) $a->planId === $planId)
            && substr((string) $a->schedule, 0, 4) === (string) $year);
        $auditIds = $audits->map(fn ($a) => (string) $a->getKey())->all();
        $checks = $scopes->checks($actor)->get()->filter(fn ($check) => in_array((string) $check->departmentId, $unitIds, true))
            ->filter(fn ($check) => filled($check->sourceAuditId)
                ? in_array((string) $check->sourceAuditId, $auditIds, true)
                : !$planId && CarTimeline::date($check->createdAt)?->timezone('Asia/Manila')->year === $year);
        $plannedUnits = $selectedPlans->flatMap(fn ($p) => array_map('strval', $p->auditPlan['header']['unitIds'] ?? []))->unique()->all();
        $rows = $units->filter(fn ($u) => !$planId || in_array((string) $u->getKey(), $plannedUnits, true)
            || $audits->contains(fn ($a) => (string) $a->departmentId === (string) $u->getKey()))->map(function ($unit) use ($audits, $checks, $selectedPlans) {
                $id = (string) $unit->getKey();
                $records = $audits->filter(fn ($a) => (string) $a->departmentId === $id);
                $findings = $checks->filter(fn ($c) => (string) $c->departmentId === $id);
                $counts = ['C' => 0, 'N' => 0, 'O' => 0];
                foreach ($records->where('status', 'accepted') as $audit) {
                    foreach ($audit->items ?? [] as $item) { if (isset($counts[$item['result'] ?? ''])) { $counts[$item['result']]++; } }
                }
                $denominator = $counts['C'] + $counts['N'];
                $cars = $findings->where('result', 'non_compliant');
                $open = $cars->filter(fn ($c) => in_array($c->correctiveStatus, ['open', 'in_progress', 'awaiting_verification', 'returned_for_revision'], true));
                $legacy = $findings->filter(fn ($c) => !filled($c->sourceAuditId));
                return ['unitId' => $id, 'unit' => $unit->departmentName,
                    'planned' => $selectedPlans->filter(fn ($p) => in_array($id, array_map('strval', $p->auditPlan['header']['unitIds'] ?? []), true))->count(),
                    'assigned' => $records->count(), 'inProgress' => $records->whereIn('status', ['draft', 'ready', 'in_progress'])->count(),
                    'submitted' => $records->where('status', 'submitted')->count(), 'returned' => $records->where('status', 'returned')->count(),
                    'accepted' => $records->where('status', 'accepted')->count(),
                    'C' => $counts['C'], 'N' => $counts['N'], 'O' => $counts['O'],
                    'denominator' => $denominator, 'compliance' => $denominator ? round(100 * $counts['C'] / $denominator, 1) : null,
                    'openCars' => $open->count(), 'overdueCars' => $open->filter(fn ($c) => $c->dueDate && $c->dueDate->copy()->timezone('Asia/Manila')->endOfDay()->isPast())->count(),
                    'awaitingVerificationCars' => $cars->where('correctiveStatus', 'awaiting_verification')->count(), 'closedCars' => $cars->where('correctiveStatus', 'closed')->count(),
                    'unknownCars' => $cars->filter(fn ($c) => !\App\Support\CarStatus::known($c->correctiveStatus))->count(),
                    'outstandingEvidence' => $records->sum(fn ($a) => count(AuditChecklist::outstandingRequests($a->evidenceRequests ?? []))),
                    'legacyChecks' => $legacy->count(), 'legacyCompliant' => $legacy->where('result', 'compliant')->count(),
                    'linkedCars' => $cars->filter(fn ($c) => filled($c->sourceAuditId))->count()];
            })->values()->all();
        return ['year' => $year, 'planId' => $planId, 'generatedAt' => now()->utc()->toIso8601String(), 'rows' => $rows,
            'plans' => $plans->map(fn ($p) => ['id' => (string) $p->getKey(), 'title' => $p->title ?: $p->originalName])->values()->all(),
            'scope' => 'Authorized CET units and assignments. Year uses audit schedule dates; planned counts one covered unit per plan overlapping the year. Assigned includes all lifecycle states. Submitted, returned and accepted are current states, not cumulative events. C/N/O use accepted checklists only; compliance = C / (C + N), observations excluded. Open CARs include awaiting verification and returned actions; overdue uses the recorded deadline in Asia/Manila. Linked CARs follow selected assignments; legacy checks use creation year and have no plan attribution. Legacy checks and linked CARs never add to checklist C/N/O. Outstanding evidence counts unresolved requests/latest responses.'];
    }
}
