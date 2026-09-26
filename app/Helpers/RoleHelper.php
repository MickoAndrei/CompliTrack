<?php

namespace App\Helpers;

class RoleHelper
{
    const ROLE_SUPER_ADMIN = 0;
    const ROLE_ADMIN = 1;
    const ROLE_AUDITOR = 2;
    const ROLE_AUDITEE = 3;

    public static function slug(int $roleId): string
    {
        return match ($roleId) {
            self::ROLE_SUPER_ADMIN => 'super_admin',
            self::ROLE_ADMIN => 'admin',
            self::ROLE_AUDITOR => 'auditor',
            self::ROLE_AUDITEE => 'auditee',
            default => 'unknown',
        };
    }
}
