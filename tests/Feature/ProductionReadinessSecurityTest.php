<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_member_cannot_cancel_order_from_support_routes(): void
    {
        $actor = $this->makeAdmin('team_member');
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-0001',
            'user_id' => $customer->id,
            'category' => 'other',
            'priority' => 'medium',
            'status' => 'open',
            'subject' => 'RBAC cancel denial',
            'description' => 'Permission check',
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.order.cancel', $ticket, absolute: false), [
                'reason' => 'Should be forbidden',
            ])
            ->assertForbidden();
    }

    public function test_team_member_cannot_manage_airports(): void
    {
        $actor = $this->makeAdmin('team_member');

        $this->actingAs($actor)
            ->get(route('admin.airports.index', absolute: false))
            ->assertForbidden();
    }

    public function test_admin_with_settings_can_open_airports(): void
    {
        $actor = $this->makeAdmin('admin');

        $this->actingAs($actor)
            ->get(route('admin.airports.index', absolute: false))
            ->assertOk();
    }

    private function makeAdmin(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $user->syncRolesByName([$role]);

        return $user;
    }
}
