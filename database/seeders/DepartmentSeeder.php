<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::firstOrCreate(
            ['departmentCode' => 'CET-IT'],
            [
                'departmentName' => 'Information Technology',
                'collegeName' => 'CET',
                'campusName' => 'Claveria',
                'headUserId' => null,
                'isActive' => true,
                'createdAt' => now()->utc(),
            ],
        );
    }
}