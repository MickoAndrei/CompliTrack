<?php

namespace App\Services;

use App\Models\{ComplianceCheck, Department, User};
use App\Support\CarTimeline;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Admin monitoring projection over the existing compliance_checks collection. */
class CarRegister extends CorrectiveActionPresenter
{
    public const HEADINGS = ['CAR No.', 'Date Submitted', 'Type', 'Source', 'Process', 'Name of Person Submitting CAR',
        'Issue', 'Assigned To', 'Target Closed-Out Date', 'Results — Pass', 'Results — Fail', 'Results — Action', 'Actual Closed-Out Date', 'Remarks'];

    public function scope(User $actor)
    {
        abort_unless($actor->isActive && (int) $actor->roleId === 1, 403);
        $units = Department::where('collegeName', 'CET')->where('unitType', '!=', 'legacy_unclassified');
        // Archive responsibility can narrow the college boundary; it never widens it.
        if (filled($actor->archiveResponsibility)) {
            $allowed = app(QmsFolderAccess::class)->units($actor)->get(['_id'])->map(fn ($unit) => $unit->getKey())->all();
            $units->whereIn('_id', app(QmsArchiveAccess::class)->ids($allowed));
        }
        $ids = app(QmsArchiveAccess::class)->ids($units->get(['_id'])->map(fn ($unit) => $unit->getKey())->all());
        return ComplianceCheck::whereIn('departmentId', array_merge($ids, array_map('strval', $ids)))
            ->where('result', 'non_compliant');
    }

    protected function card(ComplianceCheck $check, array $context): array
    {
        $row = parent::card($check, $context);
        $log = $check->carLog ?? [];
        $value = static fn ($key) => filled($log[$key] ?? null) ? (string) $log[$key] : 'Not recorded';
        $formal = filled($log['carNo'] ?? null);
        $date = static fn ($v) => CarTimeline::date($v)?->timezone(config('system.timezone', 'Asia/Manila'))->format('M j, Y') ?? 'Not recorded';
        $row['formal'] = $formal;
        $row['carNo'] = $value('carNo');
        $row['overdue'] = $check->correctiveStatus !== 'closed' && $check->dueDate
            && $check->dueDate->copy()->timezone(config('system.timezone', 'Asia/Manila'))->endOfDay()->isPast();
        $row['verification'] = 'Not recorded';
        foreach (['resultsPass' => 'Pass', 'resultsFail' => 'Fail', 'resultsAction' => 'Action'] as $key => $label) {
            if (filled($log[$key] ?? null)) { $row['verification'] = $row['verification'] === 'Not recorded' ? $label : $row['verification'].' / '.$label; }
        }
        $row['official'] = array_combine(self::HEADINGS, [$value('carNo'), $date($log['dateSubmitted'] ?? null), $value('type'), $value('source'),
            $value('process'), $value('submittedByName'), $value('issue'), $value('assignedTo'), $date($check->dueDate),
            $value('resultsPass'), $value('resultsFail'), $value('resultsAction'), $date($check->closedAt), $value('remarks')]);
        // Issue and assignment can be resolved from existing findings and accounts without inventing form metadata.
        if ($row['official']['Issue'] === 'Not recorded') { $row['official']['Issue'] = $row['finding']; }
        if ($row['official']['Assigned To'] === 'Not recorded') { $row['official']['Assigned To'] = $row['auditeeName']; }
        return $row;
    }

    public function index(Request $request): array
    {
        $request->validate(['search' => 'nullable|string|max:255', 'departmentId' => 'nullable|regex:/^[a-f0-9]{24}$/i',
            'status' => 'nullable|in:open,in_progress,awaiting_verification,closed,returned_for_revision',
            'kind' => 'nullable|in:formal,legacy', 'overdue' => 'nullable|boolean',
            'sort' => 'nullable|in:target,reference,newest', 'page' => 'nullable|integer|min:1', 'perPage' => 'nullable|integer|in:10,20,50']);
        $checks = $this->scope($request->user())->orderBy('_id')->get();
        $context = $this->context($checks, $request->user());
        $rows = $checks->map(fn ($check) => $this->card($check, $context));
        $departments = $rows->map(fn ($r) => ['id' => $r['departmentId'], 'name' => $r['departmentName']])->unique('id')->sortBy('name')->values();
        $search = trim((string) $request->query('search', ''));
        $rows = $rows->filter(fn ($r) => (!$request->filled('departmentId') || $r['departmentId'] === $request->query('departmentId'))
            && ($search === '' || mb_stripos(implode(' ', array_merge(array_values($r['official']), [$r['checkCode'], $r['auditItem'], $r['finding'], $r['departmentName'], $r['auditeeName']])), $search) !== false)
            && (!$request->filled('kind') || $r['formal'] === ($request->query('kind') === 'formal')));
        $formal = $rows->where('formal', true);
        $summary = ['Open' => $formal->where('displayStatus', 'open')->count(), 'In Progress' => $formal->where('displayStatus', 'in_progress')->count(),
            'Awaiting Verification' => $formal->where('displayStatus', 'awaiting_verification')->count(), 'Overdue' => $formal->where('overdue', true)->count(),
            'Closed' => $formal->where('correctiveStatus', 'closed')->count()];
        $legacyCount = $rows->where('formal', false)->count();
        $rows = $rows->filter(fn ($r) => (!$request->filled('status') || $r['displayStatus'] === $request->query('status'))
            && (!$request->boolean('overdue') || $r['overdue']));
        $rows = match ($request->query('sort', 'target')) {
            'reference' => $rows->sortBy(fn ($r) => $r['formal'] ? $r['carNo'] : $r['checkCode']),
            'newest' => $rows->sortByDesc('createdAt'), default => $rows->sortBy(fn ($r) => $r['dueDate'] ?? '9999'),
        };
        $rows = $rows->values();
        $perPage = (int) $request->query('perPage', 10);
        $page = min((int) $request->query('page', 1), max(1, (int) ceil($rows->count() / $perPage)));
        return ['cars' => new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->except(['car', 'history', 'export', 'page'])]),
            'summary' => $summary, 'departments' => $departments, 'legacyCount' => $legacyCount,
            'exportRows' => $rows->where('formal', true)->values()];
    }
}
