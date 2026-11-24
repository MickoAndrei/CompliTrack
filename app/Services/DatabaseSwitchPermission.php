<?php

namespace App\Services;

use App\Models\User;

class DatabaseSwitchPermission
{
    public const ABILITY = 'database.switch';

    public function allows(mixed $user): bool
    {
        return $user instanceof User
            && (int) $user->roleId === 0
            && (bool) $user->isActive;
    }
}
