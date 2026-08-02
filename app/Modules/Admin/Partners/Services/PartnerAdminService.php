<?php

namespace App\Modules\Admin\Partners\Services;

use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Models\PartnerWebhookEndpoint;
use App\Models\User;
use App\Modules\Partners\Services\PartnerApiKeyService;
use App\Modules\Partners\Support\PartnerWebhookEvents;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerAdminService
{
    public function __construct(
        private readonly PartnerApiKeyService $apiKeyService,
    ) {
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Partner::query()
            ->withCount(['apiKeys', 'webhookEndpoints'])
            ->orderByDesc('id');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $actor = null): Partner
    {
        $slug = $this->uniqueSlug($data['slug'] ?? $data['name']);

        return Partner::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'status' => $data['status'] ?? Partner::STATUS_ACTIVE,
            'contact_email' => $data['contact_email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Partner $partner, array $data): Partner
    {
        if (isset($data['slug']) && $data['slug'] !== $partner->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $partner->id);
        }

        $partner->fill([
            'name' => $data['name'] ?? $partner->name,
            'slug' => $data['slug'] ?? $partner->slug,
            'status' => $data['status'] ?? $partner->status,
            'contact_email' => array_key_exists('contact_email', $data) ? $data['contact_email'] : $partner->contact_email,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $partner->notes,
        ])->save();

        return $partner->refresh();
    }

    /**
     * @return array{key: PartnerApiKey, plain_text: string}
     */
    public function createApiKey(Partner $partner, string $name, ?User $actor = null): array
    {
        return $this->apiKeyService->create($partner, $name, $actor);
    }

    public function revokeApiKey(PartnerApiKey $key): PartnerApiKey
    {
        return $this->apiKeyService->revoke($key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{endpoint: PartnerWebhookEndpoint, signing_secret: string}
     */
    public function createWebhookEndpoint(Partner $partner, array $data): array
    {
        $events = $this->normalizeEvents($data['events'] ?? []);
        $secret = 'whsec_'.Str::lower(Str::random(40));

        $endpoint = $partner->webhookEndpoints()->create([
            'url' => $data['url'],
            'signing_secret' => $secret,
            'events' => $events,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'description' => $data['description'] ?? null,
        ]);

        return [
            'endpoint' => $endpoint,
            'signing_secret' => $secret,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateWebhookEndpoint(PartnerWebhookEndpoint $endpoint, array $data): PartnerWebhookEndpoint
    {
        if (isset($data['events'])) {
            $data['events'] = $this->normalizeEvents($data['events']);
        }

        $endpoint->fill([
            'url' => $data['url'] ?? $endpoint->url,
            'events' => $data['events'] ?? $endpoint->events,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $endpoint->is_active,
            'description' => array_key_exists('description', $data) ? $data['description'] : $endpoint->description,
        ])->save();

        return $endpoint->refresh();
    }

    public function deleteWebhookEndpoint(PartnerWebhookEndpoint $endpoint): void
    {
        $endpoint->delete();
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        if ($base === '') {
            $base = 'partner';
        }

        $slug = $base;
        $i = 2;

        while (
            Partner::query()
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $events
     * @return array<int, string>
     */
    private function normalizeEvents(array $events): array
    {
        $events = array_values(array_unique(array_filter($events)));

        if ($events === []) {
            throw ValidationException::withMessages([
                'events' => ['Select at least one webhook event.'],
            ]);
        }

        $allowed = PartnerWebhookEvents::all();

        foreach ($events as $event) {
            if (! in_array($event, $allowed, true)) {
                throw ValidationException::withMessages([
                    'events' => ["Unsupported webhook event: {$event}"],
                ]);
            }
        }

        return $events;
    }
}
