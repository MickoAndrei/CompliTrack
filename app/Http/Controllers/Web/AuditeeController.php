<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\{Audit, ComplianceCheck, User};
use App\Services\{AuditeeEvidenceService, RoleScopeService, NotificationDispatchService};
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuditeeController extends Controller
{
    public function __construct(private AuditeeEvidenceService $evidence, private RoleScopeService $scopes) {}

    public function dashboard(Request $request)
    {
        abort_unless($request->user()->isActive, 403);
        $checks = $this->scopes->checks($request->user())->orderBy('createdAt', 'desc')->get();
        $requests = Audit::where('departmentId', MongoId::parse((string) $request->user()->departmentId))
            ->where('evidenceRequests.auditeeId', (string) $request->user()->getKey())->get()
            ->flatMap(fn ($audit) => collect($audit->evidenceRequests ?? [])->filter(fn ($entry) => $entry['auditeeId'] === (string) $request->user()->getKey())
                ->map(fn ($entry) => ['auditCode' => $audit->auditCode, 'auditTitle' => $audit->title, 'auditStatus' => $audit->status] + $entry));
        $notifications = $this->scopes->notifications($request->user())->orderBy('createdAt', 'desc')->limit(4)->get();
        $published = $this->published($request->user());
        $oversight = app(\App\Services\QmsOversightService::class)->summary($request->user());
        return view('auditee.dashboard', compact('checks', 'requests', 'notifications', 'published', 'oversight'));
    }

    public function requests(Request $request)
    {
        abort_unless($request->user()->isActive && (int) $request->user()->roleId === 3, 403);
        $request->validate(['search' => 'nullable|string|max:120',
            'auditStatus' => 'nullable|in:draft,ready,returned,submitted,accepted',
            'requestStatus' => 'nullable|in:requested,response_submitted,reviewed,needs_information,under_review',
            'requestId' => 'nullable|uuid', 'auditId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'page' => 'nullable|integer|min:1']);
        if ($request->ajax() && $request->filled('requestId')) {
            return view('auditee.evidence-request-detail', $this->evidence->requestDetail($request->user(), $request->query('requestId'), $request->query('auditId')));
        }
        $audits = $this->evidence->addressedAudits($request->user())->orderBy('updatedAt', 'desc')
            ->get(['auditCode', 'title', 'departmentName', 'auditorId', 'status', 'evidenceRequests']);
        $auditors = User::whereIn('_id', $audits->pluck('auditorId')->filter()->unique()->values()->all())->get(['fullName'])->keyBy(fn ($user) => (string) $user->getKey());
        $records = $audits->flatMap(fn ($audit) => collect($audit->evidenceRequests ?? [])
            ->filter(fn ($entry) => $entry['auditeeId'] === (string) $request->user()->getKey())
            ->map(fn ($entry) => $this->evidence->requestCard($audit, $entry, $auditors->get((string) $audit->auditorId)?->fullName)))->values();
        $summary = ['active' => $records->where('status', '!=', 'reviewed')->count(),
            'awaiting' => $records->whereIn('status', ['requested', 'needs_information'])->count(),
            'submitted' => $records->where('status', 'response_submitted')->count(),
            'completed' => $records->where('status', 'reviewed')->count()];
        $filtered = $records->filter(function ($record) use ($request) {
            if ($request->filled('auditStatus') && $record['auditStatus'] !== $request->query('auditStatus')) { return false; }
            $status = $request->query('requestStatus');
            if ($status === 'requested' && !in_array($record['status'], ['requested', 'needs_information'], true)) { return false; }
            if ($status === 'under_review' && $record['statusLabel'] !== 'Under Review') { return false; }
            if ($status && !in_array($status, ['requested', 'under_review'], true) && $record['status'] !== $status) { return false; }
            return !$request->filled('search') || str_contains(mb_strtolower(implode(' ', [$record['title'], $record['description'], $record['auditCode'], $record['auditTitle'], $record['department'], $record['auditor']])), mb_strtolower(trim($request->query('search'))));
        })->values();
        $requests = new \Illuminate\Pagination\LengthAwarePaginator($filtered->forPage($request->integer('page', 1), 12)->values(), $filtered->count(), 12,
            $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->except(['page', 'auditId', 'requestId'])]);
        return view('auditee.evidence-requests', compact('requests', 'summary'));
    }

    public function createRequest(Request $request, string $id)
    {
        abort_unless((int) $request->user()->roleId === 2, 403);
        $audit = $this->evidence->audit($request->user(), $id);
        $this->evidence->ensureCurrentVersion($audit, $request);
        $data = $request->validate(['itemId' => 'required|uuid', 'auditeeId' => 'required|string',
            'description' => 'required|string|max:2000', 'allowUpload' => 'sometimes|boolean',
            'referenceStandard' => 'required|in:ARTA-3days,ARTA-7days,ARTA-20days,ISO9001-9.2']);
        abort_unless(collect($audit->items ?? [])->contains('id', $data['itemId']), 404);
        $person = User::where('_id', MongoId::parse($data['auditeeId']))->where('roleId', 3)->where('isActive', true)->firstOrFail();
        abort_unless((string) $person->departmentId === (string) $audit->departmentId, 403);
        $requests = $audit->evidenceRequests ?? [];
        foreach ($requests as $existing) {
            if ($existing['auditeeId'] === $data['auditeeId'] && $existing['itemId'] === $data['itemId'] && $existing['status'] !== 'reviewed') {
                throw ValidationException::withMessages(['request' => 'An active request already exists for this item and auditee.']);
            }
        }
        $key = (string) Str::uuid();
        $requests[] = $data + ['id' => $key, 'status' => 'requested', 'createdAt' => now()->utc()->toIso8601String(),
            'responses' => [], 'allowUpload' => $request->boolean('allowUpload')];
        $this->evidence->save($audit, $request, ['evidenceRequests' => $requests]);
        app(NotificationDispatchService::class)->persist($person->getKey(), 'request:'.$key, 'evidence_requested', 'Evidence requested',
            $audit->auditCode.': '.$data['description'], $request->user()->getKey(), null, '/auditee/evidence-requests#request-'.$key);
        return back()->with('status', 'Evidence request sent.');
    }

    public function respond(Request $request, string $id, string $requestId)
    {
        abort_unless((int) $request->user()->roleId === 3, 403);
        $audit = $this->evidence->audit($request->user(), $id);
        $requests = $audit->evidenceRequests;
        $index = $this->evidence->requestIndex($audit, $requestId, $request->user());
        $data = $request->validate(['submissionKey' => 'required|uuid', 'action' => 'required|in:draft,submit',
            'method' => 'required|in:digital-reference,physical-original,interview-process,file',
            'reference' => 'nullable|string|max:500', 'notes' => 'required|string|max:5000',
            'availability' => 'required_if:method,physical-original|nullable|in:available,to-arrange']);
        foreach ($requests[$index]['responses'] as $response) {
            if ($response['submissionKey'] === $data['submissionKey']) {
                return $request->expectsJson() ? response()->json(['message' => 'Response already saved.']) : back()->with('status', 'Response already saved.');
            }
        }
        abort_unless(in_array($requests[$index]['status'], ['requested', 'needs_information'], true), 403);
        abort_if(count($requests[$index]['responses']) >= 100, 422, 'This request has reached its response limit.');
        $this->evidence->ensureCurrentVersion($audit, $request);
        unset($data['action']);
        if ($data['method'] !== 'physical-original') { unset($data['availability']); }
        if ($data['method'] === 'file') {
            abort_unless($requests[$index]['allowUpload'] ?? false, 403, 'File copying has not been authorized for this request.');
            $request->validate(['copyAuthorized' => 'required|accepted']);
            if (!$request->hasFile('file') && !empty($requests[$index]['draft']['archiveId'])) {
                $archive = $this->scopes->archives($request->user())->where('_id', MongoId::parse($requests[$index]['draft']['archiveId']))
                    ->where('requestId', $requestId)->where('auditId', $id)->firstOrFail();
                $data['archiveId'] = (string) $archive->getKey();
            } else {
                $request->merge(['auditId' => $id, 'requestId' => $requestId, 'referenceStandard' => $requests[$index]['referenceStandard']]);
                $uploaded = app(\App\Http\Controllers\Api\V1\ArchiveController::class)->upload($request)->getData(true);
                $data['archiveId'] = $uploaded['data']['_id'];
            }
        }
        $data += ['author' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()];
        if ($request->input('action') === 'draft') { $requests[$index]['draft'] = $data; }
        else {
            $requests[$index]['responses'][] = $data;
            unset($requests[$index]['draft']);
            $requests[$index]['status'] = 'response_submitted';
        }
        $this->evidence->save($audit, $request, ['evidenceRequests' => $requests]);
        if ($request->input('action') === 'submit') {
            app(NotificationDispatchService::class)->persist($audit->auditorId, 'response:'.$data['submissionKey'], 'evidence_submitted',
                'Evidence response submitted', $audit->auditCode.' has a response ready for review.', $request->user()->getKey(), null, '/audits/'.$id.'#evidence-requests');
        }
        return $request->expectsJson() ? response()->json(['message' => $request->input('action') === 'draft' ? 'Draft saved. You can return to finish your response.' : 'Evidence submitted for auditor review.'])
            : back()->with('status', 'Evidence response saved.');
    }

    public function review(Request $request, string $id, string $requestId)
    {
        abort_unless((int) $request->user()->roleId === 2, 403);
        $audit = $this->evidence->audit($request->user(), $id);
        $requests = $audit->evidenceRequests;
        $index = $this->evidence->requestIndex($audit, $requestId, $request->user());
        abort_unless($requests[$index]['status'] === 'response_submitted', 403);
        $data = $request->validate(['decision' => 'required|in:reviewed,needs_information', 'feedback' => 'required|string|max:3000',
            'signature' => 'nullable|in:verified,missing-incomplete,not-verified,not-required']);
        $responses = $requests[$index]['responses']; $last = array_key_last($responses);
        $response = $responses[$last];
        if ($response['method'] === 'physical-original' && empty($data['signature'])) {
            throw ValidationException::withMessages(['signature' => 'Record the actual physical-signature review outcome.']);
        }
        $requests[$index]['status'] = $data['decision'];
        $requests[$index]['responses'][$last]['review'] = $data + ['author' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()];
        $items = $audit->items;
        if ($data['decision'] === 'reviewed') {
            foreach ($items as &$item) {
                if ($item['id'] === $requests[$index]['itemId']) {
                    $item['evidence'][] = ['method' => $response['method'] === 'physical-original' ? 'physical-original' : ($response['method'] === 'interview-process' ? 'interview-process' : 'digital'),
                        'reference' => $response['reference'] ?? null, 'notes' => $data['feedback'], 'signature' => $response['method'] === 'physical-original' ? $data['signature'] : null,
                        'archiveId' => $response['archiveId'] ?? null, 'requestId' => $requestId,
                        'reviewedBy' => (string) $request->user()->getKey(), 'reviewedAt' => now()->utc()->toIso8601String()];
                }
            }
            unset($item);
        }
        $this->evidence->save($audit, $request, ['evidenceRequests' => $requests, 'items' => $items]);
        app(NotificationDispatchService::class)->persist($requests[$index]['auditeeId'], 'response-review:'.$response['submissionKey'], 'evidence_reviewed',
            'Evidence response reviewed', $data['feedback'], $request->user()->getKey(), null, '/auditee/evidence-requests#request-'.$requestId);
        return back()->with('status', 'Evidence review saved. N/C/O must be evaluated separately.');
    }

    public function publish(Request $request, string $id, string $requestId)
    {
        abort_unless((int) $request->user()->roleId === 2, 403);
        $audit = $this->evidence->audit($request->user(), $id);
        abort_unless($audit->status === 'accepted', 403, 'Only accepted results may be shared.');
        $requests = $audit->evidenceRequests;
        $index = $this->evidence->requestIndex($audit, $requestId, $request->user());
        if (!empty($requests[$index]['published'])) { return back()->with('status', 'Result already shared.'); }
        $data = $request->validate(['version' => 'required|integer', 'publicSummary' => 'required|string|max:3000']);
        $item = collect($audit->items)->firstWhere('id', $requests[$index]['itemId']);
        abort_unless($item && in_array($item['result'] ?? '', ['N','C','O'], true), 422);
        $requests[$index]['published'] = ['result' => $item['result'], 'summary' => $data['publicSummary'],
            'author' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()];
        $changed = Audit::where('_id', $audit->getKey())->where('version', (int) $data['version'])->where('status', 'accepted')
            ->update(['evidenceRequests' => $requests, 'version' => (int) $audit->version + 1]);
        abort_unless($changed === 1, 409, 'Refresh before sharing this result.');
        app(\App\Services\AuditLogService::class)->record($request, 'Shared accepted audit result', 'audit', $audit->getKey());
        app(NotificationDispatchService::class)->persist($requests[$index]['auditeeId'], 'published:'.$requestId, 'audit_result_shared',
            'Accepted audit result shared', $audit->auditCode.' has an authorized result for you.', $request->user()->getKey(), null, '/auditee/my-findings');
        return back()->with('status', 'Accepted result shared with the addressed auditee.');
    }

    private function published(User $actor)
    {
        return Audit::where('departmentId', MongoId::parse((string) $actor->departmentId))->where('status', 'accepted')
            ->where('evidenceRequests.auditeeId', (string) $actor->getKey())->get()
            ->flatMap(fn ($audit) => collect($audit->evidenceRequests ?? [])->filter(fn ($entry) => $entry['auditeeId'] === (string) $actor->getKey() && !empty($entry['published']))
                ->map(fn ($entry) => ['auditCode' => $audit->auditCode, 'department' => $audit->departmentName, 'requestId' => $entry['id']] + $entry['published']))->sortByDesc('at');
    }

    public function finding(Request $request, string $id)
    {
        abort_unless($request->user()->isActive && (int) $request->user()->roleId === 3, 403);
        $check = $this->scopes->checks($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        if (!$request->ajax()) {
            return redirect('/auditee/my-findings?detail='.urlencode((string) $check->getKey()));
        }
        $archives = $this->scopes->archives($request->user())->whereIn('_id', array_map(fn ($id) => MongoId::parse((string) $id), $check->linkedArchiveIds ?? []))->get();
        $auditorName = User::find($check->auditorId)?->fullName;
        $departmentName = \App\Models\Department::find($check->departmentId)?->departmentName;
        $actorIds = collect($check->statusHistory ?? [])->pluck('changedByUserId')
            ->merge(collect($check->evidenceResponses ?? [])->pluck('author'))->filter()
            ->map(fn ($id) => (string) $id)->unique()->filter(fn ($id) => preg_match('/^[a-f0-9]{24}$/i', $id));
        $roles = $actorIds->isEmpty() ? [] : User::whereIn('_id', $actorIds->map(fn ($id) => MongoId::parse($id))->values()->all())
            ->get(['roleId'])->mapWithKeys(fn ($actor) => [(string) $actor->getKey() => $actor->roleId])->all();
        $carTimeline = \App\Support\CarTimeline::forCheck($check, $roles);
        return view('auditee.result-detail-content', compact('check', 'archives', 'auditorName', 'departmentName', 'carTimeline'));
    }

    public function results(Request $request)
    {
        abort_unless($request->user()->isActive, 403);
        $request->validate(['search' => 'nullable|string|max:120', 'result' => 'nullable|in:compliant,non_compliant,observation',
            'actionable' => 'nullable|boolean', 'archived' => 'nullable|boolean']);
        try {
            $scope = $this->scopes->checks($request->user());
            $summary = ['total' => (clone $scope)->count(),
                'nonconformities' => (clone $scope)->where('result', 'non_compliant')->count(),
                'openCars' => (clone $scope)->where('result', 'non_compliant')->whereIn('correctiveStatus', ['open', 'in_progress'])->count()];
            $query = $this->scopes->checks($request->user());
            if ($request->filled('search')) { $query->where('checkCode', 'regex', '/'.preg_quote($request->query('search'), '/').'/i'); }
            if (in_array($request->query('result'), ['compliant', 'non_compliant'], true)) { $query->where('result', $request->query('result')); }
            if ($request->query('result') === 'observation') { $query->whereRaw(['_id' => null]); }
            if ($request->boolean('actionable')) { $query->whereIn('correctiveStatus', ['open', 'in_progress']); }
            if ($request->boolean('archived')) { $query->where('correctiveStatus', 'closed'); }
            $checks = $query->orderBy('createdAt', 'desc')->paginate(20)->withQueryString();
            $published = $this->published($request->user());
            if ($request->boolean('actionable') || $request->boolean('archived')) { $published = collect(); }
            if ($request->query('result') === 'compliant') { $published = $published->where('result','C'); }
            if ($request->query('result') === 'non_compliant') { $published = $published->where('result','N'); }
            if ($request->query('result') === 'observation') { $published = $published->where('result','O'); }
            if ($request->filled('search')) { $published = $published->filter(fn ($entry) => stripos($entry['auditCode'], (string) $request->query('search')) !== false); }
            return view('auditee.my-findings', compact('checks', 'published', 'summary'));
        } catch (\MongoDB\Driver\Exception\Exception $exception) {
            report($exception);
            return response()->view('auditee.my-findings', ['summary' => null, 'published' => collect(),
                'checks' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20), 'loadError' => true], 503);
        }
    }

    public function archives(Request $request)
    {
        abort_unless($request->user()->isActive, 403);
        return view('auditee.archives');
    }

    public function correctiveResponse(Request $request, string $id)
    {
        abort_unless((int) $request->user()->roleId === 3 && $request->user()->isActive, 403);
        $check = $this->scopes->checks($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        abort_unless($check->result === 'non_compliant' && in_array($check->correctiveStatus, ['open', 'in_progress'], true)
            && (string) $check->departmentId === (string) $request->user()->departmentId, 403);
        $data = $request->validate(['submissionKey' => 'required|uuid', 'method' => 'required|in:digital-reference,physical-original,interview-process',
            'reference' => 'nullable|string|max:500', 'notes' => 'required|string|max:5000',
            'availability' => 'required_if:method,physical-original|nullable|in:available,to-arrange']);
        if ($data['method'] !== 'physical-original') { unset($data['availability']); }
        $response = $data + ['author' => (string) $request->user()->getKey(), 'at' => now()->utc()->toIso8601String()];
        $result = $check->getConnection()->getMongoDB()->selectCollection($check->getTable())->updateOne(
            ['_id' => MongoId::parse($id), 'correctiveStatus' => ['$in' => ['open', 'in_progress']], 'evidenceResponses.submissionKey' => ['$ne' => $data['submissionKey']]],
            ['$push' => ['evidenceResponses' => $response]]);
        if ($result->getModifiedCount()) {
            app(NotificationDispatchService::class)->persist($check->auditorId, 'corrective:'.$data['submissionKey'], 'evidence_submitted', 'Corrective evidence submitted',
                $check->checkCode.' has a reference/original available for review.', $request->user()->getKey(), $check->getKey(), '/auditor/checks/'.$id);
        }
        $message = 'Corrective evidence saved; the CAR remains subject to authorized verification.';
        if ($request->expectsJson()) { return response()->json(['message' => $message]); }
        return back()->with('status', $message);
    }
}
