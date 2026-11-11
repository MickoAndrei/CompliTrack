<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        app(\App\Services\QmsArchiveMigration::class)->run(true);
    }
}
