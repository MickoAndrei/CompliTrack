<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    // Seeds the 4 fixed roles (Guide §6): 0 Super Admin, 1 Admin, 2 Auditor, 3 Auditee
    public function run(): void
    {
        $roles = [
            ['roleId' => 0, 'roleSlug' => 'super_admin', 'roleName' => 'Super Admin', 'permissions' => ['*']],
            ['roleId' => 1, 'roleSlug' => 'admin', 'roleName' => 'Admin', 'permissions' => []],
            ['roleId' => 2, 'roleSlug' => 'auditor', 'roleName' => 'Auditor', 'permissions' => []],
            ['roleId' => 3, 'roleSlug' => 'auditee', 'roleName' => 'Auditee', 'permissions' => []],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['roleId' => $role['roleId']], $role);
        }
    }
}
