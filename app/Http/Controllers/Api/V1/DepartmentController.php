<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
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

    public function organizationSummary(Request $request)
    {
        abort_unless((int) $request->user()->roleId === 0, 403);
        return response()->json(['users' => User::where('isActive', true)
            ->get(['fullName', 'departmentId', 'archiveResponsibility', 'archiveUnitIds', 'archiveCollegeIds'])
            ->map(fn ($user) => ['name' => $user->fullName,
                'departmentId' => $user->departmentId ? (string) $user->departmentId : null,
                'responsibility' => $user->archiveResponsibility ?: 'faculty_staff',
                'unitIds' => array_map('strval', $user->archiveUnitIds ?? []),
                'collegeIds' => array_map('strval', $user->archiveCollegeIds ?? [])])]);
    }

    public function index(Request $request)
    {
        $sort = $request->validate([
            'sortBy' => 'sometimes|in:departmentName,departmentCode,campusName,collegeName',
            'sortDirection' => 'sometimes|in:asc,desc',
        ]);
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

        $summary = $request->boolean('includeSummary') ? [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('isActive', true)->count(),
            'inactive' => (clone $query)->where('isActive', false)->count(),
        ] : null;
        $page = $query->orderBy($sort['sortBy'] ?? 'departmentName', $sort['sortDirection'] ?? 'asc')->orderBy('_id')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100), total: $summary['total'] ?? null);

        return response()->json([
            'data' => collect($page->items())->map(fn (Department $department) => $this->serialize($department)),
            ...($summary !== null ? ['summary' => $summary] : []),
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
            'collegeName' => ['nullable', 'string', 'max:100'],
            'campusName' => ['required', 'string', 'max:100'],
        ]);
        $data['collegeName'] ??= null;
        $this->validateCampusCollege($data['campusName'], $data['collegeName']);
        if ((int) $request->user()->roleId === 1 && $data['collegeName'] !== 'CET') {
            abort(403, 'Admins may only manage CET departments.');
        }
        if (Department::where('departmentCode', $data['departmentCode'])->exists()) {
            return response()->json(['message' => 'The department code has already been taken.'], 422);
        }

        $this->validateCetDepartment($data['collegeName'], $data['departmentName']);
        if ($data['collegeName'] === 'CET') {
            abort_if(Department::where('collegeName', 'CET')->where('departmentName', $data['departmentName'])->exists(), 422, 'This CET department already exists.');
            $college = \App\Models\College::where('collegeCode', 'CET')->firstOrFail();
            $data += ['collegeId' => $college->getKey(), 'unitType' => 'academic_department', 'ownershipVerified' => true];
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
            'collegeName' => ['sometimes', 'nullable', 'string', 'max:100'],
            'campusName' => ['sometimes', 'required', 'string', 'max:100'],
            'headUserId' => ['sometimes', 'nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $collegeName = array_key_exists('collegeName', $data) ? $data['collegeName'] : $department->collegeName;
        if (array_key_exists('campusName', $data) || array_key_exists('collegeName', $data)) {
            $this->validateCampusCollege($data['campusName'] ?? $department->campusName,
                $collegeName);
        }
        if ((int) $request->user()->roleId === 1
            && $collegeName !== 'CET') {
            abort(403, 'Admins may only manage CET departments.');
        }
        $this->validateCetDepartment($collegeName, $data['departmentName'] ?? $department->departmentName);
        if ($collegeName === 'CET') {
            abort_if(Department::where('collegeName', 'CET')->where('departmentName', $data['departmentName'] ?? $department->departmentName)
                ->where('_id', '!=', $department->getKey())->exists(), 422, 'This CET department already exists. Organizational merges require a reviewed migration.');
        }
        if ($department->ownershipVerified) {
            abort_if(isset($data['departmentName']) && $data['departmentName'] !== $department->departmentName
                || array_key_exists('collegeName', $data) && $data['collegeName'] !== $department->collegeName
                || isset($data['campusName']) && $data['campusName'] !== $department->campusName, 422, 'Verified organizational ownership cannot be changed through a department rename.');
        }
        if (!empty($data['headUserId'])) {
            $head = User::where('_id', MongoId::parse($data['headUserId'], 'headUserId'))
                ->where('departmentId', $department->getKey())
                ->whereIn('roleId', [2, 3])
                ->exists();
            if (!$head) {
                return response()->json([
                    'message' => 'The department head must be an auditor or auditee assigned to this department.',
                ], 422);
            }
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
        $departmentId = (string) $department->getKey();
        User::query()->get()
            ->each(function (User $user) use ($request, $departmentId) {
                if ((int) $user->roleId === 0) {
                    return;
                }
                $storedActive = strtolower(trim((string) $user->getRawOriginal('isActive')));
                $isActive = in_array($storedActive, ['1', 'true', 'yes', 'on'], true);
                $assignedDepartmentIds = array_filter([
                    (string) $user->departmentId,
                    ...($user->assignedDepartmentIds ?? []),
                ]);
                if (! $isActive || ! in_array($departmentId, $assignedDepartmentIds, true)) {
                    return;
                }

                $user->isActive = false;
                $user->save();
                $this->auditLogs->record($request, 'Deactivated user because department was deactivated', 'user', $user->getKey());
            });
        $this->auditLogs->record($request, 'Deactivated department', 'department', $department->getKey());

        return response()->json(['message' => 'Department deactivated.']);
    }

    public function reactivate(Request $request, string $id)
    {
        $department = $this->scopes->departments($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $department->isActive = true;
        $department->save();
        $this->auditLogs->record($request, 'Reactivated department', 'department', $department->getKey());

        return response()->json(['message' => 'Department reactivated.']);
    }

    private function serialize(Department $department): array
    {
        $data = $department->toArray();
        $data['_id'] = (string) $department->getKey();
        unset($data['id']);

        return $data;
    }

    private function validateCampusCollege(string $campus, ?string $college): void
    {
        $catalog = config('qms.campusColleges');
        if (!array_key_exists($campus, $catalog)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['campusName' => 'Select a USTP campus.']);
        }
        if ($catalog[$campus] === [] ? ($college !== null && $college !== '') : !array_key_exists($college ?? '', $catalog[$campus])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['collegeName' => 'Select a college listed for the selected campus, or leave it unassigned when no college list is available.']);
        }
    }

    private function validateCetDepartment(?string $college, string $name): void
    {
        abort_if($college === 'CET' && strtolower(trim($name)) === 'office of the dean', 422,
            'The Office of the Dean is a college office, not an academic department.');
    }
}
