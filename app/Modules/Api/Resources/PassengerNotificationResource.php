<?php

namespace App\Modules\Api\Resources;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassengerNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var UserNotification $notification */
        $notification = $this->resource;
        $variables = (array) data_get($notification->data, 'variables', []);

        $orderId = $notification->related_type === 'order'
            ? $notification->related_id
            : ($variables['order_id'] ?? null);

        $productType = $variables['product_type']
            ?? $variables['service_type']
            ?? null;

        return [
            'id' => (string) $notification->id,
            'title' => $notification->title,
            'body' => $notification->message,
            'type' => $this->normalizeType($notification->type),
            'is_read' => ! $notification->isUnread(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'deep_link' => $this->deepLink($notification, $variables),
            'meta' => array_filter([
                'order_id' => $orderId !== null ? (string) $orderId : null,
                'product_type' => $productType !== null ? (string) $productType : null,
                'template_code' => $notification->template_code,
                'related_type' => $notification->related_type,
                'related_id' => $notification->related_id !== null ? (string) $notification->related_id : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function deepLink(UserNotification $notification, array $variables): ?string
    {
        if (is_string($variables['deep_link'] ?? null) && $variables['deep_link'] !== '') {
            return $variables['deep_link'];
        }

        return match ($notification->related_type) {
            'order' => '/my-orders',
            'support_ticket', 'support' => '/support/'.$notification->related_id,
            default => null,
        };
    }

    private function normalizeType(?string $type): string
    {
        $normalized = strtolower((string) $type);

        return match ($normalized) {
            'success', 'flight', 'payment', 'tag', 'order', 'system' => $normalized,
            'promo', 'promotional', 'marketing' => 'tag',
            'hotel', 'insurance', 'esim', 'bundle' => 'order',
            'refund', 'finance' => 'payment',
            'loyalty' => 'success',
            'support', 'login', 'security' => 'system',
            default => $normalized !== '' ? $normalized : 'system',
        };
    }
}