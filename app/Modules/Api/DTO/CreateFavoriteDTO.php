<?php

namespace App\Modules\Api\DTO;

use Illuminate\Support\Carbon;

final readonly class CreateFavoriteDTO
{
    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>|null  $searchContext
     */
    public function __construct(
        public string $type,
        public string $itemKey,
        public array $snapshot,
        public ?array $searchContext,
        public ?Carbon $expiresAt,
    ) {
    }

    /**
     * Create a DTO from validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            itemKey: trim((string) $data['item_key']),
            snapshot: $data['snapshot'],
            searchContext: $data['search_context'] ?? null,
            expiresAt: isset($data['expires_at'])
                ? Carbon::parse($data['expires_at'])
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(int $userId, string $status): array
    {
        return [
            'user_id' => $userId,
            'type' => $this->type,
            'item_key' => $this->itemKey,
            'status' => $status,
            'snapshot' => $this->snapshot,
            'search_context' => $this->searchContext,
            'expires_at' => $this->expiresAt,
        ];
    }
}
