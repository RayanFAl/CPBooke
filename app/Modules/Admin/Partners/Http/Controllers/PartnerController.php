<?php

namespace App\Modules\Admin\Partners\Http\Controllers;

use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Models\PartnerWebhookDelivery;
use App\Models\PartnerWebhookEndpoint;
use App\Modules\Admin\Partners\Http\Requests\StorePartnerApiKeyRequest;
use App\Modules\Admin\Partners\Http\Requests\StorePartnerRequest;
use App\Modules\Admin\Partners\Http\Requests\StorePartnerWebhookRequest;
use App\Modules\Admin\Partners\Http\Requests\UpdatePartnerRequest;
use App\Modules\Admin\Partners\Http\Requests\UpdatePartnerWebhookRequest;
use App\Modules\Admin\Partners\Services\PartnerAdminService;
use App\Modules\Partners\Support\PartnerWebhookEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController
{
    public function __construct(
        private readonly PartnerAdminService $partnerAdminService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
        ];

        $partners = $this->partnerAdminService
            ->paginate($filters)
            ->through(fn (Partner $partner): array => $this->serializePartner($partner));

        return Inertia::render('admin/partners/pages/Index', [
            'partners' => $partners,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'can_manage' => $request->user()?->can('partners.manage') ?? false,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/partners/pages/Form', [
            'partner' => null,
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $partner = $this->partnerAdminService->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Partner created.');
    }

    public function show(Request $request, Partner $partner): Response
    {
        $partner->load([
            'apiKeys' => fn ($q) => $q->orderByDesc('id'),
            'webhookEndpoints' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $deliveries = PartnerWebhookDelivery::query()
            ->where('partner_id', $partner->id)
            ->with('endpoint:id,url')
            ->orderByDesc('id')
            ->limit(25)
            ->get()
            ->map(fn (PartnerWebhookDelivery $delivery): array => [
                'id' => $delivery->id,
                'event' => $delivery->event,
                'status' => $delivery->status,
                'attempt_count' => $delivery->attempt_count,
                'response_code' => $delivery->response_code,
                'endpoint_url' => $delivery->endpoint?->url,
                'delivered_at' => optional($delivery->delivered_at)?->toIso8601String(),
                'failed_at' => optional($delivery->failed_at)?->toIso8601String(),
                'created_at' => optional($delivery->created_at)?->toIso8601String(),
            ]);

        return Inertia::render('admin/partners/pages/Show', [
            'partner' => $this->serializePartner($partner, true),
            'api_keys' => $partner->apiKeys->map(fn (PartnerApiKey $key): array => $this->serializeApiKey($key)),
            'webhooks' => $partner->webhookEndpoints->map(fn (PartnerWebhookEndpoint $endpoint): array => $this->serializeWebhook($endpoint)),
            'deliveries' => $deliveries,
            'webhook_events' => PartnerWebhookEvents::all(),
            'can_manage' => $request->user()?->can('partners.manage') ?? false,
            'created_api_key' => $request->session()->get('created_api_key'),
            'created_webhook_secret' => $request->session()->get('created_webhook_secret'),
        ]);
    }

    public function edit(Partner $partner): Response
    {
        return Inertia::render('admin/partners/pages/Form', [
            'partner' => $this->serializePartner($partner),
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner = $this->partnerAdminService->update($partner, $request->validated());

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Partner updated.');
    }

    public function storeApiKey(StorePartnerApiKeyRequest $request, Partner $partner): RedirectResponse
    {
        $result = $this->partnerAdminService->createApiKey($partner, $request->validated('name'), $request->user());

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'API key created. Copy it now — it will not be shown again.')
            ->with('created_api_key', [
                'id' => $result['key']->id,
                'name' => $result['key']->name,
                'plain_text' => $result['plain_text'],
                'key_prefix' => $result['key']->key_prefix,
            ]);
    }

    public function revokeApiKey(Partner $partner, PartnerApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->partner_id === $partner->id, 404);

        $this->partnerAdminService->revokeApiKey($apiKey);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'API key revoked.');
    }

    public function storeWebhook(StorePartnerWebhookRequest $request, Partner $partner): RedirectResponse
    {
        $result = $this->partnerAdminService->createWebhookEndpoint($partner, $request->validated());

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Webhook endpoint created. Copy the signing secret now.')
            ->with('created_webhook_secret', [
                'endpoint_id' => $result['endpoint']->id,
                'signing_secret' => $result['signing_secret'],
            ]);
    }

    public function updateWebhook(
        UpdatePartnerWebhookRequest $request,
        Partner $partner,
        PartnerWebhookEndpoint $webhook,
    ): RedirectResponse {
        abort_unless($webhook->partner_id === $partner->id, 404);

        $this->partnerAdminService->updateWebhookEndpoint($webhook, $request->validated());

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Webhook endpoint updated.');
    }

    public function destroyWebhook(Partner $partner, PartnerWebhookEndpoint $webhook): RedirectResponse
    {
        abort_unless($webhook->partner_id === $partner->id, 404);

        $this->partnerAdminService->deleteWebhookEndpoint($webhook);

        return redirect()
            ->route('admin.partners.show', $partner)
            ->with('success', 'Webhook endpoint deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePartner(Partner $partner, bool $detailed = false): array
    {
        $payload = [
            'id' => $partner->id,
            'name' => $partner->name,
            'slug' => $partner->slug,
            'status' => $partner->status,
            'contact_email' => $partner->contact_email,
            'notes' => $partner->notes,
            'api_keys_count' => $partner->api_keys_count ?? $partner->apiKeys()->count(),
            'webhooks_count' => $partner->webhook_endpoints_count ?? $partner->webhookEndpoints()->count(),
            'updated_at' => optional($partner->updated_at)?->toIso8601String(),
        ];

        if ($detailed) {
            $payload['created_at'] = optional($partner->created_at)?->toIso8601String();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApiKey(PartnerApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'key_prefix' => $key->key_prefix,
            'last_used_at' => optional($key->last_used_at)?->toIso8601String(),
            'revoked_at' => optional($key->revoked_at)?->toIso8601String(),
            'created_at' => optional($key->created_at)?->toIso8601String(),
            'is_active' => $key->isActive(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWebhook(PartnerWebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $endpoint->events ?? [],
            'is_active' => $endpoint->is_active,
            'description' => $endpoint->description,
            'created_at' => optional($endpoint->created_at)?->toIso8601String(),
            'updated_at' => optional($endpoint->updated_at)?->toIso8601String(),
        ];
    }
}
