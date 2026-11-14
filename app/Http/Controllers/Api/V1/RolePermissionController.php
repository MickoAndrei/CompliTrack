<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Role, User};
use App\Services\{AuditLogService, AuditPlanPermissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RolePermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeManager($request);
        return response()->json(['permission' => AuditPlanPermissions::APPROVE,
            'roles' => Role::orderBy('roleId')->get()->map(fn ($role) => [
                'roleId' => (int) $role->roleId, 'name' => $role->roleName,
                'enabled' => in_array(AuditPlanPermissions::APPROVE, $role->permissions ?? [], true),
                'version' => (int) ($role->permissionVersion ?? 0), 'history' => $role->permissionHistory ?? [],
            ])])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $id)
    {
        $actor = $this->authorizeManager($request);
        $data = $request->validate(['permission' => 'required|in:audit_plans.approve', 'enabled' => 'required|boolean',
            'version' => 'required|integer|min:0', 'password' => 'required|string|max:1024', 'remarks' => 'required|string|max:2000']);
        if (!Hash::check($data['password'], $actor->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
        }
        abort_unless(in_array($id, ['0', '1', '2', '3'], true), 404);
        $role = Role::where('roleId', (int) $id)->firstOrFail();
        $version = (int) ($role->permissionVersion ?? 0);
        abort_unless($version === (int) $data['version'], 409, 'Permissions changed. Reload before saving.');
        $permissions = array_values(array_filter($role->permissions ?? [], fn ($p) => $p !== AuditPlanPermissions::APPROVE));
        if ($request->boolean('enabled')) { $permissions[] = AuditPlanPermissions::APPROVE; }
        $history = $role->permissionHistory ?? [];
        $history[] = ['permission' => AuditPlanPermissions::APPROVE, 'enabled' => $request->boolean('enabled'),
            'previousEnabled' => in_array(AuditPlanPermissions::APPROVE, $role->permissions ?? [], true),
            'actorId' => \App\Support\MongoId::parse((string) $actor->getKey()), 'actorName' => $actor->fullName, 'at' => now()->utc()->toIso8601String(), 'remarks' => $data['remarks']];
        $query = Role::where('_id', $role->getKey());
        if ($version === 0) { $query->where(fn ($q) => $q->where('permissionVersion', 0)->orWhereNull('permissionVersion')); }
        else { $query->where('permissionVersion', $version); }
        abort_unless($query->update(['permissions' => $permissions, 'permissionVersion' => $version + 1, 'permissionHistory' => $history]) === 1,
            409, 'Permissions changed. Reload before saving.');
        app(AuditLogService::class)->record($request, $request->boolean('enabled') ? 'Granted audit plan approval permission' : 'Revoked audit plan approval permission', 'role', $role->getKey());
        return $this->index($request);
    }

    private function authorizeManager(Request $request): User
    {
        $actor = User::find($request->user()->getKey());
        abort_unless($actor && $actor->isActive && (int) $actor->roleId === 0, 403);
        return $actor;
    }
}
