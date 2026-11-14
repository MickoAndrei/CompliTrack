<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\RoleScopeService;
use App\Support\AuditLogFields;
use App\Support\MongoId;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly RoleScopeService $scopes) {}

    public function index(Request $request)
    {
        $request->validate([
            'category' => ['nullable', 'in:'.implode(',', AuditLogFields::CATEGORIES)],
            'outcome' => ['nullable', 'in:success,failed,denied'],
            'actor' => ['nullable', 'string', 'max:200'],
            'search' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', 'max:200'],
            'entityType' => ['nullable', 'string', 'max:100'],
            'userId' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/i'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', ...($request->filled('startDate') ? ['after_or_equal:startDate'] : [])],
        ]);
        $query = $this->scopes->auditLogs($request->user());

        if ($request->filled('category')) {
            $query->whereRaw(AuditLogFields::categoryFilter($request->query('category')));
        }
        if ($request->filled('outcome')) {
            $query->whereRaw(AuditLogFields::outcomeFilter($request->query('outcome')));
        }
        if ($request->filled('actor')) {
            $query->where('userName', 'regex', '/'.preg_quote(trim($request->query('actor')), '/').'/i');
        }

        foreach (['userId', 'action', 'entityType'] as $field) {
            if ($request->filled($field)) {
                $value = $request->query($field);
                if ($field === 'userId') {
                    $value = MongoId::parse((string) $value, $field);
                }
                $query->where($field, $value);
            }
        }
        if ($request->filled('search')) {
            $pattern = '/'.preg_quote(trim((string) $request->query('search')), '/').'/i';
            $query->where(function ($query) use ($pattern) {
                $query->where('action', 'regex', $pattern)
                    ->orWhere('userName', 'regex', $pattern);
            });
        }
        if ($request->filled('startDate')) {
            $query->where('createdAt', '>=', $request->date('startDate')->startOfDay());
        }
        if ($request->filled('endDate')) {
            $query->where('createdAt', '<', $request->date('endDate')->startOfDay()->addDay());
        }

        $summary = null;
        if ($request->boolean('includeSummary')) {
            $summary = [
                'activities' => (clone $query)->count(),
                'security' => (clone $query)->whereRaw(AuditLogFields::categoryFilter('security'))->count(),
                'denied' => (clone $query)->where('outcome', 'denied')->count(),
            ];
        }

        $sortableFields = ['createdAt', 'action', 'userName', 'entityType'];
        $sortBy = (string) $request->query('sortBy', 'createdAt');
        $sortDirection = strtolower((string) $request->query('sortDirection', 'desc'));
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'createdAt';
        }
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $page = $query->orderBy($sortBy, $sortDirection)
            ->orderBy('_id', $sortDirection)
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));
        $data = collect($page->items())->map(fn (AuditLog $log) => $this->serialize($log));

        return response()->json([
            'data' => $data,
            'summary' => $summary,
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $log = $this->scopes->auditLogs($request->user())->where('_id', MongoId::parse($id))->firstOrFail();

        return response()->json(['data' => $this->serialize($log)]);
    }

    private function serialize(AuditLog $log): array
    {
        // Only audit metadata is public to this authorized screen. Never expose
        // request payloads, headers, tokens, file paths or protected QMS content.
        return [
            '_id' => (string) $log->getKey(),
            'action' => $log->action,
            'userId' => $log->userId ? (string) $log->userId : null,
            'userName' => $log->userName ?? ($log->userId ? User::find($log->userId)?->fullName : null) ?? 'System',
            'entityType' => $log->entityType,
            'entityId' => $log->entityId ? (string) $log->entityId : null,
            'category' => $log->category ?? AuditLogFields::category($log->action ?? '', $log->entityType ?? ''),
            'outcome' => $log->outcome ?? 'success',
            'createdAt' => $log->createdAt,
            'ipAddress' => $log->ipAddress,
            'method' => $log->method,
            'route' => $log->route,
        ];
    }
}
