<?php

namespace App\Modules\Audit\Services;

use App\Models\Order;
use App\Models\ProviderWalletTransaction;
use App\Models\SavedPassenger;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class GlobalSearchService
{
    /**
     * @return array{
     *     query: string,
     *     groups: array<string, list<array<string, mixed>>>,
     *     total: int
     * }
     */
    public function search(string $query, int $perGroup = 10): array
    {
        $query = trim($query);
        $perGroup = max(1, min($perGroup, (int) config('audit.search_limit_per_group', 10)));

        if ($query === '' || mb_strlen($query) < 2) {
            return [
                'query' => $query,
                'groups' => [],
                'total' => 0,
            ];
        }

        $groups = [
            'orders' => $this->searchOrders($query, $perGroup),
            'customers' => $this->searchCustomers($query, $perGroup),
            'support_tickets' => $this->searchSupportTickets($query, $perGroup),
            'wallet_transactions' => $this->searchWalletTransactions($query, $perGroup),
            'settlements' => $this->searchSettlements($query, $perGroup),
            'passengers' => $this->searchPassengers($query, $perGroup),
        ];

        $total = array_sum(array_map('count', $groups));

        return [
            'query' => $query,
            'groups' => array_filter($groups, fn (array $items): bool => $items !== []),
            'total' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchOrders(string $query, int $limit): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return Order::query()
            ->with('customer:id,name,full_name,email,phone')
            ->where(function ($searchQuery) use ($query): void {
                if (ctype_digit($query)) {
                    $searchQuery->orWhere('id', (int) $query);
                }

                $searchQuery
                    ->orWhere('booking_reference', 'like', $query.'%')
                    ->orWhere('external_booking_id', 'like', $query.'%')
                    ->orWhere('provider_name', 'like', $query.'%')
                    ->orWhere('details->pnr', $query)
                    ->orWhere('details->provider_order_number', $query)
                    ->orWhereHas('customer', function ($customerQuery) use ($query): void {
                        $customerQuery
                            ->where('name', 'like', '%'.$query.'%')
                            ->orWhere('full_name', 'like', '%'.$query.'%')
                            ->orWhere('email', 'like', '%'.$query.'%')
                            ->orWhere('phone', 'like', '%'.$query.'%');
                    });
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order): array {
                $details = is_array($order->details) ? $order->details : [];

                return [
                    'type' => 'order',
                    'id' => $order->id,
                    'title' => $order->booking_reference ?: 'Order #'.$order->id,
                    'subtitle' => trim(implode(' · ', array_filter([
                        $order->customer?->full_name ?: $order->customer?->name,
                        $details['pnr'] ?? null,
                        $order->status,
                    ]))),
                    'meta' => [
                        'pnr' => $details['pnr'] ?? null,
                        'ticket_number' => $details['provider_order_number'] ?? null,
                        'external_booking_id' => $order->external_booking_id,
                        'status' => $order->status,
                    ],
                    'url' => route('admin.orders.show', $order->id, absolute: false),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCustomers(string $query, int $limit): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return User::query()
            ->where(function ($searchQuery) use ($query): void {
                $searchQuery
                    ->where('name', 'like', '%'.$query.'%')
                    ->orWhere('full_name', 'like', '%'.$query.'%')
                    ->orWhere('email', 'like', '%'.$query.'%')
                    ->orWhere('phone', 'like', '%'.$query.'%');

                if (ctype_digit($query)) {
                    $searchQuery->orWhere('id', (int) $query);
                }
            })
            ->where(function ($accountQuery): void {
                $accountQuery
                    ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
                    ->orWhere('is_admin', false);
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (User $user): array => [
                'type' => 'customer',
                'id' => $user->id,
                'title' => $user->full_name ?: $user->name,
                'subtitle' => trim(implode(' · ', array_filter([$user->email, $user->phone]))),
                'meta' => [
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
                'url' => route('admin.users.show', $user->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSupportTickets(string $query, int $limit): array
    {
        if (! Schema::hasTable('support_tickets')) {
            return [];
        }

        return SupportTicket::query()
            ->where(function ($searchQuery) use ($query): void {
                if (ctype_digit($query)) {
                    $searchQuery->orWhere('id', (int) $query);
                }

                $searchQuery
                    ->orWhere('ticket_number', 'like', '%'.$query.'%')
                    ->orWhere('subject', 'like', '%'.$query.'%')
                    ->orWhereHas('user', function ($userQuery) use ($query): void {
                        $userQuery
                            ->where('name', 'like', '%'.$query.'%')
                            ->orWhere('full_name', 'like', '%'.$query.'%')
                            ->orWhere('email', 'like', '%'.$query.'%')
                            ->orWhere('phone', 'like', '%'.$query.'%');
                    });
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (SupportTicket $ticket): array => [
                'type' => 'support_ticket',
                'id' => $ticket->id,
                'title' => $ticket->ticket_number ?: 'Ticket #'.$ticket->id,
                'subtitle' => trim(implode(' · ', array_filter([$ticket->subject, $ticket->status]))),
                'meta' => [
                    'status' => $ticket->status,
                    'order_id' => $ticket->order_id,
                ],
                'url' => route('admin.support.show', $ticket->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchWalletTransactions(string $query, int $limit): array
    {
        if (! Schema::hasTable('provider_wallet_transactions')) {
            return [];
        }

        return ProviderWalletTransaction::query()
            ->with(['wallet.provider:id,name', 'order:id,booking_reference'])
            ->where(function ($searchQuery) use ($query): void {
                if (ctype_digit($query)) {
                    $searchQuery->orWhere('id', (int) $query)
                        ->orWhere('order_id', (int) $query)
                        ->orWhere('provider_wallet_id', (int) $query);
                }

                $searchQuery
                    ->orWhere('reference_id', 'like', '%'.$query.'%')
                    ->orWhere('description', 'like', '%'.$query.'%')
                    ->orWhere('reference_type', 'like', '%'.$query.'%')
                    ->orWhereHas('order', function ($orderQuery) use ($query): void {
                        $orderQuery
                            ->where('booking_reference', 'like', '%'.$query.'%')
                            ->orWhere('external_booking_id', 'like', '%'.$query.'%');
                    });
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (ProviderWalletTransaction $tx): array => [
                'type' => 'wallet_transaction',
                'id' => $tx->id,
                'title' => ucfirst((string) $tx->type).' #'.$tx->id,
                'subtitle' => trim(implode(' · ', array_filter([
                    $tx->wallet?->provider?->name,
                    ($tx->amount ?? '').' '.($tx->currency ?? ''),
                    $tx->order?->booking_reference,
                ]))),
                'meta' => [
                    'wallet_id' => $tx->provider_wallet_id,
                    'order_id' => $tx->order_id,
                ],
                'url' => route('admin.provider-wallets.show', $tx->provider_wallet_id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSettlements(string $query, int $limit): array
    {
        if (! Schema::hasTable('settlements')) {
            return [];
        }

        $settlementIds = collect();

        if (ctype_digit($query)) {
            $settlementIds->push((int) $query);
        }

        if (Schema::hasTable('settlement_items')) {
            $fromItems = SettlementItem::query()
                ->where(function ($itemQuery) use ($query): void {
                    $itemQuery
                        ->where('booking_reference', 'like', '%'.$query.'%')
                        ->orWhere('external_booking_id', 'like', '%'.$query.'%');

                    if (ctype_digit($query)) {
                        $itemQuery->orWhere('order_id', (int) $query);
                    }
                })
                ->limit($limit * 3)
                ->pluck('settlement_id');

            $settlementIds = $settlementIds->merge($fromItems);
        }

        return Settlement::query()
            ->with('provider:id,name,key')
            ->where(function ($searchQuery) use ($query, $settlementIds): void {
                if ($settlementIds->isNotEmpty()) {
                    $searchQuery->orWhereIn('id', $settlementIds->unique()->all());
                }

                $searchQuery->orWhereHas('provider', function ($providerQuery) use ($query): void {
                    $providerQuery
                        ->where('name', 'like', '%'.$query.'%')
                        ->orWhere('key', 'like', '%'.$query.'%');
                });
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Settlement $settlement): array => [
                'type' => 'settlement',
                'id' => $settlement->id,
                'title' => 'Settlement #'.$settlement->id,
                'subtitle' => trim(implode(' · ', array_filter([
                    $settlement->provider?->name,
                    $settlement->period_start?->toDateString(),
                    $settlement->period_end?->toDateString(),
                    $settlement->status,
                ]))),
                'meta' => [
                    'status' => $settlement->status,
                    'currency' => $settlement->currency,
                ],
                'url' => route('admin.settlements.show', $settlement->id, absolute: false),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchPassengers(string $query, int $limit): array
    {
        if (! Schema::hasTable('saved_passengers')) {
            return [];
        }

        $passportHash = SavedPassenger::hashPassportNumber($query);
        $phoneHash = SavedPassenger::hashPhone($query);

        return SavedPassenger::query()
            ->with('user:id,name,full_name,email')
            ->where(function ($searchQuery) use ($query, $passportHash, $phoneHash): void {
                $searchQuery
                    ->where('first_name', 'like', '%'.$query.'%')
                    ->orWhere('last_name', 'like', '%'.$query.'%')
                    ->orWhere('passport_number_hash', $passportHash);

                if ($phoneHash) {
                    $searchQuery->orWhere('phone_hash', $phoneHash);
                }
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (SavedPassenger $passenger): array => [
                'type' => 'passenger',
                'id' => $passenger->id,
                'title' => trim($passenger->first_name.' '.$passenger->last_name),
                'subtitle' => trim(implode(' · ', array_filter([
                    $passenger->user?->full_name ?: $passenger->user?->name,
                    $passenger->document_type,
                ]))),
                'meta' => [
                    'user_id' => $passenger->user_id,
                    'nationality' => $passenger->nationality,
                ],
                'url' => $passenger->user_id
                    ? route('admin.users.show', $passenger->user_id, absolute: false)
                    : null,
            ])
            ->all();
    }
}
