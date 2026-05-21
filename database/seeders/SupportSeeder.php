<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketHistory;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SupportSeeder extends Seeder
{
    /**
     * Seed realistic support data for the admin support inbox and detail pages.
     */
    public function run(): void
    {
        $this->ensureSupportTablesExist();

        $this->call(RolesAndPermissionsSeeder::class);

        SupportMessage::query()->delete();
        SupportTicketHistory::query()->delete();
        SupportTicket::query()->delete();

        $agents = $this->createAgents();
        $customers = User::factory()
            ->count(8)
            ->create([
                'account_type' => User::ACCOUNT_TYPE_CUSTOMER,
                'is_admin' => false,
            ]);
        $orders = $this->createOrders($customers);

        $tickets = [
            [
                'number' => '20260510-1001',
                'status' => 'open',
                'priority' => 'high',
                'category' => 'booking_change',
                'subject' => 'Customer needs urgent departure date correction',
                'description' => 'The traveler entered the wrong departure date during checkout and needs the booking reviewed before airline confirmation.',
                'with_order' => true,
            ],
            [
                'number' => '20260510-1002',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'category' => 'payment_issue',
                'subject' => 'Card charged but booking confirmation is missing',
                'description' => 'The customer reports that payment was captured while the booking reference never appeared in the account dashboard.',
                'with_order' => true,
            ],
            [
                'number' => '20260510-1003',
                'status' => 'waiting_customer',
                'priority' => 'medium',
                'category' => 'document_request',
                'subject' => 'Passport copy requested for airline reissue',
                'description' => 'Support requested additional traveler documentation before the provider can finalize the amended itinerary.',
                'with_order' => true,
            ],
            [
                'number' => '20260510-1004',
                'status' => 'resolved',
                'priority' => 'low',
                'category' => 'refund_request',
                'subject' => 'Refund status clarified after cancellation',
                'description' => 'The customer asked for a final update on the refund timeline after a voluntary cancellation.',
                'with_order' => true,
            ],
            [
                'number' => '20260510-1005',
                'status' => 'open',
                'priority' => 'medium',
                'category' => 'technical_issue',
                'subject' => 'Customer cannot upload required travel document',
                'description' => 'The upload form fails after selection and the customer cannot complete the support workflow from the portal.',
                'with_order' => false,
            ],
            [
                'number' => '20260510-1006',
                'status' => 'in_progress',
                'priority' => 'high',
                'category' => 'refund_request',
                'subject' => 'Partial hotel refund requires provider confirmation',
                'description' => 'The guest checked out early and support is coordinating with the provider to confirm the refundable amount.',
                'with_order' => true,
            ],
            [
                'number' => '20260510-1007',
                'status' => 'waiting_customer',
                'priority' => 'low',
                'category' => 'booking_change',
                'subject' => 'Customer asked for alternative check-in date options',
                'description' => 'Support shared replacement availability and is waiting for the customer to confirm the preferred option.',
                'with_order' => false,
            ],
            [
                'number' => '20260510-1008',
                'status' => 'resolved',
                'priority' => 'urgent',
                'category' => 'payment_issue',
                'subject' => 'Duplicate payment dispute closed after verification',
                'description' => 'Finance confirmed a single successful capture and support closed the duplicate-charge concern with the customer.',
                'with_order' => true,
            ],
        ];

        foreach ($tickets as $index => $ticketData) {
            $customer = $customers[$index % $customers->count()];
            $agent = $agents[$index % $agents->count()];
            $order = $ticketData['with_order'] ? $orders[$index % $orders->count()] : null;
            $createdAt = now()->subDays(14 - $index)->setTime(9 + ($index % 4), 15);
            $updatedAt = (clone $createdAt)->modify(sprintf('+%d hours', 4 + $index));

            $ticket = SupportTicket::factory()
                ->numbered($ticketData['number'])
                ->state([
                    'user_id' => $customer->id,
                    'order_id' => $order?->id,
                    'category' => $ticketData['category'],
                    'priority' => $ticketData['priority'],
                    'status' => $ticketData['status'],
                    'assigned_to' => in_array($ticketData['status'], ['in_progress', 'waiting_customer', 'resolved'], true) ? $agent->id : null,
                    'subject' => $ticketData['subject'],
                    'description' => $ticketData['description'],
                    'first_response_due_at' => (clone $createdAt)->modify('+6 hours'),
                    'resolution_due_at' => (clone $createdAt)->modify('+48 hours'),
                    'resolved_at' => $ticketData['status'] === 'resolved' ? (clone $updatedAt)->modify('-30 minutes') : null,
                    'closed_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ])
                ->create();

            $this->seedMessages($ticket, $customer, $agent, $ticketData['status']);
            $this->seedHistory($ticket, $customer, $agent, $ticketData['status']);
        }
    }

    /**
     * Ensure the support tables exist before seeding demo data.
     */
    private function ensureSupportTablesExist(): void
    {
        $supportMigrations = [
            'support_tickets' => 'database/migrations/2026_05_10_000001_create_support_tickets_table.php',
            'support_messages' => 'database/migrations/2026_05_10_000002_create_support_messages_table.php',
            'support_ticket_histories' => 'database/migrations/2026_05_10_000003_create_support_ticket_histories_table.php',
        ];

        foreach ($supportMigrations as $table => $path) {
            if (Schema::hasTable($table)) {
                continue;
            }

            Artisan::call('migrate', [
                '--path' => $path,
                '--force' => true,
            ]);
        }
    }

    /**
     * Create a reusable pool of support agents.
     *
     * @return Collection<int, User>
     */
    private function createAgents(): Collection
    {
        $agents = collect([
            ['name' => 'Noura Support', 'email' => 'support.noura@booke.local'],
            ['name' => 'Faisal Support', 'email' => 'support.faisal@booke.local'],
            ['name' => 'Maha Support', 'email' => 'support.maha@booke.local'],
        ])->map(function (array $agent): User {
            $user = User::query()->updateOrCreate(
                ['email' => $agent['email']],
                [
                    'name' => $agent['name'],
                    'full_name' => $agent['name'],
                    'phone' => fake()->e164PhoneNumber(),
                    'country' => fake()->country(),
                    'is_admin' => true,
                    'account_type' => User::ACCOUNT_TYPE_ADMIN,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => bcrypt('password'),
                ],
            );

            $user->syncRolesByName([RbacRegistry::ROLE_SUPPORT_AGENT]);

            return $user;
        });

        return $agents->values();
    }

    /**
     * Create realistic orders used by a subset of support tickets.
     *
     * @param  Collection<int, User>  $customers
     * @return Collection<int, Order>
     */
    private function createOrders(Collection $customers): Collection
    {
        $hasPaymentStatus = Schema::hasColumn('orders', 'payment_status');
        $hasServiceType = Schema::hasColumn('orders', 'service_type');
        $hasDetails = Schema::hasColumn('orders', 'details');
        $hasInternalNotes = Schema::hasColumn('orders', 'internal_notes');

        return collect(range(1, 5))->map(function (int $index) use (
            $customers,
            $hasPaymentStatus,
            $hasServiceType,
            $hasDetails,
            $hasInternalNotes,
        ): Order {
            $customer = $customers[($index - 1) % $customers->count()];

            $attributes = [
                'customer_id' => $customer->id,
                'provider_name' => fake()->randomElement(['Saudi Holidays', 'SkyBridge', 'Nile Travel', 'Atlas Stay']),
                'external_booking_id' => 'EXT-SUP-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'booking_reference' => 'BK-SUP-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'status' => 'confirmed',
                'currency' => 'LYD',
                'total_amount' => number_format(fake()->randomFloat(2, 120, 960), 2, '.', ''),
                'request_payload' => [
                    'seed_context' => 'support_demo',
                ],
                'response_payload' => null,
                'error_message' => null,
                'created_at' => now()->subDays(30 - $index),
                'updated_at' => now()->subDays(20 - $index),
            ];

            if ($hasPaymentStatus) {
                $attributes['payment_status'] = fake()->randomElement(['paid', 'partially_refunded']);
            }

            if ($hasServiceType) {
                $attributes['service_type'] = fake()->randomElement(['flight', 'hotel', 'insurance']);
            }

            if ($hasDetails) {
                $attributes['details'] = [
                    'seed_context' => 'support_demo',
                    'reference_note' => 'Generated for support inbox testing',
                ];
            }

            if ($hasInternalNotes) {
                $attributes['internal_notes'] = null;
            }

            return Order::query()->create($attributes);
        })->values();
    }

    /**
     * Seed 2-6 realistic messages for the ticket.
     */
    private function seedMessages(SupportTicket $ticket, User $customer, User $agent, string $status): void
    {
        $messageBodies = [
            'Customer message' => [
                'I need an update on this issue because my travel date is getting close.',
                'Please confirm whether additional documents are still required from my side.',
                'I am following up because I have not seen the booking update yet.',
            ],
            'Agent message' => [
                'We reviewed the case and have contacted the provider for a manual check.',
                'Our team needs one final confirmation before we can complete the request.',
                'The latest provider response has been added to the case and we are monitoring next steps.',
            ],
            'Internal message' => [
                'Provider escalation logged internally for follow-up on the next queue cycle.',
                'Finance confirmation received and attached to the support trail for reference.',
            ],
        ];

        $count = fake()->numberBetween(2, 6);
        $baseTime = $ticket->created_at->copy()->addMinutes(8);

        for ($index = 0; $index < $count; $index++) {
            $isCustomerTurn = $index === 0 || $index % 2 === 0;
            $isInternal = ! $isCustomerTurn && $status === 'resolved' && $index === $count - 1 && fake()->boolean(35);
            $createdAt = $baseTime->copy()->addMinutes(45 * $index);

            $factory = SupportMessage::factory()
                ->for($ticket, 'ticket')
                ->state([
                    'message' => $isCustomerTurn
                        ? fake()->randomElement($messageBodies['Customer message'])
                        : fake()->randomElement($isInternal ? $messageBodies['Internal message'] : $messageBodies['Agent message']),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

            if ($isCustomerTurn) {
                $factory->fromUser($customer)->create();

                continue;
            }

            $factory->fromAgent($agent, $isInternal)->create();
        }
    }

    /**
     * Seed the canonical support timeline entries for the ticket.
     */
    private function seedHistory(SupportTicket $ticket, User $customer, User $agent, string $status): void
    {
        $createdAt = $ticket->created_at->copy();

        SupportTicketHistory::factory()
            ->for($ticket, 'ticket')
            ->created($customer)
            ->state([
                'created_at' => $createdAt,
                'new_value' => 'open',
            ])
            ->create();

        if ($ticket->assigned_to !== null) {
            SupportTicketHistory::factory()
                ->for($ticket, 'ticket')
                ->assigned($agent, $agent->full_name ?: $agent->name)
                ->state([
                    'created_at' => $createdAt->copy()->addMinutes(18),
                ])
                ->create();
        }

        SupportTicketHistory::factory()
            ->for($ticket, 'ticket')
            ->statusChanged($agent, 'open', $status)
            ->state([
                'created_at' => $createdAt->copy()->addMinutes(32),
            ])
            ->create();

        SupportTicketHistory::factory()
            ->for($ticket, 'ticket')
            ->replied($agent)
            ->state([
                'created_at' => $createdAt->copy()->addMinutes(55),
            ])
            ->create();
    }
}