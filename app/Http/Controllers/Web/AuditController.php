<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Audit, Department, Notification, User};
use App\Services\{AuditChecklist, AuditLogService, CounterService, RoleScopeService};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuditController extends Controller
{
    public function __construct(private RoleScopeService $scopes, private AuditLogService $logs) {}

    private function query(Request $request)
    {
        return app(\App\Services\AtlWorkspace::class)->audits($request->user());
    }

    public function index(Request $request)
    {
        if ((int) $request->user()->roleId === 2) {
            $request->validate(['search' => 'nullable|string|max:160', 'departmentId' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
                'status' => 'nullable|in:draft,ready,in_progress,submitted,returned,accepted', 'page' => 'nullable|integer|min:1']);
            try {
                $data = app(\App\Services\AuditorAuditListing::class)->page($request->user(), $request);
            } catch (\MongoDB\Driver\Exception\Exception $exception) {
                report($exception);
                return response()->view('auditor.audits', ['loadError' => true], 503)->header('Cache-Control', 'private, no-store');
            }
            return response()->view($request->ajax() ? 'auditor.partials.assigned-audit-fragment' : 'auditor.audits', $data)->header('Cache-Control', 'private, no-store');
        }
        $request->validate(['view' => 'nullable|in:reviews', 'status' => 'nullable|in:draft,ready,submitted,returned,accepted']);
        $query = $this->query($request);
        $admin = (int) $request->user()->roleId === 1;
        if ($admin && $request->query('view') === 'reviews') {
            $status = $request->query('status', 'submitted');
            abort_unless(in_array($status, ['submitted', 'returned', 'accepted'], true), 422);
            $counts = [];
            foreach (['submitted', 'returned', 'accepted'] as $state) { $counts[$state] = (clone $query)->where('status', $state)->count(); }
            $audits = $query->where('status', $status)->orderBy('updatedAt', 'desc')->paginate(20)->withQueryString();
            return view('admin.audit-reviews', compact('audits', 'counts', 'status'));
        }
        if ($request->filled('status')) { $query->where('status', $request->query('status')); }
        $audits = $query->orderBy('createdAt', 'desc')->paginate(20)->withQueryString();
        if (!$admin) { return view('auditor.audits', compact('audits')); }
        $assignment = app(\App\Services\AuditAssignment::class);
        $plans = $assignment->plans($request->user());
        $units = $assignment->units();
        $auditors = $this->scopes->users($request->user())->where('roleId', 2)->where('isActive', true)->orderBy('fullName')->get();
        $people = $auditors->keyBy(fn ($u) => (string) $u->getKey());
        $counts = [];
        foreach (['draft', 'ready'] as $state) { $counts[$state] = $this->query($request)->where('status', $state)->count(); }
        return view('admin.audit-assignments', compact('audits', 'plans', 'units', 'auditors', 'people', 'counts', 'assignment'));
    }

    public function metrics(Request $request)
    {
        abort_unless((int) $request->user()->roleId === 2, 403);
        $checks = $this->scopes->checks($request->user())->whereIn('departmentId', $this->scopes->assignedDepartmentIds($request->user()))->get();
        $stats = ['totalChecks' => $checks->whereIn('result', ['compliant', 'non_compliant'])->count(),
            'compliantChecks' => $checks->where('result', 'compliant')->count(),
            'openCars' => $checks->whereIn('correctiveStatus', ['open', 'in_progress'])->count(),
            'closedCars' => $checks->where('correctiveStatus', 'closed')->count(),
            'nonconformities' => $checks->where('result', 'non_compliant')->count(), 'pendingChecks' => 0,
            'nonCompliantChecks' => $checks->where('result', 'non_compliant')->count(),
            'closedThisMonth' => $checks->filter(fn ($check) => $check->correctiveStatus === 'closed' && $check->closedAt?->gte(now()->startOfMonth()))->count()];
        $audits = $this->query($request)->orderBy('updatedAt', 'desc')->get();
        $stats['observations'] = 0;
        foreach ($audits as $audit) {
            $progress = AuditChecklist::progress($audit->items ?? []);
            $stats['totalChecks'] += $progress['evaluated'];
            $stats['compliantChecks'] += $progress['C'];
            $stats['nonCompliantChecks'] += $progress['N'];
            $stats['observations'] += $progress['O'];
            $stats['pendingChecks'] += $progress['pending'];
            foreach ($audit->items ?? [] as $item) {
                if (($item['result'] ?? '') === 'N' && trim($item['finding'] ?? '') !== '') { $stats['nonconformities']++; }
            }
        }
        $changes = $checks->flatMap(function ($check) {
            return collect($check->statusHistory ?? [])->map(function ($event, $index) use ($check) {
                $at = $event['timestamp'] ?? null;
                if ($at instanceof \MongoDB\BSON\UTCDateTime) { $at = $at->toDateTime()->format(DATE_ATOM); }
                elseif ($at instanceof \DateTimeInterface) { $at = $at->format(DATE_ATOM); }
                return ['key' => (string) $check->getKey().'-'.$index, 'id' => (string) $check->getKey(),
                    'code' => $check->checkCode, 'status' => $event['status'],
                    'label' => match ($event['status']) { 'closed' => 'closed', 'in_progress' => 'moved to In Progress', default => 'opened' }, 'at' => $at];
            });
        })->filter(fn ($change) => $change['at'])->sortByDesc('at')->take(3)->values();
        $auditActivity = $audits->flatMap(function ($audit) {
            return collect($audit->history ?? [])->map(fn ($event, $index) => [
                'key' => 'audit-'.(string) $audit->getKey().'-'.$index, 'id' => (string) $audit->getKey(),
                'url' => '/audits/'.$audit->getKey(), 'code' => $audit->auditCode,
                'status' => $event['action'], 'label' => '— '.str_replace('_', ' ', $event['action']), 'at' => $event['at'],
            ]);
        });
        $changes = $changes->map(fn ($change) => $change + ['url' => '/auditor/checks/'.$change['id']])
            ->concat($auditActivity)->sortByDesc('at')->take(3)->values();
        return response()->json(['data' => $stats, 'changes' => $changes,
            'assignments' => $audits->take(3)->map(fn ($audit) => [
                'id' => (string) $audit->getKey(), 'code' => $audit->auditCode, 'title' => $audit->title,
                'status' => $audit->status, 'department' => $audit->departmentName,
                'progress' => AuditChecklist::progress($audit->items ?? []),
            ])->values()]);
    }

    public function store(Request $request, CounterService $counter)
    {
        abort_unless((int) $request->user()->roleId === 1 && $request->user()->isActive, 403);
        $data = app(\App\Services\AuditAssignment::class)->validate($request);
        // One audit record owns its assignment. Prevent an accidental form resubmission
        // without preventing distinct audits for the same department or auditor.
        $assignmentKey = hash('sha256', json_encode([$data['title'], $data['scope'], $data['criteria'], $data['schedule'],
            $data['departmentId'], $data['auditorId'], (string) $request->user()->getKey()]));
        if (Audit::where('assignmentKey', $assignmentKey)->exists()) {
            throw ValidationException::withMessages(['title' => 'This exact audit assignment already exists. Open the existing audit.']);
        }
        try {
        $audit = Audit::create($data + ['auditCode' => $counter->next('IQA', yearly: true),
            'reviewerId' => $request->user()->getKey(),
            'assignmentKey' => $assignmentKey,
            'status' => 'draft', 'version' => 1, 'items' => [], 'interviews' => [], 'history' => [['action' => 'assigned', 'actor' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()]]]);
        } catch (\Throwable $exception) {
            if ((int) $exception->getCode() === 11000) {
                throw ValidationException::withMessages(['title' => 'This audit assignment already exists. Refresh the assignment list.']);
            }
            throw $exception;
        }
        $this->logs->record($request, 'Assigned audit', 'audit', $audit->getKey());
        app(\App\Services\NotificationDispatchService::class)->persist($audit->auditorId,
            'audit:'.$audit->getKey().':assigned', 'audit_assigned', 'Audit assigned',
            $audit->auditCode.' has been assigned to you. Open My Assigned Audits.', $request->user()->getKey());
        return redirect('/audits')->with('status', 'Audit assigned.');
    }

    public function amend(Request $request, string $id)
    {
        abort_unless((int) $request->user()->roleId === 1 && $request->user()->isActive, 403);
        $audit = $this->query($request)->where('_id', MongoId::parse($id))->firstOrFail();
        $request->validate(['version' => 'required|integer', 'changeReason' => 'required|string|max:2000']);
        abort_unless((int) $audit->version === (int) $request->input('version'), 409);
        abort_unless(in_array($audit->status, ['draft', 'ready'], true)
            && !collect($audit->history ?? [])->contains('action', 'submit'), 403, 'Submitted audits retain their assignment.');
        $data = app(\App\Services\AuditAssignment::class)->validate($request);
        abort_unless((string) $audit->planId === $data['planId'] && (string) $audit->departmentId === $data['departmentId'], 422, 'Plan and unit must be retained.');
        $previous = $audit->only(['planId', 'auditorId', 'departmentId', 'title', 'schedule', 'scope', 'criteria', 'independenceNotes', 'planAuthorizationSnapshot']);
        $history = $audit->history ?? [];
        $history[] = ['action' => 'assignment_changed', 'actor' => (string) $request->user()->getKey(),
            'at' => now()->utc()->toIso8601String(), 'notes' => $request->input('changeReason'), 'previousAssignment' => $previous];
        // Retain original creation retry protection when an assignment changes.
        foreach (['planId', 'auditorId', 'departmentId'] as $field) { $data[$field] = MongoId::parse($data[$field]); }
        $updated = $this->query($request)->where('_id', $audit->getKey())->where('version', (int) $audit->version)
            ->where('status', $audit->status)->update($data + ['history' => $history, 'version' => (int) $audit->version + 1]);
        abort_unless($updated === 1, 409, 'This audit changed. Refresh before saving again.');
        $this->logs->record($request, 'Changed audit assignment', 'audit', $audit->getKey());
        foreach (array_unique([(string) $audit->auditorId, (string) $data['auditorId']]) as $recipient) {
            app(\App\Services\NotificationDispatchService::class)->persist($recipient,
                'audit:'.$audit->getKey().':assignment:'.($audit->version + 1).':'.$recipient,
                'audit_assignment_changed', 'Audit assignment updated', $audit->auditCode.': '.$request->input('changeReason'), $request->user()->getKey());
        }
        return redirect('/audits')->with('status', 'Assignment updated with history.');
    }

    public function show(Request $request, string $id)
    {
        $audit = $this->query($request)->where('_id', MongoId::parse($id))->firstOrFail();
        $auditees = User::whereIn('departmentId', [MongoId::parse((string) $audit->departmentId), (string) $audit->departmentId])->where('roleId', 3)->where('isActive', true)->get();
        $progress = AuditChecklist::progress($audit->items ?? []);
        if ($request->boolean('review')) {
            abort_unless((int) $request->user()->roleId === 1, 403);
            $ids = $audit->planArchiveIds ?? [];
            if ($audit->planId) { $ids[] = (string) $audit->planId; }
            foreach ($audit->items ?? [] as $item) { foreach ($item['evidence'] ?? [] as $e) { if (!empty($e['archiveId'])) { $ids[] = $e['archiveId']; } } }
            foreach ($audit->evidenceRequests ?? [] as $entry) { foreach ($entry['responses'] ?? [] as $response) { if (!empty($response['archiveId'])) { $ids[] = $response['archiveId']; } } }
            $attachments = app(\App\Services\AtlWorkspace::class)->evidence($request->user(), $ids);
            return response()->view('admin.audit-review', compact('audit', 'progress', 'attachments'))->header('Cache-Control', 'no-store');
        }
        $readiness = AuditChecklist::readiness($audit->items ?? []);
        $peopleIds = [(string) $audit->auditorId, (string) $audit->reviewerId];
        foreach ($audit->history ?? [] as $event) { $peopleIds[] = $event['actor'] ?? ''; }
        foreach ($audit->items ?? [] as $item) {
            foreach ($item['evidence'] ?? [] as $evidence) { $peopleIds[] = $evidence['reviewedBy'] ?? ''; }
        }
        foreach ($audit->evidenceRequests ?? [] as $entry) { $peopleIds[] = $entry['auditeeId'] ?? ''; }
        $people = User::whereIn('_id', array_values(array_unique(array_filter($peopleIds))))->get(['fullName'])->keyBy(fn ($person) => (string) $person->getKey());
        $attachmentIds = $audit->planArchiveIds ?? [];
        foreach ($audit->items ?? [] as $item) {
            foreach ($item['evidence'] ?? [] as $evidence) { if (!empty($evidence['archiveId'])) { $attachmentIds[] = $evidence['archiveId']; } }
        }
        foreach ($audit->evidenceRequests ?? [] as $entry) {
            foreach ($entry['responses'] ?? [] as $response) { if (!empty($response['archiveId'])) { $attachmentIds[] = $response['archiveId']; } }
        }
        $attachments = collect(app(\App\Services\AtlWorkspace::class)->evidence($request->user(), $attachmentIds))->keyBy('id');
        return response()->view('auditor.audit-workspace', compact('audit', 'auditees', 'progress', 'readiness', 'people', 'attachments'))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $id)
    {
        $audit = $this->query($request)->where('_id', MongoId::parse($id))->firstOrFail();
        $request->validate(['version' => 'required|integer', 'action' => 'required|in:add,edit,remove,move,ready,evaluate,evidence,interview,submit,accept,return']);
        abort_unless((int) $request->input('version') === (int) $audit->version, 409, 'This audit changed. Refresh before saving again.');
        $action = $request->input('action');
        $review = in_array($action, ['accept', 'return'], true);
        abort_unless($review ? (int) $request->user()->roleId === 1 && $audit->status === 'submitted'
            : (int) $request->user()->roleId === 2 && in_array($audit->status, ['draft', 'ready', 'returned'], true), 403);
        $items = $audit->items ?? [];
        $changes = [];
        if (in_array($action, ['edit', 'remove', 'move', 'evaluate', 'evidence'], true)) {
            $request->validate(['item' => 'required|integer|min:0']);
            $index = (int) $request->input('item');
            abort_unless(isset($items[$index]), 404);
        }
        if (in_array($action, ['add', 'edit', 'remove', 'move'], true)) {
            if (in_array($action, ['edit', 'remove'], true)) {
                abort_if(collect($audit->evidenceRequests ?? [])->contains('itemId', $items[$index]['id']), 403, 'A requested check item must be retained.');
            }
            abort_unless($audit->status === 'draft', 403, 'Prepared questions are retained.');
            if ($action === 'add') {
                $data = $request->validate(['question' => 'required|string|max:2000', 'reference' => 'nullable|string|max:500']);
                abort_if(count($items) >= 200, 422, 'Maximum 200 items per audit.');
                $items[] = $data + ['id' => (string) \Illuminate\Support\Str::uuid(), 'result' => null, 'remarks' => '', 'evidence' => []];
            } elseif ($action === 'edit') {
                $data = $request->validate(['question' => 'required|string|max:2000', 'reference' => 'nullable|string|max:500']);
                $items[$index] = array_replace($items[$index], $data);
            } elseif ($action === 'remove') {
                array_splice($items, $index, 1);
            } else {
                if ($index > 0) { [$items[$index - 1], $items[$index]] = [$items[$index], $items[$index - 1]]; }
            }
        } elseif ($action === 'ready') {
            abort_unless($audit->status === 'draft' && count($items), 422);
            $changes['status'] = 'ready';
        } elseif ($action === 'evaluate') {
            abort_if($audit->status === 'draft', 422, 'Mark the checklist ready first.');
            if (empty($items[$index]['evidence'])) {
                throw ValidationException::withMessages(['evidence' => 'Record an evidence review before choosing N/C/O. No file upload is required.']);
            }
            $data = $request->validate(['result' => 'required|in:N,C,O', 'remarks' => 'required|string|max:5000', 'finding' => 'nullable|string|max:5000']);
            if ($data['result'] === 'C') { $data['finding'] = null; }
            $items[$index] = array_replace($items[$index], $data, ['evaluatedBy' => (string) $request->user()->getKey(), 'evaluatedAt' => now()->utc()->toIso8601String()]);
        } elseif ($action === 'evidence') {
            $data = $request->validate(['method' => 'required|in:digital,physical-original,restricted,interview-process', 'reference' => 'nullable|string|max:500', 'notes' => 'required|string|max:5000', 'signature' => 'required_if:method,physical-original|nullable|in:verified,missing-incomplete,not-verified,not-required']);
            if ($data['method'] !== 'physical-original') { unset($data['signature']); }
            $items[$index]['evidence'][] = $data + ['reviewedBy' => (string) $request->user()->getKey(), 'reviewedAt' => now()->utc()->toIso8601String()];
        } elseif ($action === 'interview') {
            $data = $request->validate(['auditeeId' => 'required|string', 'process' => 'required|string|max:500', 'topics' => 'required|string|max:3000', 'responses' => 'required|string|max:5000', 'notes' => 'nullable|string|max:5000']);
            $person = User::where('_id', MongoId::parse($data['auditeeId']))->where('departmentId', MongoId::parse((string) $audit->departmentId))->where('roleId', 3)->where('isActive', true)->firstOrFail();
            $changes['interviews'] = array_merge($audit->interviews ?? [], [$data + ['auditeeName' => $person->fullName, 'author' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()]]);
        } elseif ($action === 'submit') {
            abort_if($audit->status === 'draft', 422);
            AuditChecklist::validateSubmission($items);
            $changes['status'] = 'submitted';
        } elseif ($review) {
            $request->validate(['reviewNotes' => 'required|string|max:5000']);
            $changes['status'] = $action === 'accept' ? 'accepted' : 'returned';
        }
        $history = $audit->history ?? [];
        $history[] = ['action' => $action, 'actor' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String(),
            'notes' => $request->input('reviewNotes'), 'previousItems' => $audit->items ?? [], 'previousStatus' => $audit->status];
        $updated = $this->query($request)->where('_id', $audit->getKey())->where('version', (int) $audit->version)->where('status', $audit->status)
            ->update($changes + ['items' => $items, 'history' => $history, 'version' => (int) $audit->version + 1]);
        abort_unless($updated === 1, 409, 'This audit changed. Refresh before saving again.');
        $this->logs->record($request, 'Audit '.$action, 'audit', $audit->getKey());
        if ($action === 'submit' || $review) {
            app(\App\Services\NotificationDispatchService::class)->persist(
                $action === 'submit' ? $audit->reviewerId : $audit->auditorId,
                'audit:'.$audit->getKey().':version:'.((int) $audit->version + 1),
                $action === 'submit' ? 'audit_submitted' : ($action === 'accept' ? 'audit_accepted' : 'audit_returned'),
                $audit->auditCode.' '.$changes['status'],
                ($action === 'submit' ? 'Checklist and audit results submitted. ' : '').'Open Audits to review '.$audit->auditCode.'. '.$request->input('reviewNotes', ''),
                $request->user()->getKey());
        }
        if ($request->expectsJson()) { return response()->json(['message' => $review ? 'Audit review recorded.' : 'Audit saved.', 'status' => $changes['status'] ?? $audit->status]); }
        return back()->with('status', 'Audit saved.');
    }
}
