<?php

namespace App\Services;

use App\Models\{Role, User};

class AuditPlanPermissions
{
    public const APPROVE = 'audit_plans.approve';

    public function allows(User $actor, bool $fresh = true): bool
    {
        if (!$actor->exists) { return false; }
        $user = $fresh ? User::find($actor->getKey()) : $actor;
        if (!$user || !$user->isActive) { return false; }
        // Approval is opt-in. Even the existing Super Admin wildcard is insufficient.
        return in_array(self::APPROVE, Role::where('roleId', (int) $user->roleId)->first()?->permissions ?? [], true);
    }
}
