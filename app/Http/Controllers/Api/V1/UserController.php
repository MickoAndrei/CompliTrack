<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CounterService;
use App\Services\PasswordResetService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use MongoDB\BSON\ObjectId;

class UserController extends Controller
{
    public function __construct(
        private readonly RoleScopeService $scopes,
        private readonly CounterService $counters,
        private readonly AuditLogService $auditLogs,
        private readonly PasswordResetService $passwordResets,
    ) {}

    public function index(Request $request)
    {
        $this->deactivateUsersWithInactiveDepartments($request);
        $query = $this->scopes->users($request->user());
        if ($request->filled('roleId')) {
            $query->where('roleId', (int) $request->query('roleId'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($query) use ($search) {
                $query->where('fullName', 'regex', '/'.preg_quote($search, '/').'/i')
                    ->orWhere('email', 'regex', '/'.preg_quote($search, '/').'/i')
                    ->orWhere('userCode', 'regex', '/'.preg_quote($search, '/').'/i');
            });
        }

        $page = $query->orderBy('fullName')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));

        return response()->json([
            'data' => collect($page->items())->map(fn (User $user) => $this->serialize($user)),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    private function deactivateUsersWithInactiveDepartments(Request $request): void
    {
        $inactiveDepartmentIds = Department::where('isActive', false)->pluck('_id')
            ->map(static fn ($id) => (string) $id)
            ->all();
        if ($inactiveDepartmentIds === []) {
            return;
        }

        $this->scopes->users($request->user())
            ->get()
            ->each(function (User $user) use ($request, $inactiveDepartmentIds) {
                if ((int) $user->roleId === 0) {
                    return;
                }
                $userDepartmentIds = array_filter([
                    (string) $user->departmentId,
                    ...($user->assignedDepartmentIds ?? []),
                ]);
                $isActive = $this->isStoredAsActive($user->getRawOriginal('isActive'));
                if (! $isActive || array_intersect($inactiveDepartmentIds, $userDepartmentIds) === []) {
                    return;
                }

                $user->isActive = false;
                $user->save();
                $this->auditLogs->record($request, 'Deactivated user because assigned department is inactive', 'user', $user->getKey());
            });
    }

    private function isStoredAsActive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public function auditees(Request $request)
    {
        $request->query->set('roleId', '3');

        return $this->index($request);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'roleId' => ['required', 'integer', 'between:0,3'],
            'departmentId' => ['nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'assignedDepartmentIds' => ['sometimes', 'array'],
            'assignedDepartmentIds.*' => ['string', 'regex:/^[a-f0-9]{24}$/'],
            'officeName' => ['nullable', 'string', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $email = strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'The email has already been taken.'], 422);
        }

        $actor = $request->user();
        $roleId = (int) $data['roleId'];
        if ($roleId === 0) {
            $data['isActive'] = true;
        }
        if ((int) $actor->roleId === 1 && ! in_array($roleId, [2, 3], true)) {
            abort(403, 'Admins may only create auditors and auditees.');
        }
        $departmentId = isset($data['departmentId'])
            ? MongoId::parse($data['departmentId'], 'departmentId')
            : null;
        $this->validateDepartmentAssignment($actor, $roleId, $departmentId, $data['assignedDepartmentIds'] ?? []);
        if ($roleId !== 0 && ! $departmentId) {
            return response()->json(['message' => 'A department is required for this role.'], 422);
        }
        if ($roleId === 3 && empty($data['officeName'])) {
            return response()->json(['message' => 'An office name is required for auditees.'], 422);
        }

        $prefix = [0 => 'SA', 1 => 'ADM', 2 => 'AUD', 3 => 'ATE'][$roleId];
        $user = User::create([
            'userCode' => $this->counters->next($prefix),
            'fullName' => trim($data['fullName']),
            'email' => $email,
            'password' => Hash::make(Str::random(48), ['rounds' => 12]),
            'roleId' => $roleId,
            'roleSlug' => [0 => 'super_admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'][$roleId],
            'departmentId' => $departmentId,
            'assignedDepartmentIds' => array_map(
                static fn (string $id) => MongoId::parse($id, 'assignedDepartmentIds'),
                $data['assignedDepartmentIds'] ?? ($roleId === 2 && $departmentId ? [(string) $departmentId] : []),
            ),
            'officeName' => $data['officeName'] ?? null,
            'isActive' => $data['isActive'] ?? true,
        ]);

        $this->passwordResets->send($user);
        $this->auditLogs->record($request, 'Created user', 'user', $user->getKey());

        return response()->json(['data' => $this->serialize($user)], 201);
    }

    public function show(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();

        return response()->json(['data' => $this->serialize($user)]);
    }

    public function update(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $data = $request->validate([
            'fullName' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255'],
            'roleId' => ['sometimes', 'required', 'integer', 'between:0,3'],
            'departmentId' => ['sometimes', 'nullable', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'assignedDepartmentIds' => ['sometimes', 'array'],
            'assignedDepartmentIds.*' => ['string', 'regex:/^[a-f0-9]{24}$/'],
            'officeName' => ['sometimes', 'nullable', 'string', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $requestedInactive = array_key_exists('isActive', $data)
            && ! filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN);
        if ((int) $user->roleId === 0
            && ($requestedInactive || (isset($data['roleId']) && (int) $data['roleId'] !== 0))) {
            return response()->json(['message' => 'Super admin accounts cannot be deactivated or demoted.'], 422);
        }
        if ((int) $user->roleId === 0) {
            $data['isActive'] = true;
        }

        if (isset($data['email'])) {
            $email = strtolower(trim($data['email']));
            if (User::where('email', $email)->where('_id', '!=', $user->getKey())->exists()) {
                return response()->json(['message' => 'The email has already been taken.'], 422);
            }
            $data['email'] = $email;
        }

        $roleId = (int) ($data['roleId'] ?? $user->roleId);
        if ((int) $request->user()->roleId === 1 && ! in_array($roleId, [2, 3], true)) {
            abort(403, 'Admins may only manage auditors and auditees.');
        }
        $departmentId = array_key_exists('departmentId', $data)
            ? ($data['departmentId'] ? MongoId::parse($data['departmentId'], 'departmentId') : null)
            : ($user->departmentId ? MongoId::parse((string) $user->departmentId, 'departmentId') : null);
        $assignedIds = $data['assignedDepartmentIds'] ?? ($user->assignedDepartmentIds ?? []);
        $this->validateDepartmentAssignment($request->user(), $roleId, $departmentId, $assignedIds);

        $data['roleId'] = $roleId;
        $data['roleSlug'] = [0 => 'super_admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'][$roleId];
        $data['departmentId'] = $departmentId;
        $data['assignedDepartmentIds'] = array_map(
            static fn ($value) => MongoId::parse((string) $value, 'assignedDepartmentIds'),
            $assignedIds,
        );
        $user->fill($data)->save();

        $this->auditLogs->record($request, 'Updated user', 'user', $user->getKey());

        return response()->json(['data' => $this->serialize($user->fresh())]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        if ((int) $user->roleId === 0) {
            return response()->json(['message' => 'Super admin accounts cannot be deactivated.'], 422);
        }
        $user->isActive = false;
        $user->save();
        $this->auditLogs->record($request, 'Deactivated user', 'user', $user->getKey());

        return response()->json(['message' => 'User deactivated.']);
    }

    public function reactivate(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $departmentIds = collect($user->assignedDepartmentIds ?? [])
            ->map(static fn ($departmentId) => (string) $departmentId)
            ->push((string) $user->departmentId)
            ->filter()
            ->unique()
            ->map(static fn (string $departmentId) => MongoId::parse($departmentId))
            ->all();
        $inactiveDepartments = $departmentIds === []
            ? collect()
            : Department::whereIn('_id', $departmentIds)->where('isActive', false)->pluck('departmentName');
        if ($inactiveDepartments->isNotEmpty()) {
            return response()->json([
                'message' => 'Reactivate the department'.($inactiveDepartments->count() > 1 ? 's' : '').' ('
                    .$inactiveDepartments->implode(', ').') before reactivating this user.',
            ], 422);
        }
        $user->isActive = true;
        $user->save();
        $this->auditLogs->record($request, 'Reactivated user', 'user', $user->getKey());

        return response()->json(['message' => 'User reactivated.']);
    }

    public function resetPassword(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $this->passwordResets->send($user);
        $this->auditLogs->record($request, 'Sent password reset link', 'user', $user->getKey());

        return response()->json(['message' => 'A password reset link was sent.']);
    }

    private function validateDepartmentAssignment(
        User $actor,
        int $roleId,
        ?ObjectId $departmentId,
        array $assignedDepartmentIds,
    ): void {
        if ($roleId === 0) {
            return;
        }

        $ids = $assignedDepartmentIds;
        if ($roleId === 2 && $departmentId && $ids === []) {
            $ids = [(string) $departmentId];
        }

        if ($departmentId) {
            $ids[] = (string) $departmentId;
        }
        foreach (array_unique(array_map('strval', $ids)) as $id) {
            $objectId = MongoId::parse($id, 'assignedDepartmentIds');
            $department = Department::find($objectId);
            if (! $department || ! $department->isActive
                || ((int) $actor->roleId === 1 && $department->collegeName !== 'CET')) {
                abort(422, 'The selected department is unavailable or outside your scope.');
            }
        }
    }

    private function serialize(User $user): array
    {
        $data = $user->toArray();
        $data['_id'] = (string) $user->getKey();
        $data['isActive'] = (int) $user->roleId === 0 || $this->isStoredAsActive($user->getRawOriginal('isActive'));
        unset($data['id'], $data['password']);
        $department = $user->departmentId ? Department::find($user->departmentId) : null;
        $data['departmentName'] = $department?->departmentName;

        return $data;
    }
}
