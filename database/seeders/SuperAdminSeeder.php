<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CounterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower(trim((string) env('SUPER_ADMIN_EMAIL')));
        $password = (string) env('SUPER_ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            throw new RuntimeException(
                'Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env before seeding.',
            );
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        User::create([
            'userCode' => app(CounterService::class)->next('SA'),
            'fullName' => 'Super Administrator',
            'password' => Hash::make($password, ['rounds' => 12]),
            'roleId' => 0,
            'roleSlug' => 'super_admin',
            'departmentId' => null,
            'isActive' => true,
            'createdAt' => now()->utc(),
        ]);
    }
}