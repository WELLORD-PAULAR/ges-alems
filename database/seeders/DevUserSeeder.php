<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DevUserSeeder extends Seeder
{
    /**
     * Seed development-only users.
     */
    public function run(): void
    {
        if (! app()->environment([
            'local',
            'testing',
        ])) {
            return;
        }

        $adminPassword = env('DEV_ADMIN_PASSWORD');
        $teacherPassword = env('DEV_TEACHER_PASSWORD');

        if (! $adminPassword || ! $teacherPassword) {
            throw new RuntimeException(
                'DEV_ADMIN_PASSWORD and DEV_TEACHER_PASSWORD '
                .'must be set in .env.'
            );
        }

        User::updateOrCreate(
            [
                'email' => 'admin@gems-ames.test',
            ],
            [
                'name' => 'Development Admin',
                'password' => Hash::make($adminPassword),
                'role' => Role::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'teacher@gems-ames.test',
            ],
            [
                'name' => 'Development Teacher',
                'password' => Hash::make($teacherPassword),
                'role' => Role::Teacher,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
