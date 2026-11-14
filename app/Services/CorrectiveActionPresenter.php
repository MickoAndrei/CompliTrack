<?php

namespace App\Services;

use App\Models\{ArchiveDocument, Audit, ComplianceCheck, Department, User};
use App\Support\{CarTimeline, MongoId};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Read-only presentation of existing compliance checks; no separate CAR store. */
class CorrectiveActionPresenter
{
    public function __construct(private readonly RoleScopeService $scopes, private readonly QmsArchiveAccess $archives) {}

    public function scope(User $actor)
    {
        abort_unless($actor->isActive && (int) $actor->roleId === 2, 403);
        $ids = $this->scopes->assignedDepartmentIds($actor);
        return $this->scopes->checks($actor)->whereIn('departmentId', array_merge($ids, array_map('strval', $ids)))
            ->where('result', 'non_compliant')->whereNotNull('correctiveStatus');
    }

    public function index(Request $request): array
    {
        $request->validate(['search' => 'nullable|string|max:255', 'departmentId' => 'nullable|regex:/^[a-f0-9]{24}$/i',
            'status' => 'nullable|in:open,in_progress,awaiting_verification,closed,returned_for_revision',
            'page' => 'sometimes|integer|min:1', 'perPage' => 'sometimes|integer|min:1|max:100']);
        $checks = $this->scope($request->user())->orderBy('createdAt', 'desc')->orderBy('_id')->get();
        $context = $this->context($checks, $request->user());
        $rows = $checks->map(fn ($check) => $this->card($check, $context));
        $departments = $rows->map(fn ($row) => ['id' => $row['departmentId'], 'name' => $row['departmentName']])
            ->unique('id')->sortBy('name')->values();
        $search = trim((string) $request->query('search', ''));
        $rows = $rows->filter(fn ($row) => (!$request->filled('departmentId') || $row['departmentId'] === $request->query('departmentId'))
            && ($search === '' || mb_stripos(implode(' ', [$row['auditItem'], $row['finding'], $row['checkCode'], $row['departmentName'], $row['auditeeName'], $row['referenceStandard']]), $search) !== false));
        // Cards cover every matching authorized record, independently of status tabs and pagination.
        $summary = ['total' => $rows->count(), 'inProgress' => $rows->where('displayStatus', 'in_progress')->count(),
            'awaitingVerification' => $rows->where('displayStatus', 'awaiting_verification')->count(),
            'closed' => $rows->where('correctiveStatus', 'closed')->count()];
        if ($request->filled('status')) { $rows = $rows->where('displayStatus', $request->query('status')); }
        $perPage = (int) $request->query('perPage', 10);
        $lastPage = max(1, (int) ceil($rows->count() / $perPage));
        $page = min((int) $request->query('page', 1), $lastPage);
        return ['data' => $rows->values()->forPage($page, $perPage)->values(), 'summary' => $summary, 'departments' => $departments,
            'meta' => ['total' => $rows->count(), 'currentPage' => $page, 'lastPage' => $lastPage, 'perPage' => $perPage]];
    }

    protected function context(Collection $checks, User $actor): array
    {
        $people = $checks->flatMap(fn ($check) => [$check->auditorId, $check->auditeeId])
            ->merge($checks->flatMap(fn ($check) => collect($check->statusHistory ?? [])->pluck('changedByUserId')))
            ->merge($checks->flatMap(fn ($check) => collect($check->evidenceResponses ?? [])->pluck('author')));
        $auditIds = $this->archives->ids($checks->pluck('sourceAuditId')->filter()->all());
        $archiveIds = $this->archives->ids($checks->flatMap(fn ($check) => $check->linkedArchiveIds ?? [])->all());
        return ['people' => User::whereIn('_id', $this->archives->ids($people->filter()->all()))->get(['fullName', 'roleId'])->keyBy(fn ($user) => (string) $user->getKey()),
            'departments' => Department::whereIn('_id', $this->archives->ids($checks->pluck('departmentId')->all()))->get(['departmentName'])->keyBy(fn ($unit) => (string) $unit->getKey()),
            'audits' => Audit::whereIn('_id', $auditIds)->whereIn((int) $actor->roleId === 1 ? 'reviewerId' : 'auditorId', [MongoId::parse((string) $actor->getKey()), (string) $actor->getKey()])->get(['departmentId', 'items'])->keyBy(fn ($audit) => (string) $audit->getKey()),
            'archives' => ArchiveDocument::whereIn('_id', $archiveIds)->get(['createdAt', 'uploadedByUserId', 'departmentId'])->keyBy(fn ($archive) => (string) $archive->getKey())];
    }

