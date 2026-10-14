<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditLogService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly RoleScopeService $scopes,
        private readonly AuditLogService $auditLogs,
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->scopes->departments($request->user());
        if ($request->filled('campusName')) {
            $query->where('campusName', $request->query('campusName'));
        }
        if ($request->filled('search')) {
            $search = '/'.preg_quote(trim((string) $request->query('search')), '/').'/i';
            $query->where(function ($query) use ($search) {
                $query->where('departmentName', 'regex', $search)
                    ->orWhere('departmentCode', 'regex', $search);
            });
        }

        $page = $query->orderBy('departmentName')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (Department $department) => $this->serialize($department)),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'departmentCode' => ['required', 'string', 'max:30'],
            'departmentName' => ['required', 'string', 'max:255'],
            'collegeName' => ['required', 'string', 'max:100'],
            'campusName' => ['required', 'string', 'max:100'],
        ]);
        if ((int) $request->user()->roleId === 1 && $data['collegeName'] !== 'CET') {
            abort(403, 'Admins may only manage CET departments.');
        }
        if (Department::where('departmentCode', $data['departmentCode'])->exists()) {
            return response()->json(['message' => 'The department code has already been taken.'], 422);
        }

        $department = Department::create($data + [
            'isActive' => true,
            'headUserId' => null,
        ]);
        $this->auditLogs->record($request, 'Created department', 'department', $department->getKey());

        return response()->json(['data' => $this->serialize($department)], 201);
    }

    public function show(Request $request, string $id)
    {
        $department = $this->scopes->departments($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();

        return response()->json(['data' => $this->serialize($department)]);
    }

    public function update(Request $request, string $id)
    {
        $department = $this->scopes->departments($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $data = $request->validate([
            'departmentName' => ['sometimes', 'required', 'string', 'max:255'],
            'collegeName' => ['sometimes', 'required', 'string', 'max:100'],
            'campusName' => ['sometimes', 'required', 'string', 'max:100'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        if ((int) $request->user()->roleId === 1
            && ($data['collegeName'] ?? $department->collegeName) !== 'CET') {
            abort(403, 'Admins may only manage CET departments.');
        }
        $department->fill($data)->save();
        $this->auditLogs->record($request, 'Updated department', 'department', $department->getKey());

        return response()->json(['data' => $this->serialize($department->fresh())]);
    }

    public function destroy(Request $request, string $id)
    {
        $department = $this->scopes->departments($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $department->isActive = false;
        $department->save();
        $this->auditLogs->record($request, 'Deactivated department', 'department', $department->getKey());

        return response()->json(['message' => 'Department deactivated.']);
    }

    private function serialize(Department $department): array
    {
        $data = $department->toArray();
        $data['_id'] = (string) $department->getKey();
        unset($data['id']);

        return $data;
    }
}
