<?php

namespace App\Services;

use App\Models\{ArchiveDocument, Department, User};
use App\Support\MongoId;
use Illuminate\Validation\ValidationException;

class AuditAssignment
{
    // An archive's existence, title or signature is not approval to assign an audit.
    // Authorization is issued by the permission-checked approval workflow.
    public function plans(User $actor)
    {
        return app(QmsArchiveAccess::class)->documents($actor)
            ->where('assignmentAuthorization.status', 'authorized')
            ->where('auditPlan.status', 'accepted')
            ->whereIn('assignmentAuthorization.reviewerIds', [MongoId::parse((string) $actor->getKey()), (string) $actor->getKey()])
            ->get()->filter(fn ($plan) => $this->complete($plan));
    }

    public function complete(ArchiveDocument $plan): bool
    {
        $a = $plan->assignmentAuthorization ?? [];
        if (!is_array($a)) { return false; }
        $decision = $plan->auditPlan ?? [];
        if (($decision['status'] ?? null) !== 'accepted' || empty($decision['approvedBy']) || empty($decision['approvedAt'])
            || ($a['approvalVersion'] ?? null) !== ($decision['version'] ?? null)
            || (string) ($a['approvedBy'] ?? '') !== (string) $decision['approvedBy']
            || ($a['approvedAt'] ?? null) !== $decision['approvedAt']) { return false; }
        if ((int) ($decision['documentVersion'] ?? 0) !== (int) ($plan->versionNumber ?: 1)
            || ($decision['documentChecksum'] ?? null) !== $plan->checksum) { return false; }
        $header = $decision['header'] ?? [];
        foreach (['scope', 'criteria', 'scheduleStart', 'scheduleEnd', 'procedureReference'] as $field) {
            if (($a[$field] ?? null) !== ($header[$field] ?? null)) { return false; }
        }
        foreach (['unitIds', 'reviewerIds'] as $field) {
            if (!is_array($a[$field] ?? null) || !is_array($header[$field] ?? null)) { return false; }
            $authorized = array_map('strval', $a[$field]); $approved = array_map('strval', $header[$field]);
            sort($authorized); sort($approved);
            if ($authorized !== $approved) { return false; }
        }
        return !\Illuminate\Support\Facades\Validator::make($a, [
            'unitIds' => 'required|array|min:1', 'unitIds.*' => ['required', function ($attribute, $value, $fail) {
                if (!is_scalar($value) && !$value instanceof \MongoDB\BSON\ObjectId) { $fail('Invalid unit reference.'); return; }
                if (!preg_match('/^[a-f0-9]{24}$/i', (string) $value)) { $fail('Invalid unit reference.'); }
            }],
            'scope' => 'required|string|max:3000', 'criteria' => 'required|string|max:3000',
            'scheduleStart' => 'required|date_format:Y-m-d', 'scheduleEnd' => 'required|date_format:Y-m-d|after_or_equal:scheduleStart',
            'procedureReference' => 'required|string|max:1000',
        ])->fails();
    }

    public function units()
    {
        return Department::where('collegeName', 'CET')->where('isActive', true)->where('ownershipVerified', true)
            ->whereIn('unitType', ['academic_department', 'college_office', 'office'])->get();
    }

    public function eligible(User $auditor, Department $unit): bool
    {
        $id = (string) $unit->getKey();
        return $auditor->isActive && (int) $auditor->roleId === 2
            && (string) $auditor->departmentId !== $id
            && in_array($id, array_map('strval', $auditor->assignedDepartmentIds ?? []), true)
            && !in_array($id, array_map('strval', $auditor->conflictDepartmentIds ?? []), true);
    }

    public function validate(\Illuminate\Http\Request $request): array
    {
        $data = $request->validate([
            'planId' => 'required|string|regex:/^[a-f0-9]{24}$/i',
            'title' => 'required|string|max:200', 'departmentId' => 'required|string|regex:/^[a-f0-9]{24}$/i',
            'auditorId' => 'required|string|regex:/^[a-f0-9]{24}$/i', 'schedule' => 'required|date_format:Y-m-d',
            'independenceConfirmed' => 'accepted', 'independenceNotes' => 'required|string|max:2000',
        ]);
        $fail = fn ($key, $message) => throw ValidationException::withMessages([$key => $message]);
        $plan = $this->plans($request->user())->first(fn ($p) => (string) $p->getKey() === $data['planId']);
        if (!$plan) { $fail('planId', 'Select a plan with confirmed assignment authorization and scope.'); }
        $authorization = $plan->assignmentAuthorization;
        $unit = $this->units()->first(fn ($u) => (string) $u->getKey() === $data['departmentId']);
        if (!$unit || !in_array($data['departmentId'], array_map('strval', $authorization['unitIds']), true)) {
            $fail('departmentId', 'This department or office is outside the authorized plan scope.');
        }
        $auditor = app(RoleScopeService::class)->users($request->user())
            ->where('_id', MongoId::parse($data['auditorId']))->first();
        if (!$auditor || !$this->eligible($auditor, $unit)) {
            $fail('auditorId', 'Select an active assigned auditor without a home-unit or recorded conflict.');
        }
        if ($data['schedule'] < $authorization['scheduleStart'] || $data['schedule'] > $authorization['scheduleEnd']) {
            $fail('schedule', 'Schedule must be within the authorized plan dates.');
        }
        return $data + ['scope' => $authorization['scope'], 'criteria' => $authorization['criteria'],
            'departmentName' => $unit->departmentName, 'planTitle' => $plan->title ?: $plan->originalName,
            'planAuthorizationSnapshot' => $authorization];
    }
}
