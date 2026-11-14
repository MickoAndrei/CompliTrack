<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AuditorAuditListing
{
    public function page(User $actor, Request $request): array
    {
        // The same assignment boundary protects the register and each workspace.
        $records = app(AtlWorkspace::class)->audits($actor)->orderBy('createdAt', 'desc')->get([
            'auditCode', 'title', 'departmentId', 'departmentName', 'schedule', 'status', 'reviewerId',
            'items.result', 'items.evidence.method', 'history.action', 'history.actor', 'history.notes', 'history.at',
        ]);
        return $this->present($records, $request);
    }

    public function present(Collection $records, Request $request): array
    {
        $cards = $records->map(fn ($audit) => $this->card($audit));
        $summary = ['total' => $cards->count(), 'inProgress' => $cards->whereIn('status', ['ready', 'in_progress', 'returned'])->count(),
            'awaitingReview' => $cards->where('status', 'submitted')->count(), 'completed' => $cards->where('status', 'accepted')->count()];
        $departments = $cards->filter(fn ($card) => $card['departmentId'] !== '')->unique('departmentId')->sortBy('department')->map(
            fn ($card) => ['id' => $card['departmentId'], 'name' => $card['department']])->values();
        $filters = ['search' => trim((string) $request->query('search', '')), 'departmentId' => (string) $request->query('departmentId', ''), 'status' => (string) $request->query('status', '')];
        $search = mb_strtolower($filters['search']);
        $filtered = $cards->filter(fn ($card) => ($search === '' || str_contains(mb_strtolower($card['title'].' '.$card['code'].' '.$card['department']), $search))
            && (!$filters['departmentId'] || $card['departmentId'] === $filters['departmentId'])
            && (!$filters['status'] || $card['status'] === $filters['status']))->values();
        $page = max(1, (int) $request->query('page', 1));
        $audits = new LengthAwarePaginator($filtered->forPage($page, 12)->values(), $filtered->count(), 12, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
        return compact('audits', 'summary', 'departments', 'filters');
    }

    public function card(Audit $audit): array
    {
        $progress = AuditChecklist::progress($audit->items ?? []);
        $status = $audit->status;
        if ($status === 'ready' && collect($audit->items ?? [])->contains(fn ($item) => !empty($item['evidence']) || in_array($item['result'] ?? null, ['N', 'C', 'O'], true))) {
            $status = 'in_progress';
        }
        $feedback = null;
        if ($status === 'returned') {
            $event = collect($audit->history ?? [])->last(fn ($event) => ($event['action'] ?? '') === 'return'
                && !empty($audit->reviewerId) && (string) ($event['actor'] ?? '') === (string) $audit->reviewerId);
            if ($event && trim($event['notes'] ?? '') !== '') {
                $feedback = ['notes' => $event['notes'], 'at' => AtlWorkspace::date($event['at'] ?? null)];
            }
        }
        $scheduled = 'Not scheduled';
        if ($audit->schedule) {
            try { $scheduled = \Illuminate\Support\Carbon::parse($audit->schedule)->format('M j, Y'); }
            catch (\Throwable) { $scheduled = 'Schedule unavailable'; }
        }
        $action = match ($status) {
            'draft' => 'Prepare Checklist', 'ready' => 'Open Audit', 'in_progress' => 'Continue Audit',
            'submitted' => 'View Submission', 'returned' => 'Review Feedback & Revise', 'accepted' => 'View Final Results', default => 'View Audit',
        };
        return ['id' => (string) $audit->getKey(), 'title' => $audit->title ?: 'Title not recorded', 'code' => $audit->auditCode,
            'departmentId' => (string) $audit->departmentId, 'department' => $audit->departmentName ?: 'Department not recorded',
            'scheduled' => $scheduled, 'status' => $status, 'label' => $status === 'in_progress' ? 'In Progress' : ucfirst($status),
            'progress' => $progress, 'action' => $action, 'feedback' => $feedback,
            'awaitingAtl' => $status === 'submitted' && !empty($audit->reviewerId),
            'url' => '/audits/'.$audit->getKey().(in_array($status, ['submitted', 'accepted', 'returned'], true) ? '#review' : '#checklist')];
    }
}
