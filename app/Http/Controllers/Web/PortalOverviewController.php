<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Services\{AtlWorkspace, RoleScopeService, QmsArchiveAccess};
use Illuminate\Http\Request;

/** Read-only overview; all assignment and corrective action queries retain their existing role scope. */
class PortalOverviewController extends Controller
{
    public function show(Request $request, AtlWorkspace $workspace, RoleScopeService $scope, QmsArchiveAccess $archives)
    {
        $actor = $request->user();
        $role = (int) $actor->roleId;
        abort_unless($actor->isActive && in_array($role, [0, 1, 2], true), 403);
        if ($request->boolean('details')) {
            return response()->view([0 => 'super-admin.dashboard-details', 1 => 'admin.dashboard-details', 2 => 'auditor.dashboard-details'][$role])->header('Cache-Control', 'private, no-store');
        }
        $request->validate(['planId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i']);
        $query = $role === 0 ? Audit::query() : $workspace->audits($actor);
        $audits = $query->get(['auditCode', 'title', 'planId', 'planTitle', 'departmentId', 'departmentName', 'status', 'items', 'schedule']);
        $plans = $audits->filter(fn ($audit) => $audit->planId)->mapWithKeys(fn ($audit) => [(string) $audit->planId => $audit->planTitle ?: 'Audit plan '.(string) $audit->planId]);
        if ($role === 0) {
            $plans = $archives->documents($actor)->whereNotNull('auditPlan')->get(['title', 'originalName'])->mapWithKeys(fn ($plan) => [(string) $plan->getKey() => $plan->title ?: $plan->originalName])->union($plans);
        }
        $selected = (string) $request->query('planId', '');
        abort_if($selected !== '' && !$plans->has($selected), 404);
        if ($selected !== '') { $audits = $audits->filter(fn ($audit) => (string) $audit->planId === $selected); }
        $total = $audits->count();
        $review = $audits->where('status', 'submitted')->count();
        $accepted = $audits->where('status', 'accepted')->count();
        $submitted = $review + $accepted;
        $pending = $total - $submitted;
        $active = $audits->whereIn('status', ['ready', 'in_progress', 'returned'])->count();
        $carQuery = $scope->checks($actor)->where('result', 'non_compliant');
        if ($role === 2) { $carQuery->whereIn('departmentId', $scope->assignedDepartmentIds($actor)); }
        if ($selected !== '') {
            $ids = $audits->map(fn ($audit) => $audit->getKey())->all();
            $carQuery->whereIn('sourceAuditId', array_merge($archives->ids($ids), array_map('strval', $ids))); 
        }
        $cars = $carQuery->get(['correctiveStatus', 'dueDate']);
        $open = $cars->whereIn('correctiveStatus', ['open', 'in_progress', 'awaiting_verification', 'returned_for_revision'])->count();
        $closed = $cars->where('correctiveStatus', 'closed')->count();
        $due = $cars->filter(fn ($car) => $workspace->isOverdue($car))->count();
        $period = $selected !== '' ? 'Selected cycle' : 'All authorized records';
        $bars = [['Assigned', $total], ['Submitted', $submitted], ['Reviewed', $accepted]];
        $distribution = [['Reviewed', $accepted, 'navy'], ['Awaiting review', $review, 'gold'], ['Not submitted', $pending, 'neutral']];
        $chartTitle = 'Audit activity';
        if ($role === 0) {
            $metrics = [['Audit plans', $selected !== '' ? 1 : $plans->count(), $period], ['Assignments', $total, $period], ['Reports', $submitted, 'Submitted or accepted audits'], ['Open CARs', $open, 'Unresolved']];
            $attention = [['Action', $due.' overdue corrective actions', '/super-admin/dashboard?details=1'], ['Review', $review.' reports awaiting review', '/super-admin/dashboard?details=1']];
            $title = 'University overview'; $subtitle = 'Audit activity across authorized organizational units.';
        } elseif ($role === 1) {
            $metrics = [['Assignments', $total, $period], ['Reports submitted', $submitted, $period], ['Open CARs', $open, 'Unresolved'], ['Closed actions', $closed, 'Recorded closure']];
            $chartTitle = 'Recorded checks by department';
            $bars = $audits->groupBy(fn ($audit) => $audit->departmentName ?: 'Unassigned')->map(fn ($group, $name) => [$name, $group->sum(fn ($audit) => collect($audit->items ?? [])->whereIn('result', ['C', 'N', 'O'])->count())])->sortByDesc(fn ($row) => $row[1])->values()->all();
            $distribution = [['Submitted', $submitted, 'navy'], ['Pending', $pending, 'gold']];
            $attention = [['Action', $pending.' reports not submitted', '/audits'], ['Review', $due.' overdue corrective actions', '/admin/compliance-records?monitor=1']];
            $title = 'Audit overview'; $subtitle = 'Track the selected internal quality audit at a glance.';
        } else {
            $metrics = [['Assigned', $total, $period], ['To submit', $pending, 'Outstanding'], ['Submitted', $submitted, $period], ['For review', $review, 'Awaiting decision']];
            $bars = [['Assigned', $total], ['In progress', $active], ['Submitted', $submitted]];
            $distribution = [['Submitted', $submitted, 'navy'], ['Remaining', $pending, 'gold']];
            $attention = [['Action', $due.' overdue corrective actions', '/auditor/corrective-actions'], ['Review', $pending.' assignments outstanding', '/audits']];
            $title = 'My audit overview'; $subtitle = 'A summary of your authorized audit assignments.';
        }
        $view = [0 => 'super-admin.dashboard', 1 => 'admin.dashboard', 2 => 'auditor.dashboard'][$role];
        return response()->view($view, compact('title', 'subtitle', 'metrics', 'bars', 'distribution', 'chartTitle', 'attention', 'plans', 'selected', 'period'))->header('Cache-Control', 'private, no-store');
    }
}
