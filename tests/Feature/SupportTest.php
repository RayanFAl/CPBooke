<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketEventLog;
use App\Models\SupportTicketResolutionReport;
use App\Models\User;
use App\Modules\Support\Services\SupportService;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_agent_can_create_a_ticket_with_first_message(): void
    {
        Carbon::setTestNow('2026-05-10 09:00:00');

        [$actor, $customer] = $this->supportActorAndCustomer();

        $order = $this->createOrderForCustomer($customer);

        $this->actingAs($actor)
            ->post(route('admin.support.store', absolute: false), [
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'category' => 'payment_issue',
                'priority' => 'high',
                'assigned_agent_id' => $actor->id,
                'subject' => 'Charge captured without confirmation',
                'first_message' => 'Customer reported that checkout completed but the itinerary never appeared.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()->firstOrFail();

        $this->assertSame('open', $ticket->status);
        $this->assertSame($customer->id, $ticket->user_id);
        $this->assertSame($order->id, $ticket->order_id);
        $this->assertSame($actor->id, $ticket->assigned_to);
        $this->assertSame('2026-05-10 13:00:00', $ticket->first_response_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-11 09:00:00', $ticket->resolution_due_at?->format('Y-m-d H:i:s'));
        $this->assertNull($ticket->getAttribute('first_response_at'));
        $this->assertNull($ticket->resolved_at);

        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Customer reported that checkout completed but the itinerary never appeared.',
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'action' => 'created',
            'field' => 'status',
            'new_value' => 'open',
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'action' => 'assigned',
            'field' => 'assigned_agent_id',
        ]);

        $this->assertSame(1, SupportTicketEventLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event_type', 'SupportTicketCreated')
            ->count());
        $this->assertSame(1, SupportTicketEventLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event_type', 'SupportTicketAssigned')
            ->count());

        Carbon::setTestNow();
    }

    public function test_support_agent_can_reply_assign_and_change_status_from_show_flow(): void
    {
        Carbon::setTestNow('2026-05-10 10:00:00');

        [$actor, $customer] = $this->supportActorAndCustomer();
        $secondAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $secondAgent->syncRolesByName(['support_agent']);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-1001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => null,
            'subject' => 'Portal upload issue',
            'description' => 'Customer cannot upload the requested file.',
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'I cannot upload my file from the portal.',
            'is_internal' => false,
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.reply', $ticket, absolute: false), [
                'message' => 'We are reviewing the upload issue and will update you shortly.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $this->actingAs($actor)
            ->put(route('admin.support.update-status', $ticket, absolute: false), [
                'status' => 'in_progress',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $this->actingAs($actor)
            ->put(route('admin.support.assign', $ticket, absolute: false), [
                'assigned_agent_id' => $secondAgent->id,
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $ticket->refresh();

        $this->assertSame('in_progress', $ticket->status);
        $this->assertSame($secondAgent->id, $ticket->assigned_to);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'first_response_at' => '2026-05-10 10:00:00',
        ]);

        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => 'We are reviewing the upload issue and will update you shortly.',
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'action' => 'replied',
            'field' => 'message',
            'new_value' => 'agent_reply',
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'action' => 'status_changed',
            'field' => 'status',
            'old_value' => 'open',
            'new_value' => 'in_progress',
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'action' => 'assigned',
            'field' => 'assigned_agent_id',
        ]);

        $this->assertSame(1, SupportTicketEventLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event_type', 'SupportTicketReplied')
            ->count());
        $this->assertSame(1, SupportTicketEventLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event_type', 'SupportTicketAssigned')
            ->count());

        $this->actingAs($actor)
            ->put(route('admin.support.update-status', $ticket, absolute: false), [
                'status' => 'resolved',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $ticket->refresh();

        $this->assertSame('resolved', $ticket->status);
        $this->assertSame('2026-05-10 10:00:00', $ticket->resolved_at?->format('Y-m-d H:i:s'));
        $this->assertSame(2, SupportTicketEventLog::query()
            ->where('ticket_id', $ticket->id)
            ->where('event_type', 'SupportTicketStatusChanged')
            ->count());

        Carbon::setTestNow();
    }

    public function test_support_agent_cancel_on_issued_order_creates_pending_approval(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $operationsManager = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $operationsManager->syncRolesByName(['operations_manager']);
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-CANCEL-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'Cancel linked booking',
            'description' => 'Customer asked support to cancel the booking.',
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.order.cancel', $ticket, absolute: false), [
                'reason' => 'Customer requested cancellation from support.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $approval = \App\Models\Approval::query()->firstOrFail();

        $this->assertSame(\App\Models\Approval::TYPE_CANCEL, $approval->type);
        $this->assertSame(\App\Models\Approval::STATUS_PENDING, $approval->status);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CONFIRMED,
        ]);

        $this->actingAs($operationsManager)
            ->post(route('admin.approvals.approve', $approval, absolute: false))
            ->assertRedirect(route('admin.approvals.index', ['status' => 'all'], absolute: false));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'action' => 'status_changed',
            'field' => 'status',
            'old_value' => Order::STATUS_CONFIRMED,
            'new_value' => Order::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'order_cancelled',
            'field' => 'order_status',
            'old_value' => Order::STATUS_CONFIRMED,
            'new_value' => Order::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'is_internal' => true,
            'message' => 'Order BK-SUPPORT-1 was cancelled manually. Reason: Customer requested cancellation from support.',
        ]);
    }

    public function test_ticket_cannot_be_closed_without_resolution_report(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REPORT-1001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'Close requires report',
            'description' => 'Attempting to close without a report should fail.',
        ]);

        $this->actingAs($actor)
            ->from(route('admin.support.show', $ticket, absolute: false))
            ->put(route('admin.support.update-status', $ticket, absolute: false), [
                'status' => 'closed',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false))
            ->assertSessionHasErrors('status');

        $ticket->refresh();

        $this->assertSame('in_progress', $ticket->status);
        $this->assertNull($ticket->closed_at);
        $this->assertDatabaseCount('support_ticket_resolution_reports', 0);
    }

    public function test_resolution_report_is_linked_to_ticket_and_calculates_handling_minutes(): void
    {
        Carbon::setTestNow('2026-05-10 10:30:00');

        try {
            [$actor, $customer] = $this->supportActorAndCustomer();

            $ticket = SupportTicket::query()->create([
                'ticket_number' => 'SUP-REPORT-1002',
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'payment_issue',
                'priority' => 'high',
                'status' => 'in_progress',
                'assigned_to' => $actor->id,
                'subject' => 'Resolution timing',
                'description' => 'Ticket should produce a measured handling duration.',
            ]);

            $ticket->forceFill([
                'created_at' => Carbon::parse('2026-05-10 09:00:00'),
                'updated_at' => Carbon::parse('2026-05-10 09:00:00'),
            ])->save();

            $this->actingAs($actor)
                ->post(route('admin.support.resolution-report.upsert', $ticket, absolute: false), [
                    'resolution_type' => 'resolved',
                    'root_cause' => 'Missing provider confirmation callback.',
                    'actions_taken' => 'Verified the provider state and manually re-synced the confirmation.',
                    'resolution_summary' => 'Booking confirmation was re-synced and delivered to the customer.',
                    'internal_notes' => 'Provider webhook timeout isolated to one booking.',
                    'customer_visible_notes' => 'Your booking is now confirmed and visible in your account.',
                    'status_after' => 'resolved',
                    'escalated' => false,
                    'satisfaction_requested' => true,
                ])
                ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

            $ticket->refresh();
            $report = SupportTicketResolutionReport::query()->where('ticket_id', $ticket->id)->firstOrFail();

            $this->assertSame($ticket->id, $report->ticket_id);
            $this->assertSame($actor->id, $report->agent_id);
            $this->assertSame('resolved', $report->status_after);
            $this->assertSame(90, $report->handling_minutes);
            $this->assertSame('resolved', $ticket->status);
            $this->assertSame('2026-05-10 10:30:00', $report->resolved_at?->format('Y-m-d H:i:s'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_support_agent_can_view_reports_dashboard(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $ticket = $this->createTicketWithResolutionReport($actor, $customer, 'closed');

        $this->actingAs($actor)
            ->get(route('admin.support.reports.index', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Reports', false)
                ->where('dashboard.available', true)
                ->where('dashboard.summary.total_reports', 1)
                ->has('dashboard.recent_reports', 1)
                ->where('dashboard.recent_reports.0.ticket_id', $ticket->id));
    }

    public function test_support_agent_can_export_resolution_reports_csv(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $this->createTicketWithResolutionReport($actor, $customer, 'closed');

        $response = $this->actingAs($actor)
            ->get(route('admin.support.reports.export.csv', absolute: false));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Ticket Number', $content);
        $this->assertStringContainsString('SUP-REPORT-EXPORT', $content);
        $this->assertStringContainsString('Provider webhook timeout isolated to one booking.', $content);
    }

    public function test_support_agent_can_print_resolution_report(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $ticket = $this->createTicketWithResolutionReport($actor, $customer, 'closed');

        $this->actingAs($actor)
            ->get(route('admin.support.resolution-report.print', $ticket, absolute: false))
            ->assertOk()
            ->assertSee('Support Resolution Report')
            ->assertSee('SUP-REPORT-EXPORT')
            ->assertSee('Provider webhook timeout isolated to one booking.');
    }

    public function test_print_resolution_report_returns_not_found_without_report(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REPORT-404',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $actor->id,
            'subject' => 'No report yet',
            'description' => 'Ticket without resolution report.',
        ]);

        $this->actingAs($actor)
            ->get(route('admin.support.resolution-report.print', $ticket, absolute: false))
            ->assertNotFound();
    }

    public function test_resolution_report_is_added_to_ticket_timeline(): void
    {
        Carbon::setTestNow('2026-05-10 12:00:00');

        try {
            [$actor, $customer] = $this->supportActorAndCustomer();

            $ticket = SupportTicket::query()->create([
                'ticket_number' => 'SUP-REPORT-1003',
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'document_request',
                'priority' => 'medium',
                'status' => 'in_progress',
                'assigned_to' => $actor->id,
                'subject' => 'Timeline resolution event',
                'description' => 'Timeline should include the structured resolution event.',
            ]);

            $ticket->forceFill([
                'created_at' => Carbon::parse('2026-05-10 11:15:00'),
                'updated_at' => Carbon::parse('2026-05-10 11:15:00'),
            ])->save();

            $this->actingAs($actor)
                ->post(route('admin.support.resolution-report.upsert', $ticket, absolute: false), [
                    'resolution_type' => 'partially_resolved',
                    'root_cause' => 'Customer documents were incomplete.',
                    'actions_taken' => 'Requested corrected documents and completed the internal checklist.',
                    'resolution_summary' => 'The ticket was resolved with a pending customer document follow-up.',
                    'internal_notes' => 'Pending final document upload.',
                    'customer_visible_notes' => 'We processed your request and only need the final document upload.',
                    'status_after' => 'resolved',
                ])
                ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

            $response = $this->actingAs($actor)
                ->get(route('admin.support.show', $ticket, absolute: false))
                ->assertOk();

            $timeline = $response->viewData('page')['props']['ticket']['timeline'];

            $resolutionEntry = collect($timeline)->first(fn (array $entry): bool => str_contains($entry['event'], 'Ticket resolved by'));

            $this->assertNotNull($resolutionEntry);
            $this->assertStringContainsString('Resolution type: partially resolved.', $resolutionEntry['description']);
            $this->assertStringContainsString('Handling time: 45 minutes.', $resolutionEntry['description']);
            $this->assertStringContainsString('The ticket was resolved with a pending customer document follow-up.', $resolutionEntry['description']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reopen_flow_does_not_break_existing_resolution_report(): void
    {
        Carbon::setTestNow('2026-05-10 09:30:00');

        try {
            [$actor, $customer] = $this->supportActorAndCustomer();

            $ticket = SupportTicket::query()->create([
                'ticket_number' => 'SUP-REPORT-1004',
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'technical_issue',
                'priority' => 'high',
                'status' => 'in_progress',
                'assigned_to' => $actor->id,
                'subject' => 'Reopen after resolution',
                'description' => 'The report should survive reopen and update cycles.',
            ]);

            $ticket->forceFill([
                'created_at' => Carbon::parse('2026-05-10 08:00:00'),
                'updated_at' => Carbon::parse('2026-05-10 08:00:00'),
            ])->save();

            $this->actingAs($actor)
                ->post(route('admin.support.resolution-report.upsert', $ticket, absolute: false), [
                    'resolution_type' => 'resolved',
                    'root_cause' => 'Portal cache issue.',
                    'actions_taken' => 'Cache purged and portal refreshed.',
                    'resolution_summary' => 'The customer portal was restored.',
                    'internal_notes' => 'Monitor if issue returns after deployment.',
                    'customer_visible_notes' => 'The portal is working again.',
                    'status_after' => 'resolved',
                ])
                ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

            $initialReport = SupportTicketResolutionReport::query()->where('ticket_id', $ticket->id)->firstOrFail();

            app(SupportService::class)->addCustomerMessage(
                $ticket->fresh(),
                'The issue came back after another login attempt.',
                $customer->id,
            );

            $ticket->refresh();
            $this->assertSame('open', $ticket->status);
            $this->assertDatabaseHas('support_ticket_resolution_reports', [
                'id' => $initialReport->id,
                'ticket_id' => $ticket->id,
            ]);

            Carbon::setTestNow('2026-05-10 10:15:00');

            $this->actingAs($actor)
                ->post(route('admin.support.resolution-report.upsert', $ticket, absolute: false), [
                    'resolution_type' => 'resolved',
                    'root_cause' => 'Portal cache issue after deployment.',
                    'actions_taken' => 'Applied the permanent cache invalidation fix.',
                    'resolution_summary' => 'The permanent fix was applied and verified with the customer.',
                    'internal_notes' => 'Escalate only if another login cycle reproduces it.',
                    'customer_visible_notes' => 'We applied a permanent fix and confirmed the portal is working.',
                    'status_after' => 'closed',
                ])
                ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

            $updatedReport = SupportTicketResolutionReport::query()->where('ticket_id', $ticket->id)->firstOrFail();

            $this->assertSame($initialReport->id, $updatedReport->id);
            $this->assertSame(1, $updatedReport->reopened_count);
            $this->assertSame('closed', $updatedReport->status_after);
            $this->assertSame('closed', $ticket->fresh()->status);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_support_agent_can_add_compensation_and_record_audit_trail(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-COMP-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'Compensate customer',
            'description' => 'Customer should receive a service recovery credit.',
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.order.compensation', $ticket, absolute: false), [
                'amount' => '25.00',
                'reason' => 'Service recovery after provider delay.',
                'compensation_type' => 'future_discount',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $transaction = FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', FinancialTransaction::TYPE_COMPENSATION)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('25.00', $transaction->amount);
        $this->assertSame(FinancialTransaction::SOURCE_SUPPORT_TICKET, $transaction->source);
        $this->assertSame($ticket->id, $transaction->source_id);
        $this->assertSame('Service recovery after provider delay.', $transaction->reason);
        $this->assertSame('future_discount', $transaction->metadata['compensation_type'] ?? null);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'compensation_added',
            'field' => 'compensation_amount',
            'new_value' => '25.00',
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'action' => 'support_compensation_added',
            'field' => 'compensation_amount',
            'new_value' => '25.00',
        ]);
    }

    public function test_finance_manager_can_reverse_refund_from_support_and_record_audit_trail(): void
    {
        [$actor, $customer] = $this->financeActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '220.00',
            'currency' => 'USD',
            'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
            'performed_by_id' => $actor->id,
            'source' => FinancialTransaction::SOURCE_SUPPORT_TICKET,
            'source_id' => 999,
            'reason' => 'Original refund execution.',
            'metadata' => ['mode' => 'full'],
        ]);

        $order->forceFill([
            'payment_status' => Order::PAYMENT_STATUS_REFUNDED,
        ])->save();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-REV-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'urgent',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'Reverse refund on linked booking',
            'description' => 'Finance approved reversing the refund.',
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.order.reverse-refund', $ticket, absolute: false), [
                'reason' => 'Refund was approved in error.',
                'internal_note' => 'Finance verified provider settlement and restored the paid state.',
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $order->refresh();

        $this->assertSame(Order::PAYMENT_STATUS_PAID, $order->payment_status);

        $reversal = FinancialTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', FinancialTransaction::TYPE_REVERSAL)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('220.00', $reversal->amount);
        $this->assertSame($ticket->id, $reversal->source_id);
        $this->assertSame('Refund was approved in error.', $reversal->reason);
        $this->assertSame('executed', $reversal->metadata['workflow_status'] ?? null);

        $this->assertDatabaseHas('support_ticket_histories', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'action' => 'refund_reversed',
            'field' => 'payment_status',
            'old_value' => Order::PAYMENT_STATUS_REFUNDED,
            'new_value' => Order::PAYMENT_STATUS_PAID,
        ]);

        $this->assertDatabaseHas('support_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'is_internal' => true,
            'message' => 'Refund reversal of 220.00 USD was executed. Reason: Refund was approved in error.. Internal note: Finance verified provider settlement and restored the paid state.',
        ]);

        $this->assertDatabaseHas('order_histories', [
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'action' => 'support_refund_reversed',
            'field' => 'payment_status',
            'old_value' => Order::PAYMENT_STATUS_REFUNDED,
            'new_value' => Order::PAYMENT_STATUS_PAID,
        ]);
    }

    public function test_support_show_exposes_permission_matrix_for_support_finance_and_team_member_roles(): void
    {
        [$supportActor, $customer] = $this->supportActorAndCustomer();
        $financeActor = $this->financeActor();
        $teamMember = $this->teamMemberActor();
        $order = $this->createOrderForCustomer($customer);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '50.00',
            'currency' => 'USD',
            'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
            'performed_by_id' => $supportActor->id,
            'source' => FinancialTransaction::SOURCE_SUPPORT_TICKET,
            'source_id' => 1,
            'reason' => 'Partial refund for matrix setup.',
            'metadata' => ['mode' => 'partial'],
        ]);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-MATRIX-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $supportActor->id,
            'subject' => 'Permissions matrix',
            'description' => 'Validate role-based support order actions.',
        ]);

        $supportResponse = $this->actingAs($supportActor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk();

        $supportProps = $supportResponse->viewData('page')['props'];

        $this->assertTrue($supportProps['order_actions']['can_manage']);
        $this->assertSame(
            ['cancel', 'full_refund', 'partial_refund', 'reverse_refund', 'compensation'],
            array_column($supportProps['order_actions']['available'], 'name'),
            'Support agents retain reverse refund access temporarily through the legacy orders.change-status fallback.',
        );

        $financeResponse = $this->actingAs($financeActor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk();

        $financeProps = $financeResponse->viewData('page')['props'];

        $this->assertTrue($financeProps['order_actions']['can_manage']);
        $this->assertSame(['reverse_refund'], array_column($financeProps['order_actions']['available'], 'name'));

        $teamMemberResponse = $this->actingAs($teamMember)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk();

        $teamMemberProps = $teamMemberResponse->viewData('page')['props'];

        $this->assertFalse($teamMemberProps['order_actions']['can_manage']);
        $this->assertSame([], $teamMemberProps['order_actions']['available']);
    }

    public function test_reverse_refund_is_hidden_and_forbidden_without_refunded_balance(): void
    {
        [$financeActor, $customer] = $this->financeActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-REV-EDGE-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $financeActor->id,
            'subject' => 'Reverse refund edge case',
            'description' => 'No refunded balance exists.',
        ]);

        $this->actingAs($financeActor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('order_actions.can_manage', true)
                ->has('order_actions.available', 0)
            );

        $this->actingAs($financeActor)
            ->post(route('admin.support.order.reverse-refund', $ticket, absolute: false), [
                'reason' => 'Should fail.',
                'internal_note' => 'No refunded balance to reverse.',
            ])
            ->assertForbidden();
    }

    public function test_compensation_and_partial_refund_are_reflected_together_in_order_snapshot(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-SNAPSHOT-1',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'Snapshot accuracy',
            'description' => 'Compensation and refund should coexist in the snapshot.',
        ]);

        $this->actingAs($actor)
            ->post(route('admin.support.order.compensation', $ticket, absolute: false), [
                'amount' => '25.00',
                'reason' => 'Service recovery credit.',
                'compensation_type' => 'wallet_credit',
            ])
            ->assertRedirect();

        $this->actingAs($actor)
            ->post(route('admin.support.order.partial-refund', $ticket, absolute: false), [
                'amount' => '50.00',
                'reason' => 'Partial refund after itinerary change.',
            ])
            ->assertRedirect();

        $this->actingAs($actor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ticket.order_snapshot.paid_amount', '170.00')
                ->where('ticket.order_snapshot.refunded_amount', '50.00')
                ->where('ticket.order_snapshot.compensation_amount', '25.00')
                ->where('ticket.order_snapshot.remaining_collectible', '25.00')
                ->where('ticket.order_snapshot.payment_status', Order::PAYMENT_STATUS_PARTIALLY_REFUNDED)
            );
    }

    public function test_support_timeline_is_sorted_in_descending_created_at_order(): void
    {
        Carbon::setTestNow('2026-05-10 08:00:00');

        try {
            [$actor, $customer] = $this->supportActorAndCustomer();
            $order = $this->createOrderForCustomer($customer);

            $ticket = SupportTicket::query()->create([
                'ticket_number' => 'SUP-TEST-TIMELINE-1',
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'category' => 'payment_issue',
                'priority' => 'high',
                'status' => 'in_progress',
                'assigned_to' => $actor->id,
                'subject' => 'Timeline ordering',
                'description' => 'Timeline should remain newest-first across sources.',
                'created_at' => Carbon::parse('2026-05-10 09:00:00'),
                'updated_at' => Carbon::parse('2026-05-10 09:00:00'),
            ]);

            $transaction = FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_COMPENSATION,
                'status' => FinancialTransaction::STATUS_EXECUTED,
                'amount' => '10.00',
                'currency' => 'USD',
                'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
                'performed_by_id' => $actor->id,
                'source' => FinancialTransaction::SOURCE_SUPPORT_TICKET,
                'source_id' => $ticket->id,
                'reason' => 'Newest event',
                'metadata' => ['compensation_type' => 'manual_adjustment'],
            ]);

            $transaction->forceFill([
                'created_at' => Carbon::parse('2026-05-10 13:00:00'),
                'updated_at' => Carbon::parse('2026-05-10 13:00:00'),
            ])->save();

            $message = SupportMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'message' => 'Customer notified.',
                'is_internal' => false,
            ]);

            $message->forceFill([
                'created_at' => Carbon::parse('2026-05-10 11:30:00'),
                'updated_at' => Carbon::parse('2026-05-10 11:30:00'),
            ])->save();

            $response = $this->actingAs($actor)
                ->get(route('admin.support.show', $ticket, absolute: false))
                ->assertOk();

            $timeline = $response->viewData('page')['props']['ticket']['timeline'];
            $timelineDates = array_column($timeline, 'created_at');
            $sortedTimelineDates = $timelineDates;
            rsort($sortedTimelineDates);

            $this->assertSame('Compensation Added', $timeline[0]['event']);
            $this->assertSame('2026-05-10 13:00:00', $timeline[0]['created_at']);
            $this->assertSame($sortedTimelineDates, $timelineDates);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_support_show_handles_missing_order_snapshot_safely(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-NULL-1',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $actor->id,
            'subject' => 'Null snapshot safety',
            'description' => 'This ticket is intentionally not linked to any order.',
        ]);

        $this->actingAs($actor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('ticket.order', null)
                ->where('ticket.order_snapshot', null)
                ->where('order_actions.can_manage', false)
                ->has('order_actions.available', 0)
            );
    }

    public function test_support_show_query_count_does_not_grow_with_related_rows(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();

        $baselineTicket = $this->buildSupportShowFixture($customer, $actor, 'SUP-TEST-N1-A', 1);
        $heavyTicket = $this->buildSupportShowFixture($customer, $actor, 'SUP-TEST-N1-B', 6);

        $baselineQueries = $this->supportShowQueryCount($actor, $baselineTicket);
        $heavyQueries = $this->supportShowQueryCount($actor, $heavyTicket);

        $this->assertLessThanOrEqual(
            $baselineQueries + 2,
            $heavyQueries,
            sprintf('Expected support show query count to remain stable, baseline=%d heavy=%d', $baselineQueries, $heavyQueries),
        );
    }

    public function test_support_agent_can_reply_with_attachment(): void
    {
        Storage::fake('local');

        [$actor, $customer] = $this->supportActorAndCustomer();

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-ATTACH-1',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $actor->id,
            'subject' => 'Need a signed copy',
            'description' => 'Customer is waiting for the file.',
        ]);

        $file = UploadedFile::fake()->create('support-note.pdf', 64, 'application/pdf');

        $this->actingAs($actor)
            ->post(route('admin.support.reply', $ticket, absolute: false), [
                'message' => '',
                'attachment' => $file,
            ])
            ->assertRedirect(route('admin.support.show', $ticket, absolute: false));

        $message = SupportMessage::query()->latest('id')->firstOrFail();

        $this->assertSame($actor->id, $message->user_id);
        $this->assertSame('', $message->message);
        $this->assertSame('support-note.pdf', $message->attachment_name);
        $this->assertSame('application/pdf', $message->attachment_mime);

        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertStringStartsWith('support/attachments/', $message->attachment_path);
        $this->assertNotSame('support-note.pdf', basename((string) $message->attachment_path));
    }

    public function test_support_auto_assignment_uses_weighted_scoring_and_falls_back_when_all_agents_are_overloaded(): void
    {
        Carbon::setTestNow('2026-05-10 11:00:00');

        [$actor, $customer] = $this->supportActorAndCustomer();
        $preferredAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $preferredAgent->syncRolesByName(['support_agent']);

        foreach (range(1, 12) as $index) {
            SupportTicket::query()->create([
                'ticket_number' => 'SUP-AUTO-A-'.$index,
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'technical_issue',
                'priority' => 'urgent',
                'status' => 'open',
                'assigned_to' => $actor->id,
                'subject' => 'Overloaded urgent '.$index,
                'description' => 'Heavy urgent load for primary actor.',
                'first_response_due_at' => now()->addHour(),
                'resolution_due_at' => now()->addHours(8),
                'first_response_at' => now()->subMinutes(1)->copy()->setTime(15, 0),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHour(),
            ]);
        }

        foreach (range(1, 13) as $index) {
            SupportTicket::query()->create([
                'ticket_number' => 'SUP-AUTO-B-'.$index,
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'document_request',
                'priority' => 'low',
                'status' => 'open',
                'assigned_to' => $preferredAgent->id,
                'subject' => 'Lower weight load '.$index,
                'description' => 'Lower priority load for preferred agent.',
                'first_response_due_at' => now()->addHours(12),
                'resolution_due_at' => now()->addHours(48),
                'first_response_at' => now()->subMinutes(1)->copy()->setTime(10, 0),
                'created_at' => now()->subDay(),
                'updated_at' => now()->subHour(),
            ]);
        }

        $this->actingAs($actor)
            ->post(route('admin.support.store', absolute: false), [
                'user_id' => $customer->id,
                'order_id' => null,
                'category' => 'payment_issue',
                'priority' => 'high',
                'subject' => 'Auto assignment request',
                'first_message' => 'Assign this ticket automatically.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::query()
            ->where('subject', 'Auto assignment request')
            ->firstOrFail();

        $this->assertSame($preferredAgent->id, $ticket->assigned_to);

        Carbon::setTestNow();
    }

    public function test_support_pages_expose_create_and_show_workflow_payloads(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-TEST-2001',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'booking_change',
            'priority' => 'urgent',
            'status' => 'waiting_customer',
            'assigned_to' => $actor->id,
            'subject' => 'Need revised departure date',
            'description' => 'Customer is waiting for revised travel options.',
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => 'We sent the available alternatives for confirmation.',
            'is_internal' => false,
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'message' => 'Please proceed with the earliest option.',
            'is_internal' => false,
        ]);

        $this->actingAs($actor)
            ->get(route('admin.support.create', absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Create', false)
                ->has('customers')
                ->has('orders')
                ->has('agents')
                ->has('categories', 5)
                ->has('priorities', 4)
            );

        $this->actingAs($actor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Show', false)
                ->where('ticket.assigned_agent_id', $actor->id)
                ->where('ticket.status', 'waiting_customer')
                ->where('ticket.has_unread_for_admin', true)
                ->where('ticket.has_unread_for_customer', false)
                ->where('ticket.last_sender_type', 'user')
                ->where('ticket.last_message', 'Please proceed with the earliest option.')
                ->where('ticket.conversation_state', 'waiting_for_support')
                ->has('status_options', 5)
                ->has('agents')
                ->where('ticket.messages.0.sender_type', 'agent')
                ->where('ticket.messages.1.sender_type', 'user')
                ->where('ticket.order_snapshot.paid_amount', '220.00')
                ->where('ticket.order_snapshot.refunded_amount', '0.00')
                ->where('ticket.order_snapshot.payment_method', 'Card')
                ->has('ticket.order_ticket')
                ->where('ticket.order_ticket.service_type', Order::SERVICE_TYPE_FLIGHT)
                ->has('ticket.timeline')
                ->where('order_actions.can_manage', true)
                ->has('order_actions.available', 4)
                ->where('order_actions.available.0.name', 'cancel')
                ->where('order_actions.available.1.name', 'full_refund')
                ->where('order_actions.available.2.name', 'partial_refund')
                ->where('order_actions.available.3.name', 'compensation')
            );
    }

    public function test_support_show_exposes_customer_notification_delivery_logs(): void
    {
        [$actor, $customer] = $this->supportActorAndCustomer();
        $order = $this->createOrderForCustomer($customer);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-NOTIF-3001',
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $actor->id,
            'subject' => 'Did not receive confirmation email',
            'description' => 'Customer says no email arrived.',
        ]);

        NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => 'email',
            'template_code' => 'ORDER_CONFIRMED',
            'notification_type' => 'order',
            'subject' => 'Your booking is confirmed',
            'body' => 'Booking confirmation body.',
            'status' => NotificationLog::STATUS_SENT,
            'related_type' => 'order',
            'related_id' => $order->id,
            'sent_at' => now()->subHour(),
        ]);

        NotificationLog::query()->create([
            'user_id' => $customer->id,
            'channel' => 'sms',
            'template_code' => 'SUPPORT_REPLY',
            'notification_type' => 'support',
            'subject' => 'Support reply',
            'body' => 'We replied to your ticket.',
            'status' => NotificationLog::STATUS_FAILED,
            'related_type' => 'support_ticket',
            'related_id' => $ticket->id,
            'response_payload' => ['error' => 'Carrier rejected the message'],
            'failed_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($actor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Show', false)
                ->where('notification_logs_enabled', true)
                ->has('customer_notification_logs', 2)
                ->where('customer_notification_logs.0.channel', 'sms')
                ->where('customer_notification_logs.0.status', NotificationLog::STATUS_FAILED)
                ->where('customer_notification_logs.0.is_ticket_related', true)
                ->where('customer_notification_logs.0.failure_reason', 'Carrier rejected the message')
                ->where('customer_notification_logs.1.channel', 'email')
                ->where('customer_notification_logs.1.is_order_related', true)
            );
    }

    public function test_support_index_exposes_filters_search_sort_and_counters(): void
    {
        Carbon::setTestNow('2026-05-10 12:00:00');

        [$actor, $customer] = $this->supportActorAndCustomer();

        $assignedAgent = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
            'name' => 'Assigned Agent',
            'full_name' => 'Assigned Agent',
            'email' => 'assigned.agent@example.test',
        ]);
        $assignedAgent->syncRolesByName(['support_agent']);

        $otherCustomer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
            'name' => 'Other Customer',
            'full_name' => 'Other Customer',
            'email' => 'other.customer@example.test',
        ]);

        $openTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-INDEX-0001',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'open',
            'assigned_to' => $assignedAgent->id,
            'subject' => 'Search target ticket',
            'description' => 'Searching by customer name should find this ticket.',
            'first_response_due_at' => now()->addHours(5),
            'resolution_due_at' => now()->addHours(30),
            'first_response_at' => now()->subMinutes(20),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subHour(),
        ]);

        $inProgressTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-INDEX-0002',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'urgent',
            'status' => 'in_progress',
            'assigned_to' => $assignedAgent->id,
            'subject' => 'Urgent assigned ticket',
            'description' => 'This ticket is used to assert priority sorting and counters.',
            'first_response_due_at' => now()->subMinutes(10),
            'resolution_due_at' => now()->addHours(2),
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subMinutes(15),
        ]);

        $waitingTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-INDEX-0003',
            'user_id' => $otherCustomer->id,
            'order_id' => null,
            'category' => 'technical_issue',
            'priority' => 'medium',
            'status' => 'waiting_customer',
            'assigned_to' => null,
            'subject' => 'Waiting on customer reply',
            'description' => 'This ticket should contribute to waiting customer counters only.',
            'first_response_due_at' => now()->subHours(3),
            'resolution_due_at' => now()->addHours(12),
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subHours(6),
        ]);

        $resolvedTicket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-INDEX-0004',
            'user_id' => $otherCustomer->id,
            'order_id' => null,
            'category' => 'document_request',
            'priority' => 'low',
            'status' => 'resolved',
            'assigned_to' => null,
            'subject' => 'Resolved document request',
            'description' => 'This ticket should contribute to resolved counters only.',
            'first_response_due_at' => now()->subHours(8),
            'resolution_due_at' => now()->subHour(),
            'first_response_at' => now()->subHours(7),
            'resolved_at' => now()->subMinutes(30),
            'created_at' => now()->subHours(12),
            'updated_at' => now()->subHours(3),
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $openTicket->id,
            'user_id' => $customer->id,
            'message' => 'I still need help with this payment issue.',
            'is_internal' => false,
            'created_at' => now()->subMinutes(50),
            'updated_at' => now()->subMinutes(50),
        ]);

        SupportMessage::query()->create([
            'support_ticket_id' => $inProgressTicket->id,
            'user_id' => $assignedAgent->id,
            'message' => 'We are checking this urgent case now.',
            'is_internal' => false,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($actor)
            ->get(route('admin.support.index', [
                'status' => 'open',
                'priority' => 'high',
                'category' => 'payment_issue',
                'assigned_agent_id' => $assignedAgent->id,
                'search' => $customer->email,
                'sort' => 'updated_at',
            ], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Index', false)
                ->where('filters.status', 'open')
                ->where('filters.priority', 'high')
                ->where('filters.category', 'payment_issue')
                ->where('filters.assigned_agent_id', $assignedAgent->id)
                ->where('filters.search', $customer->email)
                ->where('filters.sort', 'updated_at')
                ->where('counters.open', 1)
                ->where('counters.in_progress', 1)
                ->where('counters.waiting_customer', 1)
                ->where('counters.resolved', 1)
                ->has('status_options', 4)
                ->has('priority_options', 4)
                ->has('category_options', 5)
                ->has('sort_options', 4)
                ->has('agents')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $openTicket->id)
                ->where('tickets.data.0.sla_status', 'on_track')
                ->where('tickets.data.0.assignee.id', $assignedAgent->id)
                ->where('tickets.data.0.last_message', 'I still need help with this payment issue.')
                ->where('tickets.data.0.last_sender_type', 'user')
                ->where('tickets.data.0.has_unread_for_admin', true)
                ->where('tickets.data.0.has_unread_for_customer', false)
                ->where('tickets.data.0.conversation_state', 'waiting_for_support')
            );

        $this->actingAs($actor)
            ->get(route('admin.support.index', [
                'search' => (string) $openTicket->id,
                'sort' => 'latest',
            ], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Index', false)
                ->has('tickets.data', 1)
                ->where('tickets.data.0.id', $openTicket->id)
            );

        $this->actingAs($actor)
            ->get(route('admin.support.index', [
                'search' => $customer->name,
                'sort' => 'priority',
            ], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/support/pages/Index', false)
                ->has('tickets.data', 2)
                ->where('tickets.data.0.id', $inProgressTicket->id)
                ->where('tickets.data.0.sla_status', 'overdue')
                ->where('tickets.data.0.last_message', 'We are checking this urgent case now.')
                ->where('tickets.data.0.last_sender_type', 'agent')
                ->where('tickets.data.0.has_unread_for_admin', false)
                ->where('tickets.data.0.has_unread_for_customer', true)
                ->where('tickets.data.0.conversation_state', 'waiting_for_customer')
                ->where('tickets.data.1.id', $openTicket->id)
            );

        $this->assertNotNull($waitingTicket);
        $this->assertNotNull($resolvedTicket);

        Carbon::setTestNow();
    }

    /**
     * Create a support-capable actor and a customer.
     *
     * @return array{0: User, 1: User}
     */
    private function supportActorAndCustomer(): array
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['support_agent']);

        return [$actor, $customer];
    }

    /**
     * Create a finance-capable actor and a customer.
     *
     * @return array{0: User, 1: User}
     */
    private function financeActorAndCustomer(): array
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $customer = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
            'is_admin' => false,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['finance_manager']);

        return [$actor, $customer];
    }

    private function financeActor(): User
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['finance_manager']);

        return $actor;
    }

    private function teamMemberActor(): User
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $actor->refresh()->syncRolesByName(['team_member']);

        return $actor;
    }

    private function supportShowQueryCount(User $actor, SupportTicket $ticket): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($actor)
            ->get(route('admin.support.show', $ticket, absolute: false))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $queryCount;
    }

    private function buildSupportShowFixture(User $customer, User $actor, string $ticketNumber, int $extraRows): SupportTicket
    {
        $order = $this->createOrderForCustomer($customer, bookingReference: 'BK-'.$ticketNumber);

        $ticket = SupportTicket::query()->create([
            'ticket_number' => $ticketNumber,
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => 'in_progress',
            'assigned_to' => $actor->id,
            'subject' => 'N+1 regression fixture '.$ticketNumber,
            'description' => 'Support show should load relations eagerly.',
        ]);

        foreach (range(1, $extraRows) as $index) {
            SupportMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $index % 2 === 0 ? $actor->id : $customer->id,
                'message' => 'Fixture message '.$index,
                'is_internal' => false,
            ]);

            FinancialTransaction::query()->create([
                'order_id' => $order->id,
                'type' => FinancialTransaction::TYPE_COMPENSATION,
                'status' => FinancialTransaction::STATUS_EXECUTED,
                'amount' => number_format(5 * $index, 2, '.', ''),
                'currency' => 'USD',
                'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
                'performed_by_id' => $actor->id,
                'source' => FinancialTransaction::SOURCE_SUPPORT_TICKET,
                'source_id' => $ticket->id,
                'reason' => 'Fixture transaction '.$index,
                'metadata' => ['compensation_type' => 'manual_adjustment'],
            ]);
        }

        return $ticket;
    }

    /**
     * Create an order linked to the supplied customer.
     */
    private function createOrderForCustomer(User $customer, string $bookingReference = 'BK-SUPPORT-1'): Order
    {
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'provider_name' => 'Support Test Provider',
            'external_booking_id' => 'EXT-'.$bookingReference,
            'booking_reference' => $bookingReference,
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'details' => ['route' => 'RUH-JED', 'payment_method' => 'card'],
            'currency' => 'USD',
            'total_amount' => '220.00',
            'internal_notes' => null,
            'request_payload' => ['route' => 'RUH-JED', 'payment_method' => 'card'],
            'response_payload' => null,
            'error_message' => null,
        ]);

        FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_PAYMENT,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => '220.00',
            'currency' => 'USD',
            'performed_by_type' => FinancialTransaction::PERFORMED_BY_TYPE_USER,
            'performed_by_id' => $customer->id,
            'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
            'source_id' => $order->id,
            'reason' => 'Initial order payment.',
            'metadata' => ['workflow_status' => 'executed'],
        ]);

        return $order->refresh();
    }

    private function createTicketWithResolutionReport(User $actor, User $customer, string $statusAfter = 'resolved'): SupportTicket
    {
        $ticket = SupportTicket::query()->create([
            'ticket_number' => 'SUP-REPORT-EXPORT',
            'user_id' => $customer->id,
            'order_id' => null,
            'category' => 'payment_issue',
            'priority' => 'high',
            'status' => $statusAfter === 'closed' ? 'closed' : 'resolved',
            'assigned_to' => $actor->id,
            'subject' => 'Exportable resolution report',
            'description' => 'Ticket prepared for support reporting exports.',
            'resolved_at' => now(),
            'closed_at' => $statusAfter === 'closed' ? now() : null,
        ]);

        SupportTicketResolutionReport::query()->create([
            'ticket_id' => $ticket->id,
            'agent_id' => $actor->id,
            'resolution_type' => 'resolved',
            'root_cause' => 'Provider webhook timeout isolated to one booking.',
            'actions_taken' => 'Verified the provider state and manually re-synced the confirmation.',
            'resolution_summary' => 'Booking confirmation was re-synced and delivered to the customer.',
            'internal_notes' => 'Internal audit note for export coverage.',
            'customer_visible_notes' => 'Your booking is now confirmed and visible in your account.',
            'status_before' => 'in_progress',
            'status_after' => $statusAfter,
            'handling_minutes' => 45,
            'escalated' => false,
            'reopened_count' => 0,
            'satisfaction_requested' => true,
            'metadata' => ['source' => 'support_resolution_report'],
            'resolved_at' => now(),
        ]);

        return $ticket->refresh();
    }
}
