<?php
namespace App\Services;

use App\Models\{Role, User};

class CarPermissions
{
    public const VERIFY = 'cars.verify';
    public const CLOSE = 'cars.close';

    public function allows(User $actor, string $permission): bool
    {
        $fresh = User::find($actor->getKey());
        return $fresh && $fresh->isActive && (int) $fresh->roleId === 2
            && in_array($permission, Role::where('roleId', (int) $fresh->roleId)->first()?->permissions ?? [], true);
    }
}
