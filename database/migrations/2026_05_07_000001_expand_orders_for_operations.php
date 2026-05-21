<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status', 30)->default(Order::PAYMENT_STATUS_UNPAID)->index()->after('status');
            $table->string('service_type', 30)->nullable()->index()->after('payment_status');
            $table->json('details')->nullable()->after('service_type');
            $table->text('internal_notes')->nullable()->after('error_message');
        });

        DB::table('orders')
            ->orderBy('id')
            ->select(['id', 'status', 'request_payload'])
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $payload = json_decode($order->request_payload ?? '[]', true);

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'status' => $this->normalizeStatus($order->status),
                            'payment_status' => $this->normalizePaymentStatus($order->status),
                            'service_type' => $this->inferServiceType(is_array($payload) ? $payload : []),
                            'details' => json_encode(is_array($payload) ? $payload : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('draft', 'pending_payment', 'paid', 'processing', 'confirmed', 'completed', 'cancelled', 'failed', 'refunded') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->whereIn('status', ['draft', 'pending_payment', 'paid', 'processing'])
            ->update(['status' => 'pending']);

        DB::table('orders')
            ->where('status', 'completed')
            ->update(['status' => 'confirmed']);

        DB::table('orders')
            ->where('status', 'refunded')
            ->update(['status' => 'cancelled']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending', 'confirmed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_status', 'service_type', 'details', 'internal_notes']);
        });
    }

    /**
     * Normalize a legacy order status to the new lifecycle.
     */
    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'pending' => Order::STATUS_PENDING_PAYMENT,
            'processing' => Order::STATUS_PROCESSING,
            'confirmed' => Order::STATUS_CONFIRMED,
            'cancelled' => Order::STATUS_CANCELLED,
            'failed' => Order::STATUS_FAILED,
            'refunded' => Order::STATUS_REFUNDED,
            default => Order::STATUS_DRAFT,
        };
    }

    /**
     * Normalize the payment status based on the legacy order state.
     */
    private function normalizePaymentStatus(string $status): string
    {
        return match ($status) {
            'confirmed', 'processing' => Order::PAYMENT_STATUS_PAID,
            'refunded' => Order::PAYMENT_STATUS_REFUNDED,
            default => Order::PAYMENT_STATUS_UNPAID,
        };
    }

    /**
     * Infer the service type from a legacy request payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function inferServiceType(array $payload): ?string
    {
        $tripType = strtolower((string) ($payload['trip_type'] ?? ''));

        if ($tripType === Order::SERVICE_TYPE_FLIGHT || isset($payload['pnr'], $payload['airline'])) {
            return Order::SERVICE_TYPE_FLIGHT;
        }

        if ($tripType === Order::SERVICE_TYPE_HOTEL || isset($payload['hotel_name'], $payload['check_in'], $payload['check_out'])) {
            return Order::SERVICE_TYPE_HOTEL;
        }

        if ($tripType === Order::SERVICE_TYPE_INSURANCE || isset($payload['insurance_type'], $payload['coverage_days'])) {
            return Order::SERVICE_TYPE_INSURANCE;
        }

        return null;
    }
};