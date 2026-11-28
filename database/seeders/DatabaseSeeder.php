<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (config('database.default') !== 'mongodb') {
            throw new RuntimeException(
                'DatabaseSeeder requires DB_CONNECTION=mongodb so data is written to the MongoDB database.',
            );
        }

        // Fail before seeding if the configured MongoDB server/database is unavailable.
        DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);

        if (config('v2.enabled') || DB::connection('mongodb')->getMongoDB()->getDatabaseName() === 'complitrack_v2') {
            $this->call(\Database\Seeders\V2\V2DatabaseSeeder::class);
            return;
        }

        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            SuperAdminSeeder::class,
            DemoDataSeeder::class,
            ArchiveDemoSeeder::class,
        ]);
    }
}