    protected function card(ComplianceCheck $check, array $context): array
    {
        $audit = $context['audits']->get((string) $check->sourceAuditId);
        $item = $audit && filled($check->sourceItemId) && (string) $audit->departmentId === (string) $check->departmentId
            ? collect($audit->items ?? [])->firstWhere('id', $check->sourceItemId) : null;
        $finding = trim((string) ($item['finding'] ?? '')) ?: trim((string) $check->remarks);
        $title = trim((string) ($item['question'] ?? '')) ?: ($finding ?: 'Corrective action requires review');
        $status = $check->correctiveStatus;
        if (in_array($status, ['open', 'in_progress'], true)) {
            $latestStatus = collect($check->statusHistory ?? [])->map(fn ($event) => CarTimeline::date($event['timestamp'] ?? null)?->getTimestampMs())->filter()->max();
            $submitted = collect($check->evidenceResponses ?? [])->map(fn ($response) => CarTimeline::date($response['at'] ?? null)?->getTimestampMs())->filter()->max();
            foreach ($check->linkedArchiveIds ?? [] as $id) {
                $archive = $context['archives']->get((string) $id);
                if ($archive && (string) $archive->departmentId === (string) $check->departmentId
                    && (string) $archive->uploadedByUserId === (string) $check->auditeeId) {
                    $submitted = max($submitted ?? 0, CarTimeline::date($archive->createdAt)?->getTimestampMs() ?? 0);
                }
            }
            if ($submitted && (!$latestStatus || $submitted >= $latestStatus)) { $status = 'awaiting_verification'; }
        }
        return ['_id' => (string) $check->getKey(), 'checkCode' => $check->checkCode, 'auditItem' => $title,
            'finding' => $finding ?: 'Not recorded', 'auditorRemarks' => trim((string) ($item['remarks'] ?? '')) ?: $check->remarks,
            'referenceStandard' => trim((string) ($item['reference'] ?? '')) ?: $check->referenceStandard,
            'departmentId' => (string) $check->departmentId,
            'departmentName' => $context['departments']->get((string) $check->departmentId)?->departmentName ?? 'Not recorded',
            'auditeeName' => $context['people']->get((string) $check->auditeeId)?->fullName ?? 'Not recorded',
            'auditorName' => $context['people']->get((string) $check->auditorId)?->fullName ?? 'Not recorded',
            'correctiveStatus' => $check->correctiveStatus, 'displayStatus' => $status,
            'statusLabel' => $status === 'awaiting_verification' ? 'Awaiting Verification' : CarTimeline::label($status),
            'createdAt' => CarTimeline::date($check->createdAt)?->toIso8601String(), 'dueDate' => CarTimeline::date($check->dueDate)?->toIso8601String()];
    }

    public function detail(User $actor, string $id): array
    {
        $check = $this->scope($actor)->where('_id', MongoId::parse($id))->firstOrFail();
        $context = $this->context(collect([$check]), $actor);
        $history = collect($check->statusHistory ?? [])->filter(fn ($event) => !empty($event['status']))->values();
        $roles = $context['people']->mapWithKeys(fn ($person) => [(string) $person->getKey() => $person->roleId])->all();
        $timeline = collect(CarTimeline::forCheck($check, $roles))->map(function ($event) use ($history, $check, $context) {
            $source = $event['kind'] === 'status' ? $history->get($event['order'] ?? -1)
                : ($event['kind'] === 'evidence' ? ($check->evidenceResponses[($event['order'] ?? 0) - $history->count()] ?? null) : null);
            $actorId = $source['changedByUserId'] ?? $source['author'] ?? null;
            return ['label' => $event['label'], 'kind' => $event['kind'], 'state' => $event['state'],
                'returned' => in_array($event['status'], ['returned', 'returned_for_revision', 'needs_revision'], true),
                'at' => $event['at']?->toIso8601String(), 'actor' => $context['people']->get((string) $actorId)?->fullName,
                'role' => $event['role'], 'remarks' => $source['remarks'] ?? null];
        });
        $ids = array_merge($check->linkedArchiveIds ?? [], collect($check->evidenceResponses ?? [])->pluck('archiveId')->filter()->all());
        $evidence = app(AtlWorkspace::class)->evidence($actor, $ids);
        $responses = collect($check->evidenceResponses ?? [])->map(fn ($response) => [
            'method' => $response['method'] ?? null, 'reference' => $response['reference'] ?? null, 'notes' => $response['notes'] ?? null,
            'availability' => $response['availability'] ?? null, 'at' => CarTimeline::date($response['at'] ?? null)?->toIso8601String(),
            'actor' => $context['people']->get((string) ($response['author'] ?? ''))?->fullName,
            'review' => isset($response['review']) ? ['feedback' => $response['review']['feedback'] ?? null,
                'at' => CarTimeline::date($response['review']['at'] ?? null)?->toIso8601String()] : null]);
        foreach ($evidence as $file) {
            $archive = $context['archives']->get($file['id']);
            if ($archive && (string) $archive->uploadedByUserId === (string) $check->auditeeId) {
                $timeline->push(['label' => 'Linked corrective file uploaded', 'kind' => 'evidence', 'state' => 'completed', 'returned' => false,
                    'at' => CarTimeline::date($archive->createdAt)?->toIso8601String(), 'actor' => $context['people']->get((string) $check->auditeeId)?->fullName,
                    'role' => 'Auditee', 'remarks' => null]);
            }
        }
        $timeline = $timeline->reject(fn ($event) => $event['kind'] === 'upcoming')->sortBy(fn ($event) => $event['at'] ?? '9999')->values()
            ->merge($timeline->where('kind', 'upcoming'))->values();
        $card = $this->card($check, $context);
        if ($card['displayStatus'] === 'awaiting_verification') {
            $lastEvidence = $timeline->search(fn ($event) => $event['kind'] === 'evidence' && $event['at'] === $timeline->where('kind', 'evidence')->pluck('at')->filter()->max());
            if ($lastEvidence !== false) {
                $timeline = $timeline->map(function ($event, $index) use ($lastEvidence) {
                    if ($event['state'] === 'current') { $event['state'] = 'completed'; }
                    if ($index === $lastEvidence) { $event['state'] = 'current'; $event['label'] .= ' — Awaiting Verification'; }
                    return $event;
                });
            }
        }
        $transitions = (int) $actor->roleId === 2 ? ComplianceCheckService::allowedTransitions($check) : [];
        if (in_array('closed', $transitions, true) && (!app(CarPermissions::class)->allows($actor, CarPermissions::VERIFY)
            || !app(CarPermissions::class)->allows($actor, CarPermissions::CLOSE))) { $transitions = []; }
        return $card + ['timeline' => $timeline, 'evidence' => $evidence, 'responses' => $responses, 'allowedTransitions' => $transitions];
    }
}
