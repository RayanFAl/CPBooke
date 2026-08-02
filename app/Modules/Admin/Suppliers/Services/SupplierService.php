<?php

namespace App\Modules\Admin\Suppliers\Services;

use App\Models\Provider;
use App\Modules\Wallets\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    /**
     * @param  array{search?: string|null, status?: string|null}  $filters
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;

        return Provider::query()
            ->withCount('wallets')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('legal_name', 'like', "%{$search}%")
                        ->orWhere('contact_email', 'like', "%{$search}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Provider
    {
        $key = $this->normalizeKey((string) $data['key']);

        if (Provider::query()->where('key', $key)->exists()) {
            throw ValidationException::withMessages([
                'key' => 'A supplier with this key already exists.',
            ]);
        }

        return Provider::query()->create([
            ...$this->normalizePayload($data),
            'key' => $key,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Provider $provider, array $data): Provider
    {
        $payload = $this->normalizePayload($data);

        if (array_key_exists('key', $data)) {
            $key = $this->normalizeKey((string) $data['key']);

            $exists = Provider::query()
                ->where('key', $key)
                ->where('id', '!=', $provider->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'key' => 'A supplier with this key already exists.',
                ]);
            }

            $payload['key'] = $key;
        }

        $provider->fill($payload)->save();

        return $provider->refresh();
    }

    /**
     * Ensure BookNow exists with sensible supplier defaults.
     */
    public function ensureBooknow(): Provider
    {
        return $this->walletService->findOrCreateProviderByKey(Provider::KEY_BOOKNOW, 'BookNow');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data): array
    {
        return [
            'name' => $data['name'],
            'legal_name' => $data['legal_name'] ?? null,
            'status' => $data['status'] ?? Provider::STATUS_ACTIVE,
            'commission_rate' => isset($data['commission_rate']) && $data['commission_rate'] !== ''
                ? number_format((float) $data['commission_rate'], 2, '.', '')
                : null,
            'settlement_cycle' => $data['settlement_cycle'] ?? Provider::SETTLEMENT_MONTHLY,
            'credit_limit' => isset($data['credit_limit']) && $data['credit_limit'] !== ''
                ? number_format((float) $data['credit_limit'], 2, '.', '')
                : null,
            'default_currency' => strtoupper((string) ($data['default_currency'] ?? \App\Support\Platform\PlatformSettings::defaultCurrency())),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'integration_status' => $data['integration_status'] ?? Provider::INTEGRATION_NOT_CONFIGURED,
            'contract_starts_at' => $data['contract_starts_at'] ?? null,
            'contract_ends_at' => $data['contract_ends_at'] ?? null,
            'contract_notes' => $data['contract_notes'] ?? null,
            'notes' => $data['notes'] ?? null,
            'website' => $data['website'] ?? null,
        ];
    }

    private function normalizeKey(string $key): string
    {
        $normalized = Str::of($key)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '-')
            ->trim('-')
            ->toString();

        if ($normalized === '') {
            throw ValidationException::withMessages([
                'key' => 'Supplier key is required.',
            ]);
        }

        return $normalized;
    }
}
