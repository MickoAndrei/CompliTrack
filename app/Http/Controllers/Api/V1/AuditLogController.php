<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(private readonly RoleScopeService $scopes)
    {
    }

    public function index(Request $request)
    {
        $query = $this->scopes->auditLogs($request->user());

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
            $query->where('createdAt', '>=', $request->date('startDate'));
        }
        if ($request->filled('endDate')) {
            $query->where('createdAt', '<=', $request->date('endDate'));
        }

        $sortableFields = ['createdAt', 'action', 'userName', 'entityType'];
        $sortBy = (string) $request->query('sortBy', 'createdAt');
        $sortDirection = strtolower((string) $request->query('sortDirection', 'desc'));
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'createdAt';
        }
        if (!in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $page = $query->orderBy($sortBy, $sortDirection)
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));
        $data = collect($page->items())->map(function (AuditLog $log) {
            $data = $log->toArray();
            $data['_id'] = (string) $log->getKey();
            unset($data['id']);
            $data['userName'] ??= User::find($log->userId)?->fullName ?? 'System';

            return $data;
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }
}
