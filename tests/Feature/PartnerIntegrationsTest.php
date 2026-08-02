<?php

namespace Tests\Feature;

use App\Models\FinancialTransaction;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerWebhookDelivery;
use App\Models\PartnerWebhookEndpoint;
use App\Models\User;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\RefundIssued;
use App\Modules\Partners\Jobs\DeliverPartnerWebhookJob;
use App\Modules\Partners\Services\PartnerApiKeyService;
use App\Modules\Partners\Support\PartnerWebhookEvents;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PartnerIntegrationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_partner_api_keys_and_webhooks(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPER_ADMIN]);

        $this->actingAs($actor)
            ->post(route('admin.partners.store'), [
                'name' => 'Accounting Partner',
                'slug' => 'accounting-partner',
                'status' => Partner::STATUS_ACTIVE,
                'contact_email' => 'ops@partner.test',
            ])
            ->assertRedirect();

        $partner = Partner::query()->where('slug', 'accounting-partner')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('admin.partners.api-keys.store', $partner), [
                'name' => 'Production',
            ])
            ->assertRedirect(route('admin.partners.show', $partner))
            ->assertSessionHas('created_api_key');

        $this->assertSame(1, $partner->apiKeys()->count());

        $this->actingAs($actor)
            ->post(route('admin.partners.webhooks.store', $partner), [
                'url' => 'https://partner.test/hooks/cpbooke',
                'events' => [PartnerWebhookEvents::ORDER_CREATED, PartnerWebhookEvents::REFUND_ISSUED],
                'description' => 'Main hook',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.partners.show', $partner))
            ->assertSessionHas('created_webhook_secret');

        $this->assertSame(1, $partner->webhookEndpoints()->count());
    }

    public function test_partner_api_key_authenticates_me_and_order_show(): void
    {
        $partner = Partner::query()->create([
            'name' => 'Partner A',
            'slug' => 'partner-a',
            'status' => Partner::STATUS_ACTIVE,
        ]);

        $created = app(PartnerApiKeyService::class)->create($partner, 'CI');
        $plain = $created['plain_text'];

        $order = Order::query()->create([
            'customer_id' => User::factory()->create()->id,
            'provider_name' => 'Test Provider',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 100,
            'final_amount' => 100,
            'booking_reference' => 'BN-100',
            'source' => 'booknow',
            'request_payload' => ['source' => 'test'],
        ]);

        $this->getJson('/api/v1/partner/me', [
            'Authorization' => 'Bearer '.$plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.partner.slug', 'partner-a')
            ->assertJsonPath('data.api_key.name', 'CI');

        $this->getJson('/api/v1/partner/orders/'.$order->id, [
            'X-Partner-Key' => $plain,
        ])
            ->assertOk()
            ->assertJsonPath('data.order.booking_reference', 'BN-100');

        $this->getJson('/api/v1/partner/me')
            ->assertUnauthorized();
    }

    public function test_order_and_refund_events_enqueue_partner_webhooks(): void
    {
        Queue::fake();

        $partner = Partner::query()->create([
            'name' => 'Partner Hooks',
            'slug' => 'partner-hooks',
            'status' => Partner::STATUS_ACTIVE,
        ]);

        $endpoint = PartnerWebhookEndpoint::query()->create([
            'partner_id' => $partner->id,
            'url' => 'https://hooks.example/cpbooke',
            'signing_secret' => 'whsec_test_secret',
            'events' => [
                PartnerWebhookEvents::ORDER_CREATED,
                PartnerWebhookEvents::REFUND_ISSUED,
            ],
            'is_active' => true,
        ]);

        $order = Order::query()->create([
            'customer_id' => User::factory()->create()->id,
            'provider_name' => 'Test Provider',
            'status' => Order::STATUS_CONFIRMED,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'service_type' => Order::SERVICE_TYPE_FLIGHT,
            'currency' => 'LYD',
            'total_amount' => 250,
            'final_amount' => 250,
            'booking_reference' => 'BN-200',
            'source' => 'booknow',
            'request_payload' => ['source' => 'test'],
        ]);

        event(new OrderCreated($order));

        $this->assertDatabaseHas('partner_webhook_deliveries', [
            'partner_id' => $partner->id,
            'partner_webhook_endpoint_id' => $endpoint->id,
            'event' => PartnerWebhookEvents::ORDER_CREATED,
            'status' => PartnerWebhookDelivery::STATUS_PENDING,
        ]);

        Queue::assertPushed(DeliverPartnerWebhookJob::class);

        $transaction = FinancialTransaction::query()->create([
            'order_id' => $order->id,
            'type' => FinancialTransaction::TYPE_REFUND,
            'status' => FinancialTransaction::STATUS_EXECUTED,
            'amount' => 50,
            'currency' => 'LYD',
            'source' => FinancialTransaction::SOURCE_PAYMENT_STATUS_PARTIALLY_REFUNDED,
        ]);

        event(new RefundIssued($order, $transaction));

        $this->assertSame(
            2,
            PartnerWebhookDelivery::query()->where('partner_webhook_endpoint_id', $endpoint->id)->count()
        );
    }

    public function test_deliver_partner_webhook_job_posts_signed_payload(): void
    {
        Http::fake([
            'https://hooks.example/*' => Http::response(['ok' => true], 200),
        ]);

        $partner = Partner::query()->create([
            'name' => 'Signer',
            'slug' => 'signer',
            'status' => Partner::STATUS_ACTIVE,
        ]);

        $endpoint = PartnerWebhookEndpoint::query()->create([
            'partner_id' => $partner->id,
            'url' => 'https://hooks.example/cpbooke',
            'signing_secret' => 'whsec_delivery_secret',
            'events' => [PartnerWebhookEvents::ORDER_CREATED],
            'is_active' => true,
        ]);

        $delivery = PartnerWebhookDelivery::query()->create([
            'partner_id' => $partner->id,
            'partner_webhook_endpoint_id' => $endpoint->id,
            'event' => PartnerWebhookEvents::ORDER_CREATED,
            'status' => PartnerWebhookDelivery::STATUS_PENDING,
            'payload' => [
                'type' => PartnerWebhookEvents::ORDER_CREATED,
                'data' => ['order' => ['id' => 1]],
            ],
        ]);

        (new DeliverPartnerWebhookJob($delivery->id))->handle(app(\App\Modules\Partners\Services\PartnerWebhookSigner::class));

        $delivery->refresh();
        $this->assertSame(PartnerWebhookDelivery::STATUS_SENT, $delivery->status);
        $this->assertSame(200, $delivery->response_code);

        Http::assertSent(function ($request) use ($endpoint) {
            $timestamp = $request->header('X-CPBooke-Timestamp')[0] ?? null;
            $signature = $request->header('X-CPBooke-Signature')[0] ?? null;
            $body = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_delivery_secret');

            return $request->url() === $endpoint->url
                && ($request->header('X-CPBooke-Event')[0] ?? null) === PartnerWebhookEvents::ORDER_CREATED
                && $signature === $expected;
        });
    }

    public function test_partners_index_requires_permission(): void
    {
        $actor = User::factory()->create([
            'account_type' => User::ACCOUNT_TYPE_ADMIN,
            'is_admin' => true,
        ]);
        $actor->syncRolesByName([RbacRegistry::ROLE_SUPPORT_AGENT]);

        $this->actingAs($actor)
            ->get(route('admin.partners.index'))
            ->assertForbidden();
    }
}
