<?php

namespace App\Services;

use App\Models\{ArchiveDocument, User};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditPlanWorkflow
{
    public const LABELS = ['draft' => 'Draft', 'submitted' => 'Pending Approval', 'accepted' => 'Approved', 'returned' => 'Returned for Revision'];

    public function approvalQuery()
    {
        $ids = app(AuditAssignment::class)->units()->map(fn ($unit) => MongoId::parse((string) $unit->getKey()))->all();
        return ArchiveDocument::where('ownershipVerified', true)->whereIn('departmentId', array_merge($ids, array_map('strval', $ids)));
    }

    public function canPrepare(User $actor, ArchiveDocument $plan): bool
    {
        $access = app(QmsArchiveAccess::class);
        if (!$actor->isActive || (int) $actor->roleId !== 1 || !$access->isAuditTeamLeader($actor)) { return false; }
        $key = 'auditPlans.editable.'.(string) $actor->getKey();
        if (!request()->attributes->has($key)) {
            $unitIds = app(AuditAssignment::class)->units()->map(fn ($unit) => (string) $unit->getKey())->all();
            $ids = $access->documents($actor)->get(['departmentId'])->filter(fn ($document) => in_array((string) $document->departmentId, $unitIds, true))
                ->map(fn ($document) => (string) $document->getKey())->all();
            request()->attributes->set($key, $ids);
        }
        return in_array((string) $plan->getKey(), request()->attributes->get($key), true);
    }

    public function header(array $data): array
    {
        $fields = Validator::make($data, [
            'unitIds' => 'required|array|min:1|max:100', 'unitIds.*' => 'required|string|regex:/^[a-f0-9]{24}$/i|distinct',
            'reviewerIds' => 'required|array|min:1|max:100', 'reviewerIds.*' => 'required|string|regex:/^[a-f0-9]{24}$/i|distinct',
            'scope' => 'required|string|max:3000', 'criteria' => 'required|string|max:3000',
            'scheduleStart' => 'required|date_format:Y-m-d', 'scheduleEnd' => 'required|date_format:Y-m-d|after_or_equal:scheduleStart',
            'procedureReference' => 'required|string|max:1000',
        ])->validate();
        $units = app(AuditAssignment::class)->units()->map(fn ($unit) => (string) $unit->getKey())->all();
        abort_if(array_diff($fields['unitIds'], $units), 422, 'Select verified active CET departments or offices.');
        $reviewers = User::whereIn('_id', app(QmsArchiveAccess::class)->ids($fields['reviewerIds']))->where('isActive', true)->where('roleId', 1)->count();
        abort_unless($reviewers === count($fields['reviewerIds']), 422, 'Select active Admin / ATL accounts for assignments.');
        return $fields;
    }

    public function transition(Request $request, string $id): ArchiveDocument
    {
        // Read role and permission from MongoDB, never from a cached session or token claim.
        $actor = User::find($request->user()->getKey());
        abort_unless($actor && $actor->isActive, 403);
        $data = $request->validate(['action' => 'required|in:save,submit,approve,return,revise', 'version' => 'required|integer|min:0',
            'remarks' => 'required|string|max:3000']);
        $decision = in_array($data['action'], ['approve', 'return'], true);
        if ($decision) {
            abort_unless(app(AuditPlanPermissions::class)->allows($actor), 403, 'Audit plan approval permission is required.');
            $query = $this->approvalQuery();
        } else {
            abort_unless((int) $actor->roleId === 1, 403);
            $query = app(QmsArchiveAccess::class)->documents($actor);
        }
        $archive = $query->where('_id', MongoId::parse($id))->firstOrFail();
        if (!$decision) { abort_unless($this->canPrepare($actor, $archive), 403); }
        $old = $archive->auditPlan ?? [];
        $version = (int) ($old['version'] ?? 0);
        abort_unless($version === (int) $data['version'], 409, 'The plan changed. Reload before saving.');
        $status = $old['status'] ?? 'draft';
        $action = $data['action'];
        abort_unless(match ($action) {
            'save' => in_array($status, ['draft', 'returned'], true),
            'submit' => $version > 0 && in_array($status, ['draft', 'returned'], true),
            'approve', 'return' => $version > 0 && $status === 'submitted',
            'revise' => $version > 0 && $status === 'accepted',
        }, 403, 'This action is unavailable in the current plan state.');
        $header = $action === 'save' ? $this->header($request->all()) : ($old['header'] ?? []);
        if (in_array($action, ['submit', 'approve'], true)) { $header = $this->header($header); }
        $at = now()->utc()->toIso8601String();
        $history = $old['history'] ?? [];
        $history[] = ['decision' => $action, 'actorId' => \App\Support\MongoId::parse((string) $actor->getKey()), 'actorName' => $actor->fullName, 'at' => $at,
            'remarks' => $data['remarks'], 'previousStatus' => $old['status'] ?? null,
            'previousHeader' => $old['header'] ?? [], 'previousAuthorization' => $archive->assignmentAuthorization ?? null];
        $newStatus = match ($action) { 'submit' => 'submitted', 'approve' => 'accepted', 'return' => 'returned', default => 'draft' };
        $plan = ['status' => $newStatus, 'version' => $version + 1, 'header' => $header, 'history' => $history,
            'preparedBy' => $old['preparedBy'] ?? MongoId::parse((string) $actor->getKey()), 'updatedAt' => $at,
            'documentVersion' => $archive->versionNumber ?: 1, 'documentChecksum' => $archive->checksum,
            'approvedBy' => $action === 'approve' ? MongoId::parse((string) $actor->getKey()) : null, 'approvedAt' => $action === 'approve' ? $at : null];
        $authorization = ['status' => $action === 'approve' ? 'authorized' : $newStatus];
        if ($action === 'approve') {
            $authorization += $header + ['approvedBy' => MongoId::parse((string) $actor->getKey()), 'approvedAt' => $at, 'approvalVersion' => $version + 1];
            foreach (['unitIds', 'reviewerIds'] as $field) { $authorization[$field] = app(QmsArchiveAccess::class)->ids($authorization[$field]); }
        }
        $write = ArchiveDocument::where('_id', $archive->getKey());
        if ($version === 0) { $write->whereNull('auditPlan.version'); }
        else { $write->where('auditPlan.version', $version)->where('auditPlan.status', $status); }
        abort_unless($write->update(['auditPlan' => $plan, 'assignmentAuthorization' => $authorization]) === 1,
            409, 'The plan changed. Reload before saving.');
        app(AuditLogService::class)->record($request, 'Audit plan '.$action, 'archive_document', $archive->getKey());
        return $archive->fresh();
    }
}
