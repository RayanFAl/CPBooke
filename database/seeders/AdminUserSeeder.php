<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the application's primary administrator.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@booke.local')],
            [
                'name' => env('ADMIN_NAME', 'Booke Admin'),
                'full_name' => env('ADMIN_NAME', 'Booke Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'is_admin' => true,
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);
    }
}