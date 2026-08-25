<?php

namespace App\Modules\Admin\Orders\Services;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Provider;
use App\Models\User;
use App\Modules\Api\Orders\Services\OrderService;
use App\Modules\Audit\Services\AuditRecorder;
use App\Modules\CustomerWallets\Services\CustomerWalletService;
use App\Modules\Notifications\Services\TravelMarketingService;
use App\Modules\Orders\Events\OrderConfirmed as OrderConfirmedEvent;
use App\Modules\Orders\Events\OrderCreated as OrderCreatedEvent;
use App\Support\Platform\PlatformSettings;
use Illuminate\Support\Facades\DB;

class ManualBookingService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly CustomerWalletService $customerWalletService,
        private readonly TravelMarketingService $travelMarketingService,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * Record a booking that staff already made outside the app.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(User $actor, array $data): Order
    {
        $customer = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->findOrFail((int) $data['customer_id']);

        $amount = (float) $data['total_amount'];
        $currency = strtoupper((string) $data['currency']);
        $paid = ($data['payment_status'] ?? '') === Order::PAYMENT_STATUS_PAID;
        $paymentMethod = $paid
            ? (string) ($data['payment_method'] ?: Order::PAYMENT_METHOD_CASH)
            : null;
        $provider = Provider::query()->where('key', Provider::KEY_BOOKNOW)->first();
        $details = $this->detailsFromInput($data);

        $order = DB::transaction(function () use ($actor, $customer, $data, $amount, $currency, $paid, $paymentMethod, $provider, $details): Order {
            if ($paid && $paymentMethod === Order::PAYMENT_METHOD_WALLET) {
                $wallet = $this->customerWalletService->resolveWallet($customer, $currency, false);
                $this->customerWalletService->adminDebit($wallet, $amount, $actor, [
                    'description' => 'Manual booking '.$data['booking_reference'],
                    'metadata' => ['source' => 'admin_manual_booking'],
                ]);
            }

            $order = Order::query()->create([
                'customer_id' => $customer->id,
                'provider_id' => $provider?->id,
                'provider_name' => $data['provider_name'] ?: ($provider?->name ?: 'BookNow'),
                'source' => Order::SOURCE_ADMIN_MANUAL,
                'external_booking_id' => $data['booking_reference'],
                'booking_reference' => $data['booking_reference'],
                'status' => $paid ? Order::STATUS_CONFIRMED : Order::STATUS_PENDING_PAYMENT,
                'payment_status' => $paid ? Order::PAYMENT_STATUS_PAID : Order::PAYMENT_STATUS_UNPAID,
                'service_type' => $data['service_type'],
                'details' => $details,
                'currency' => $currency,
                'total_amount' => number_format($amount, 2, '.', ''),
                'selling_price' => number_format($amount, 2, '.', ''),
                'final_amount' => number_format($amount, 2, '.', ''),
                'internal_notes' => $data['internal_notes'] ?: null,
                'payment_method' => $paymentMethod,
                'request_payload' => [
                    'source' => Order::SOURCE_ADMIN_MANUAL,
                    'recorded_by' => $actor->id,
                    ...$details,
                ],
            ]);

            if ($paid) {
                $this->orderService->recordFinancialTransaction(
                    $order,
                    FinancialTransaction::TYPE_PAYMENT,
                    $amount,
                    FinancialTransaction::SOURCE_PAYMENT_STATUS_PAID,
                    $actor,
                    [
                        'source_id' => $order->id,
                        'reason' => 'Manual booking recorded by '.$actor->full_name,
                    ],
                );
            }

            $this->orderService->recordOperationalHistory(
                $order,
                $actor,
                'admin_manual_booking_recorded',
                'status',
                null,
                $order->status,
            );

            return $order->refresh()->load('customer');
        });

        event(new OrderCreatedEvent($order));

        if ($paid) {
            event(new OrderConfirmedEvent($order));
        }

        $this->travelMarketingService->markConvertedForCustomer($customer);

        $this->auditRecorder->record(
            module: 'orders',
            action: 'order.manual.created',
            subject: 'Recorded a manual booking from the Control Panel.',
            entityType: 'order',
            entityId: $order->id,
            actor: $actor,
            newValues: [
                'customer_id' => $customer->id,
                'booking_reference' => $order->booking_reference,
                'service_type' => $order->service_type,
                'total_amount' => $order->total_amount,
            ],
        );

        return $order;
    }

    /**
     * @return array<int, array{id: int, name: string, email: string|null, phone: string|null}>
     */
    public function customerOptions(?int $selectedId = null): array
    {
        $query = User::query()
            ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
            ->select(['id', 'name', 'full_name', 'email', 'phone'])
            ->orderByDesc('id')
            ->limit(80);

        $customers = $query->get();

        if ($selectedId && ! $customers->contains('id', $selectedId)) {
            $selected = User::query()
                ->where('account_type', User::ACCOUNT_TYPE_CUSTOMER)
                ->select(['id', 'name', 'full_name', 'email', 'phone'])
                ->find($selectedId);

            if ($selected) {
                $customers->prepend($selected);
            }
        }

        return $customers
            ->unique('id')
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name ?: $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->values()
            ->all();
    }

    public function defaultCurrency(): string
    {
        return PlatformSettings::defaultCurrency();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailsFromInput(array $data): array
    {
        $details = [
            'passenger_name' => $data['passenger_name'],
            'pnr' => $data['booking_reference'],
            'source' => Order::SOURCE_ADMIN_MANUAL,
        ];

        if (($data['service_type'] ?? '') === Order::SERVICE_TYPE_FLIGHT) {
            $details['origin'] = strtoupper((string) ($data['origin'] ?? ''));
            $details['destination'] = strtoupper((string) ($data['destination'] ?? ''));
            $details['departure_airport'] = $details['origin'];
            $details['arrival_airport'] = $details['destination'];
            $details['departure_date'] = $data['departure_date'] ?? null;
            $details['return_date'] = $data['return_date'] ?? null;
        }

        if (($data['service_type'] ?? '') === Order::SERVICE_TYPE_HOTEL) {
            $details['hotel_name'] = $data['hotel_name'] ?? null;
            $details['check_in'] = $data['check_in'] ?? null;
            $details['check_out'] = $data['check_out'] ?? null;
        }

        if (($data['service_type'] ?? '') === Order::SERVICE_TYPE_INSURANCE) {
            $details['insurance_type'] = $data['insurance_type'] ?? null;
            $details['beneficiary_name'] = $data['passenger_name'];
        }

        return $details;
    }
}
