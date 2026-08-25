<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the single super admin account for the Control Panel.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@booke.local');
        $name = (string) env('ADMIN_NAME', 'Booke Admin');
        $password = (string) env('ADMIN_PASSWORD', 'ChangeMe123!');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'full_name' => $name,
                'password' => Hash::make($password),
                'is_admin' => true,
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->forceFill([
            'is_admin' => true,
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_active' => true,
        ])->save();

        $user->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->demoteOtherSuperAdmins($user);
    }

    /**
     * Keep super admin exclusive to the primary seeded account.
     */
    private function demoteOtherSuperAdmins(User $superAdmin): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_user')) {
            return;
        }

        User::query()
            ->whereKeyNot($superAdmin->id)
            ->whereHas('roles', function ($query): void {
                $query->where('name', RbacRegistry::ROLE_SUPER_ADMIN);
            })
            ->get()
            ->each(function (User $user): void {
                $user->syncRolesByName([RbacRegistry::ROLE_ADMIN]);
            });
    }
}
