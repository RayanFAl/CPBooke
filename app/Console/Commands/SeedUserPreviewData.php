<?php

namespace App\Console\Commands;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedUserPreviewData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:seed-preview
                            {user : User id or email}
                            {--orders=3 : Number of demo orders to create}
                            {--tickets=2 : Number of active demo tickets to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create demo CRM data for an existing user profile';

    public function handle(): int
    {
        $user = $this->resolveUser((string) $this->argument('user'));

        if (! $user) {
            $this->error('User not found. Pass a valid user id or email.');

            return self::FAILURE;
        }

        $ordersToCreate = max(1, (int) $this->option('orders'));
        $ticketsToCreate = max(1, (int) $this->option('tickets'));
        $agent = $this->resolveAgent();

        $createdOrders = 0;
        $createdTransactions = 0;
        $createdHistories = 0;
        $createdTickets = 0;
        $createdMessages = 0;

        DB::transaction(function () use (
            $user,
            $agent,
            $ordersToCreate,
            $ticketsToCreate,
            &$createdOrders,
            &$createdTransactions,
            &$createdHistories,
            &$createdTickets,
            &$createdMessages,
        ): void {
            $orders = collect();

            for ($index = 0; $index < $ordersToCreate; $index++) {
                $createdAt = now()->subDays($ordersToCreate - $index)->setTime(9 + $index, 30);
                $amount = 180 + ($index * 95);
                $order = Order::query()->create($this->orderPayload(
                    $user,
                    $index,
                    $amount,
                    $createdAt,
                ));

                $orders->push($order);
                $createdOrders++;

                if (Schema::hasTable('financial_transactions')) {
                    $mapping = FinancialTransaction::ledgerMappingForType(FinancialTransaction::TYPE_PAYMENT);

                    FinancialTransaction::query()->create([
                        'order_id' => $order->id,
                        'type' => FinancialTransaction::TYPE_PAYMENT,
                        'amount' => $amount,
                        'currency' => 'LYD',
                        'source' => FinancialTransaction::SOURCE_ORDER_CREATION,
                        'debit_account' => $mapping['debit_account'],
                        'credit_account' => $mapping['credit_account'],
                        'reference_type' => FinancialTransaction::REFERENCE_TYPE_ORDER,
                        'reference_id' => $order->id,
                        'created_at' => $createdAt->copy()->addMinutes(8),
                        'updated_at' => $createdAt->copy()->addMinutes(8),
                    ]);
                    $createdTransactions++;
                }

                if (Schema::hasTable('order_histories')) {
                    OrderHistory::query()->create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'action' => 'created',
                        'field' => 'status',
                        'old_value' => null,
                        'new_value' => $order->status,
                        'created_at' => $createdAt->copy()->addMinutes(2),
                    ]);
                    $createdHistories++;

                    OrderHistory::query()->create([
                        'order_id' => $order->id,
                        'user_id' => $user->id,
                        'action' => 'updated',
                        'field' => 'payment_status',
                        'old_value' => Order::PAYMENT_STATUS_UNPAID,
                        'new_value' => Order::PAYMENT_STATUS_PAID,
                        'created_at' => $createdAt->copy()->addMinutes(10),
                    ]);
                    $createdHistories++;
                }
            }

            if (Schema::hasTable('support_tickets') && Schema::hasTable('support_messages')) {
                for ($index = 0; $index < $ticketsToCreate; $index++) {
                    $createdAt = now()->subDays($ticketsToCreate - $index)->setTime(14, 10 + $index);
                    $ticket = SupportTicket::factory()
                        ->state([
                            'ticket_number' => sprintf('SUP-CRM-%s-%03d', now()->format('ymd'), $user->id + $index),
                            'user_id' => $user->id,
                            'order_id' => $orders[$index % $orders->count()]?->id,
                            'category' => ['booking_change', 'payment_issue', 'document_request'][$index % 3],
                            'priority' => ['high', 'urgent', 'medium'][$index % 3],
                            'status' => ['open', 'in_progress', 'waiting_customer'][$index % 3],
                            'assigned_to' => $agent?->id,
                            'subject' => [
                                'Customer requested itinerary adjustment',
                                'Payment captured but confirmation is still pending',
                                'Traveler needs document review before issuance',
                            ][$index % 3],
                            'description' => 'Preview support ticket generated for the CRM user profile demo.',
                            'first_response_due_at' => $createdAt->copy()->addHours(6),
                            'resolution_due_at' => $createdAt->copy()->addHours(48),
                            'resolved_at' => null,
                            'closed_at' => null,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt->copy()->addHours(5),
                        ])
                        ->create();
                    $createdTickets++;

                    SupportMessage::factory()
                        ->fromUser($user)
                        ->state([
                            'support_ticket_id' => $ticket->id,
                            'message' => 'Hello, I need help with this booking. I want to understand the latest update and next step.',
                            'created_at' => $createdAt->copy()->addMinutes(3),
                            'updated_at' => $createdAt->copy()->addMinutes(3),
                        ])
                        ->create();
                    $createdMessages++;

                    if ($agent) {
                        SupportMessage::factory()
                            ->fromAgent($agent)
                            ->state([
                                'support_ticket_id' => $ticket->id,
                                'message' => 'We are reviewing the request now. I linked the relevant booking and escalated the workflow for follow-up.',
                                'created_at' => $createdAt->copy()->addMinutes(35),
                                'updated_at' => $createdAt->copy()->addMinutes(35),
                            ])
                            ->create();
                        $createdMessages++;
                    }

                    SupportMessage::factory()
                        ->fromUser($user)
                        ->state([
                            'support_ticket_id' => $ticket->id,
                            'message' => 'Thank you. Please keep me updated here as soon as there is movement from the provider.',
                            'created_at' => $createdAt->copy()->addMinutes(70),
                            'updated_at' => $createdAt->copy()->addMinutes(70),
                        ])
                        ->create();
                    $createdMessages++;
                }
            }

            $user->forceFill([
                'last_login_at' => now()->subHours(3),
            ])->save();
        });

        $this->info('Preview data created successfully.');
        $this->line('User: '.$user->full_name.' <'.$user->email.'>');
        $this->line('Orders: '.$createdOrders);
        $this->line('Transactions: '.$createdTransactions);
        $this->line('Order history entries: '.$createdHistories);
        $this->line('Support tickets: '.$createdTickets);
        $this->line('Support messages: '.$createdMessages);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(User $user, int $index, int|float $amount, $createdAt): array
    {
        $payload = [
                    'customer_id' => $user->id,
                    'provider_name' => ['SkyLine Travel', 'Blue Dunes Hotel', 'SafePath Insurance'][$index % 3],
                    'booking_reference' => sprintf('BK-%s-%03d', strtoupper(Str::padLeft(dechex($user->id), 4, '0')), $index + 1),
                    'status' => Order::STATUS_CONFIRMED,
                    'currency' => 'LYD',
                    'total_amount' => $amount,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt->copy()->addHours(3),
                ];

        if (Schema::hasColumn('orders', 'external_booking_id')) {
            $payload['external_booking_id'] = sprintf('CRM-%s-%03d', now()->format('ymd'), $user->id + $index);
        }

        if (Schema::hasColumn('orders', 'payment_status')) {
            $payload['payment_status'] = Order::PAYMENT_STATUS_PAID;
        }

        if (Schema::hasColumn('orders', 'service_type')) {
            $payload['service_type'] = [Order::SERVICE_TYPE_FLIGHT, Order::SERVICE_TYPE_HOTEL, Order::SERVICE_TYPE_INSURANCE][$index % 3];
        }

        if (Schema::hasColumn('orders', 'details')) {
            $payload['details'] = [
                'source' => 'crm_preview',
                'customer_note' => 'Preview data generated for admin user profile review.',
            ];
        }

        if (Schema::hasColumn('orders', 'internal_notes')) {
            $payload['internal_notes'] = 'Generated by users:seed-preview command.';
        }

        if (Schema::hasColumn('orders', 'request_payload')) {
            $payload['request_payload'] = [
                'preview' => true,
                'customer_email' => $user->email,
            ];
        }

        if (Schema::hasColumn('orders', 'response_payload')) {
            $payload['response_payload'] = [
                'status' => 'ok',
                'preview' => true,
            ];
        }

        return $payload;
    }

    private function resolveUser(string $identifier): ?User
    {
        return User::query()
            ->when(is_numeric($identifier), fn ($query) => $query->orWhereKey((int) $identifier))
            ->orWhere('email', $identifier)
            ->first();
    }

    private function resolveAgent(): ?User
    {
        return User::query()
            ->where('account_type', User::ACCOUNT_TYPE_ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}