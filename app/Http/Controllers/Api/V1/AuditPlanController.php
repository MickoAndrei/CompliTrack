<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ArchiveDocument, User};
use App\Services\{AuditAssignment, AuditPlanPermissions, AuditPlanWorkflow, QmsArchiveAccess};
use Illuminate\Http\Request;

class AuditPlanController extends Controller
{
    public function index(Request $request)
    {
        $actor = User::find($request->user()->getKey());
        abort_unless($actor && $actor->isActive, 403);
        $approve = app(AuditPlanPermissions::class)->allows($actor);
        $prepare = (int) $actor->roleId === 1;
        abort_unless($approve || $prepare, 403, 'Audit plan preparation or approval permission is required.');
        $workflow = app(AuditPlanWorkflow::class);
        $query = $approve ? $workflow->approvalQuery() : app(QmsArchiveAccess::class)->documents($actor);
        $plans = $query->whereNotNull('auditPlan')->get()->map(function ($archive) use ($actor, $approve, $workflow) {
            $plan = $archive->auditPlan;
            $canPrepare = $workflow->canPrepare($actor, $archive);
            return ['id' => (string) $archive->getKey(), 'title' => $archive->title ?: $archive->originalName,
                'reference' => $archive->documentReference ?: $archive->fileCode, 'plan' => $plan,
                'stateLabel' => AuditPlanWorkflow::LABELS[$plan['status']] ?? 'Unknown',
                'canEdit' => $canPrepare && in_array($plan['status'], ['draft', 'returned'], true),
                'canRevise' => $canPrepare && $plan['status'] === 'accepted',
                'canDecide' => $approve && $plan['status'] === 'submitted'];
        })->sortBy(fn ($row) => $row['plan']['status'] === 'submitted' ? 0 : 1)->values();
        $archives = $prepare ? app(QmsArchiveAccess::class)->documents($actor)->whereNull('auditPlan')->get()
            ->filter(fn ($archive) => $workflow->canPrepare($actor, $archive))
            ->map(fn ($archive) => ['id' => (string) $archive->getKey(), 'title' => $archive->title ?: $archive->originalName])->values() : collect();
        $reviewers = User::where('roleId', 1)->where('isActive', true);
        if (!$prepare) {
            $reviewers->whereIn('_id', app(QmsArchiveAccess::class)->ids($plans->flatMap(fn ($row) => $row['plan']['header']['reviewerIds'] ?? [])->all()));
        }
        return response()->json(['plans' => $plans, 'archives' => $archives, 'canApprove' => $approve, 'canPrepare' => $prepare,
            'units' => app(AuditAssignment::class)->units()->map(fn ($unit) => ['id' => (string) $unit->getKey(), 'name' => $unit->departmentName])->values(),
            'reviewers' => $reviewers->get()->map(fn ($user) => ['id' => (string) $user->getKey(), 'name' => $user->fullName])])
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $id)
    {
        $archive = app(AuditPlanWorkflow::class)->transition($request, $id);
        return response()->json(['message' => 'Audit plan '.AuditPlanWorkflow::LABELS[$archive->auditPlan['status']].'.',
            'version' => $archive->auditPlan['version'], 'status' => $archive->auditPlan['status']]);
    }
}
