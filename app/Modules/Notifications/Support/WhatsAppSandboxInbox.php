<?php

namespace App\Modules\Notifications\Support;

use Illuminate\Support\Facades\Storage;

class WhatsAppSandboxInbox
{
    public const PATH = 'whatsapp-sandbox.json';

    public const LIMIT = 50;

    /**
     * @param  array<string, mixed>  $message
     */
    public function record(array $message): void
    {
        $items = $this->all();
        array_unshift($items, $message);
        $items = array_slice($items, 0, self::LIMIT);

        Storage::disk('local')->put(
            self::PATH,
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '[]',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (! Storage::disk('local')->exists(self::PATH)) {
            return [];
        }

        $decoded = json_decode((string) Storage::disk('local')->get(self::PATH), true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            static fn (mixed $item): bool => is_array($item),
        ));
    }
}
