<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Modules\Admin\Support\Services\SupportAgentScoringService;
use App\Modules\Admin\Support\Services\SupportSLARiskService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_scoring_prefers_skill_matched_agent_before_load_when_scores_are_comparable(): void
    {
        Carbon::setTestNow('2026-05-10 11:00:00');

        [$firstAgent, $secondAgent, $customer] = $this->supportAgentsAndCustomer();

        config()->set('support.auto_assignment.agent_skills.by_id', [
            $firstAgent->id => ['tech'],
            $secondAgent->id => ['finance'],
        ]);

        SupportTicket::query()->create([
            'ticket_number' => 'SUP-SCORE-0001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $firstAgent->id,
            'subject' => 'First agent current load',
            'description' => 'Comparable load baseline.',
            'first_response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addHours(48),
            'first_response_at' => now()->subDay()->addMinutes(60),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHour(),
        ]);

        SupportTicket::query()->create([
            'ticket_number' => 'SUP-SCORE-0002',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $secondAgent->id,
            'subject' => 'Second agent current load',
            'description' => 'Comparable load baseline.',
            'first_response_due_at' => now()->addHours(8),
            'resolution_due_at' => now()->addHours(48),
            'first_response_at' => now()->subDay()->addMinutes(70),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHour(),
        ]);

        $ticket = new SupportTicket([
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $bestAgent = app(SupportAgentScoringService::class)->getBestAgent($ticket);

        $this->assertNotNull($bestAgent);
        $this->assertSame($secondAgent->id, $bestAgent->id);

        Carbon::setTestNow();
    }

    public function test_support_sla_risk_service_marks_overdue_urgent_ticket_as_high_risk(): void
    {
        Carbon::setTestNow('2026-05-10 14:00:00');

        [, $agent, $customer] = $this->supportAgentsAndCustomer();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-RISK-0001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'urgent',
            'status' => 'open',
            'assigned_to' => $agent->id,
            'subject' => 'Critical outage',
            'description' => 'Customer cannot access the system.',
            'first_response_due_at' => now()->subHour(),
            'resolution_due_at' => now()->addHours(3),
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(2),
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'System still not working.',
            'is_internal' => false,
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ]);

        $this->assertSame('high', app(SupportSLARiskService::class)->riskLevelFor($ticket->load('messages.user')));

        Carbon::setTestNow();
    }

    public function test_support_service_can_reassign_high_risk_ticket_when_feature_flag_is_enabled(): void
    {
        Carbon::setTestNow('2026-05-10 15:00:00');

        [$overloadedAgent, $reliefAgent, $customer] = $this->supportAgentsAndCustomer();

        config()->set('support.smart_reassignment.enabled', true);
        config()->set('support.auto_assignment.agent_skills.by_id', [
            $overloadedAgent->id => ['finance'],
            $reliefAgent->id => ['finance'],
        ]);

        foreach (range(1, 8) as $index) {
            SupportTicket::query()->create([
                'ticket_number' => 'SUP-REBALANCE-A-'.$index,
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'payment_issue',
                'priority' => 'urgent',
                'status' => 'open',
                'assigned_to' => $overloadedAgent->id,
                'subject' => 'Overload '.$index,
                'description' => 'Pressure on overloaded agent.',
                'first_response_due_at' => now()->addHour(),
                'resolution_due_at' => now()->addHours(8),
                'first_response_at' => now()->subDay()->addHours(6),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHour(),
            ]);
        }

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REBALANCE-0001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'urgent',
            'status' => 'open',
            'assigned_to' => $overloadedAgent->id,
            'subject' => 'Needs reassignment',
            'description' => 'High-risk finance escalation.',
            'first_response_due_at' => now()->subMinutes(90),
            'resolution_due_at' => now()->addHour(),
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(2),
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'I still need help with the payment issue.',
            'is_internal' => false,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        app('auth')->login($overloadedAgent);

        app(\App\Modules\Admin\Support\Services\SupportService::class)
            ->updateTicketStatus($ticket, 'in_progress', $overloadedAgent->id);

        $ticket->refresh();

        $this->assertSame($reliefAgent->id, $ticket->assigned_to);

        Carbon::setTestNow();
    }

    /**
     * Create two support agents and one customer.
     *
     * @return array{0: User, 1: User, 2: User}
     */
    private function supportAgentsAndCustomer(): array
    {
        $firstAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $secondAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $firstAgent->refresh()->syncRolesByName(['support_agent']);
        $secondAgent->refresh()->syncRolesByName(['support_agent']);

        return [$firstAgent, $secondAgent, $customer];
    }
}