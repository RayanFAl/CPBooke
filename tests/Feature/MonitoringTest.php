<?php

namespace Tests\Feature;

use App\Jobs\CheckProviderWalletsJob;
use App\Jobs\ExpirePendingApprovalsJob;
use App\Jobs\RunSystemHealthProbesJob;
use App\Models\Approval;
use App\Models\Provider;
use App\Models\ProviderWallet;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Modules\Monitoring\Services\MonitoringDashboardService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_view_monitoring_dashboard(): void
    {
        $actor = $this->makeAdmin('operations_manager');

        $this->actingAs($actor)
            ->get(route('admin.monitoring.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/monitoring/pages/Index', false)
                ->has('dashboard.services')
                ->has('dashboard.signals')
                ->has('dashboard.alerts'));
    }

    public function test_health_probes_persist_system_health_checks(): void
    {
        RunSystemHealthProbesJob::dispatchSync();

        $this->assertGreaterThanOrEqual(8, SystemHealthCheck::query()->count());
        $this->assertDatabaseHas('system_health_checks', [
            'check_key' => 'database',
            'status' => SystemHealthCheck::STATUS_OK,
        ]);
    }

    public function test_monitoring_dashboard_service_includes_required_signal_keys(): void
    {
        $dashboard = app(MonitoringDashboardService::class)->dashboard();

        foreach ([
            'queue_jobs',
            'failed_jobs',
            'exceptions_1h',
            'slow_requests_1h',
            'wallet_alerts',
            'settlement_alerts',
            'email_failures_24h',
            'whatsapp_failures_24h',
        ] as $key) {
            $this->assertArrayHasKey($key, $dashboard['signals']);
        }

        $keys = collect($dashboard['services'])->pluck('key')->all();
        $this->assertContains('application', $keys);
        $this->assertContains('database', $keys);
        $this->assertContains('queue', $keys);
        $this->assertContains('booknow', $keys);
        $this->assertContains('payment', $keys);
    }

    public function test_expire_pending_approvals_job_rejects_stale_requests(): void
    {
        $requester = $this->makeAdmin('support_agent');

        $approval = Approval::query()->create([
            'type' => Approval::TYPE_REFUND,
            'entity_type' => Approval::ENTITY_ORDER,
            'entity_id' => 1,
            'status' => Approval::STATUS_PENDING,
            'requested_by' => $requester->id,
            'reason' => 'Stale refund',
            'payload' => ['amount' => '200.00'],
        ]);

        $approval->forceFill([
            'created_at' => now()->subHours(100),
            'updated_at' => now()->subHours(100),
        ])->saveQuietly();

        ExpirePendingApprovalsJob::dispatchSync();

        $this->assertSame(Approval::STATUS_REJECTED, $approval->refresh()->status);
    }

    public function test_wallet_check_job_records_alert_for_low_balance(): void
    {
        $provider = Provider::query()->create([
            'name' => 'BookNow',
            'key' => 'booknow',
            'status' => Provider::STATUS_ACTIVE,
        ]);

        ProviderWallet::query()->create([
            'provider_id' => $provider->id,
            'currency' => 'LYD',
            'environment' => 'production',
            'balance' => '50.00',
            'low_balance_threshold' => '1000.00',
            'allow_negative' => true,
            'is_active' => true,
        ]);

        CheckProviderWalletsJob::dispatchSync();

        $this->assertDatabaseHas('application_events', [
            'source' => 'wallet_check',
            'severity' => 'critical',
        ]);
    }

    public function test_run_probes_endpoint_dispatches_job(): void
    {
        Queue::fake();
        $actor = $this->makeAdmin('admin');

        $this->actingAs($actor)
            ->post(route('admin.monitoring.run-probes', absolute: false))
            ->assertRedirect(route('admin.monitoring.index', absolute: false));

        Queue::assertPushed(RunSystemHealthProbesJob::class);
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
