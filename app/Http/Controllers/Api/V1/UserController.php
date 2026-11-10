<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\Audit;
use App\Models\College;
use App\Services\UserOrganization;
use App\Services\UserManagementPresenter;
use App\Services\AuditLogService;
use App\Services\CounterService;
use App\Services\PasswordResetService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;
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
        $request->validate(['roleId' => 'sometimes|integer|between:0,3', 'departmentId' => 'sometimes|nullable|string|regex:/^[a-f0-9]{24}$/i',
            'responsibility' => 'sometimes|nullable|string|in:'.implode(',', config('qms.responsibilities')),
            'status' => 'sometimes|nullable|in:active,inactive', 'search' => 'sometimes|nullable|string|max:255']);
        $management = $request->boolean('management');
        abort_if($management && (int) $request->user()->roleId !== 0, 403);
        $summaryQuery = $this->scopes->users($request->user());
        $query = $this->scopes->users($request->user());
        if ($request->filled('roleId')) {
            $query->where('roleId', (int) $request->query('roleId'));
        }
        if ($request->filled('departmentId')) {
            $values = [MongoId::parse($request->query('departmentId')), $request->query('departmentId')];
            $query->where(fn ($q) => $q->whereIn('departmentId', $values)->orWhereIn('archiveUnitIds', $values)->orWhereIn('assignedDepartmentIds', $values));
        }
        if ($request->filled('responsibility')) {
            $query->where('archiveResponsibility', $request->query('responsibility'));
        }
        if ($request->filled('status')) {
            $query->where('isActive', $request->query('status') === 'active');
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
            ...($management ? ['summary' => ['total' => (clone $summaryQuery)->count(), 'active' => (clone $summaryQuery)->where('isActive', true)->count(),
                'auditors' => (clone $summaryQuery)->where('roleId', 2)->count(), 'auditees' => (clone $summaryQuery)->where('roleId', 3)->count()],
                'options' => app(UserManagementPresenter::class)->options()] : []),
        ]);
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
            ...$this->organizationRules(),
        ]);

        $email = strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'The email has already been taken.'], 422);
        }

        $actor = $request->user();
        $roleId = (int) $data['roleId'];
        $organization = $this->organizationInput($request, $roleId, $data);
        $data = array_merge($data, $organization);
        if ($roleId === 0) {
            $data['isActive'] = true;
        }
        if ((int) $actor->roleId === 1 && ! in_array($roleId, [2, 3], true)) {
            abort(403, 'Admins may only create auditors and auditees.');
        }
        $departmentId = isset($data['departmentId'])
            ? MongoId::parse($data['departmentId'], 'departmentId')
            : null;
        $collegeRole = $this->isCollegeResponsibility($data['archiveResponsibility'] ?? null);
        $this->validateDepartmentAssignment($actor, $roleId, $departmentId, $data['assignedDepartmentIds'] ?? [], $collegeRole, $data['archiveCollegeIds'] ?? []);
        if ($roleId !== 0 && ! $departmentId && !$collegeRole) {
            return response()->json(['message' => 'A department is required for this role.'], 422);
        }
        if ($roleId === 3 && empty($data['officeName']) && !$collegeRole && empty($data['archiveResponsibility'])) {
            return response()->json(['message' => 'An office name is required for auditees.'], 422);
        }

        $prefix = [0 => 'SA', 1 => 'ADM', 2 => 'AUD', 3 => 'ATE'][$roleId];
        $user = User::create([
            'userCode' => $this->counters->next($prefix),
            'fullName' => trim($data['fullName']),
            'email' => $email,
            'password' => Str::random(48), // The User model hashes using the configured password policy.
            'roleId' => $roleId,
            'roleSlug' => [0 => 'super_admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'][$roleId],
            'departmentId' => $departmentId,
            'assignedDepartmentIds' => array_map(
                static fn (string $id) => MongoId::parse($id, 'assignedDepartmentIds'),
                $data['assignedDepartmentIds'] ?? ($roleId === 2 && $departmentId ? [(string) $departmentId] : []),
            ),
            'officeName' => $data['officeName'] ?? null,
            'isActive' => $data['isActive'] ?? true,
            ...$organization,
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

        $details = (int) $request->user()->roleId === 0 ? app(UserManagementPresenter::class)->details($user) : [];
        return response()->json(['data' => array_merge($this->serialize($user), $details)]);
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
            ...$this->organizationRules(),
        ]);

        $requestedInactive = array_key_exists('isActive', $data)
            && ! filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN);
        if ($requestedInactive) { $this->guardDeactivation($request, $user); }
        if ($this->isAuditTeamLeader($user) && ((isset($data['roleId']) && (int) $data['roleId'] !== 1)
            || (array_key_exists('departmentId', $data) && (string) $data['departmentId'] !== (string) $user->departmentId)
            || (isset($data['assignedDepartmentIds']) && array_map('strval', $data['assignedDepartmentIds']) !== array_map('strval', $user->assignedDepartmentIds ?? [])))) {
            abort(422, 'Preserve the existing Admin / Audit Team Leader role and audit assignments.');
        }
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
        $organization = $this->organizationInput($request, $roleId, $data, $user);
        foreach (['archiveResponsibility', 'archiveUnitIds', 'archiveCollegeIds'] as $key) { unset($data[$key]); }
        $data = array_merge($data, $organization);
        $collegeRole = $this->isCollegeResponsibility(array_key_exists('archiveResponsibility', $data) ? $data['archiveResponsibility'] : $user->archiveResponsibility);
        if ((int) $request->user()->roleId === 1 && ! in_array($roleId, [2, 3], true)) {
            abort(403, 'Admins may only manage auditors and auditees.');
        }
        $departmentId = array_key_exists('departmentId', $data)
            ? ($data['departmentId'] ? MongoId::parse($data['departmentId'], 'departmentId') : null)
            : ($user->departmentId ? MongoId::parse((string) $user->departmentId, 'departmentId') : null);
        $assignedIds = $data['assignedDepartmentIds'] ?? ($user->assignedDepartmentIds ?? []);
        abort_if(!$collegeRole && $roleId !== 0 && !$departmentId
            && ($roleId !== (int) $user->roleId || array_key_exists('departmentId', $data)), 422, 'A department is required for this role.');
        $oldAssignments = array_map('strval', $user->assignedDepartmentIds ?? []);
        $newAssignments = array_map('strval', $assignedIds);
        sort($oldAssignments); sort($newAssignments);
        // Unverified legacy ownership blocks new assignments, not unrelated account maintenance.
        if ($roleId !== (int) $user->roleId || (string) $departmentId !== (string) $user->departmentId || $oldAssignments !== $newAssignments) {
            $this->validateDepartmentAssignment($request->user(), $roleId, $departmentId, $assignedIds, $collegeRole, $data['archiveCollegeIds'] ?? $user->archiveCollegeIds ?? []);
        }
        if ($collegeRole && $departmentId) {
            $unit = Department::find($departmentId);
            abort_unless($unit && $unit->unitType === 'college_office' && in_array((string) $unit->collegeId, array_map('strval', $data['archiveCollegeIds'] ?? $user->archiveCollegeIds ?? []), true), 422,
                'College responsibilities belong to a college, not an individual department.');
        }
        if (isset($data['isActive']) && $data['isActive'] && !$user->isActive) {
            $this->ensureDepartmentsActive($departmentId, $assignedIds);
        }

        if ($roleId !== (int) $user->roleId) {
            $data['roleId'] = $roleId;
            $data['roleSlug'] = [0 => 'super_admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'][$roleId];
        } else { unset($data['roleId']); }
        if (array_key_exists('departmentId', $data) && (string) $departmentId !== (string) $user->departmentId) {
            $data['departmentId'] = $departmentId;
        } else { unset($data['departmentId']); }
        if ($oldAssignments !== $newAssignments) {
            $data['assignedDepartmentIds'] = array_map(static fn ($value) => MongoId::parse((string) $value, 'assignedDepartmentIds'), $assignedIds);
        } else { unset($data['assignedDepartmentIds']); }
        $user->fill($data)->save();

        $this->auditLogs->record($request, 'Updated user', 'user', $user->getKey());

        return response()->json(['data' => $this->serialize($user->fresh())]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $this->scopes->users($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $this->guardDeactivation($request, $user);
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
        bool $collegeRole = false,
        array $collegeIds = [],
    ): void {
        if ($collegeRole && $departmentId) {
            $unit = Department::find($departmentId);
            abort_unless($unit && $unit->unitType === 'college_office' && in_array((string) $unit->collegeId, array_map('strval', $collegeIds), true), 422,
                'College responsibilities belong to a college, not an individual department.');
        }
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
            if (! $department || ! $department->isActive || $department->unitType === 'legacy_unclassified'
                || ($department->unitType === 'college_office' && $roleId !== 3 && !($collegeRole && in_array((string) $department->collegeId, array_map('strval', $collegeIds), true)))
                || ((int) $actor->roleId === 1 && $department->collegeName !== 'CET')) {
                abort(422, 'The selected department is unavailable or outside your scope.');
            }
        }
    }

    private function serialize(User $user): array
    {
        $data = $user->toArray();
        $data['_id'] = (string) $user->getKey();
        $data['roleSlug'] = $user->roleSlug ?? [0 => 'super_admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'][(int) $user->roleId] ?? 'unknown';
        $data['isActive'] = (int) $user->roleId === 0 || $this->isStoredAsActive($user->getRawOriginal('isActive'));
        unset($data['id'], $data['password']);
        $department = $user->departmentId ? Department::find($user->departmentId) : null;
        $data['departmentName'] = $department?->departmentName;
        $data['departmentId'] = $user->departmentId ? (string) $user->departmentId : null;
        foreach (['assignedDepartmentIds', 'archiveUnitIds', 'archiveCollegeIds'] as $key) { $data[$key] = array_map('strval', $user->$key ?? []); }
        $data['organizationalUnits'] = Department::whereIn('_id', app(\App\Services\QmsArchiveAccess::class)->ids($user->archiveUnitIds ?? []))
            ->get(['departmentName'])->map(fn ($unit) => $unit->departmentName)->values()->all();
        $data['organizationalColleges'] = College::whereIn('_id', app(\App\Services\QmsArchiveAccess::class)->ids($user->archiveCollegeIds ?? []))
            ->get(['collegeName'])->map(fn ($college) => $college->collegeName)->values()->all();
        $data['isAuditTeamLeader'] = $this->isAuditTeamLeader($user);
        $data['canDeactivate'] = (int) $user->roleId !== 0 && !$data['isAuditTeamLeader'];

        return $data;
    }

    private function organizationRules(): array
    {
        return ['archiveResponsibility' => 'sometimes|nullable|string|in:'.implode(',', config('qms.responsibilities')),
            'archiveUnitIds' => 'sometimes|array|max:50', 'archiveUnitIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct',
            'archiveCollegeIds' => 'sometimes|array|max:20', 'archiveCollegeIds.*' => 'string|regex:/^[a-f0-9]{24}$/i|distinct'];
    }

    private function organizationInput(Request $request, int $role, array $data, ?User $user = null): array
    {
        $keys = ['archiveResponsibility', 'archiveUnitIds', 'archiveCollegeIds'];
        $explicit = array_intersect($keys, array_keys($data)) !== [];
        abort_if($explicit && (int) $request->user()->roleId !== 0, 403, 'Only Super Admin may assign organizational responsibilities.');
        $changed = $user && $role !== (int) $user->roleId;
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && ($key === 'archiveResponsibility'
                ? ($data[$key] ?: null) !== ($user?->$key ?: null)
                : array_map('strval', $data[$key]) !== array_map('strval', $user?->$key ?? []))) { $changed = true; }
        }
        if ($user && !$changed) { return []; } // Preserve unchanged legacy relationships during unrelated maintenance.
        if (!$explicit && !$changed) { return []; }
        return app(UserOrganization::class)->validate($role, array_key_exists('archiveResponsibility', $data) ? $data['archiveResponsibility'] : $user?->archiveResponsibility,
            $data['archiveUnitIds'] ?? $user?->archiveUnitIds ?? [], $data['archiveCollegeIds'] ?? $user?->archiveCollegeIds ?? []);
    }

    private function isCollegeResponsibility(?string $responsibility): bool
    {
        return in_array($responsibility, ['college_dean', 'college_secretary', 'cluster_administrator'], true);
    }

    private function isAuditTeamLeader(User $user): bool
    {
        return (int) $user->roleId === 1 && Audit::whereIn('reviewerId', [MongoId::parse((string) $user->getKey()), (string) $user->getKey()])->exists();
    }

    private function guardDeactivation(Request $request, User $user): void
    {
        abort_if((string) $request->user()->getKey() === (string) $user->getKey(), 422, 'You cannot deactivate your current account.');
        // Existing policy protects every Super Admin, which also protects the last active Super Admin without a count race.
        abort_if((int) $user->roleId === 0, 422, 'Super Admin accounts cannot be deactivated or demoted.');
        abort_if($this->isAuditTeamLeader($user), 422, 'Preserve the existing Admin account serving as Audit Team Leader.');
    }

    private function ensureDepartmentsActive(?ObjectId $departmentId, array $assignedIds): void
    {
        $ids = app(\App\Services\QmsArchiveAccess::class)->ids(array_merge($assignedIds, $departmentId ? [(string) $departmentId] : []));
        abort_if(Department::whereIn('_id', $ids)->where('isActive', false)->exists(), 422, 'Reactivate the assigned departments before reactivating this user.');
    }
}
