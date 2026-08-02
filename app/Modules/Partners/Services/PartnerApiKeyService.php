<?php

namespace App\Modules\Partners\Services;

use App\Models\Partner;
use App\Models\PartnerApiKey;
use App\Models\User;
use Illuminate\Support\Str;

class PartnerApiKeyService
{
    /**
     * @return array{key: PartnerApiKey, plain_text: string}
     */
    public function create(Partner $partner, string $name, ?User $actor = null): array
    {
        $plainText = 'pk_live_'.Str::lower(Str::random(40));
        $prefix = substr($plainText, 0, 16);

        $key = $partner->apiKeys()->create([
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plainText),
            'created_by_user_id' => $actor?->id,
        ]);

        return [
            'key' => $key,
            'plain_text' => $plainText,
        ];
    }

    public function findActiveByPlainText(?string $plainText): ?PartnerApiKey
    {
        if ($plainText === null || $plainText === '') {
            return null;
        }

        $hash = hash('sha256', $plainText);

        $key = PartnerApiKey::query()
            ->with('partner')
            ->where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($key === null || $key->partner === null || ! $key->partner->isActive()) {
            return null;
        }

        return $key;
    }

    public function touchLastUsed(PartnerApiKey $key): void
    {
        $key->forceFill(['last_used_at' => now()])->save();
    }

    public function revoke(PartnerApiKey $key): PartnerApiKey
    {
        if ($key->revoked_at === null) {
            $key->forceFill(['revoked_at' => now()])->save();
        }

        return $key->refresh();
    }
}
