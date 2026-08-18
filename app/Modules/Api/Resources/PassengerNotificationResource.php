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
                'family' => $variables['family'] ?? null,
                'category' => $variables['category'] ?? $this->categoryFromType($notification->type, $productType),
                'severity' => $variables['severity'] ?? null,
                'recipient' => $variables['recipient'] ?? null,
                'channels' => is_array($variables['channels'] ?? null) && $variables['channels'] !== []
                    ? $variables['channels']
                    : null,
                'expires_at' => $variables['expires_at'] ?? null,
                'action_engine' => (bool) ($variables['action_engine'] ?? false) ?: null,
                'from_value' => $variables['from_value'] ?? null,
                'to_value' => $variables['to_value'] ?? null,
                'actions' => is_array($variables['actions'] ?? null) && $variables['actions'] !== []
                    ? $variables['actions']
                    : null,
                'origin' => $variables['origin'] ?? null,
                'destination' => $variables['destination'] ?? null,
                'route' => $variables['route'] ?? null,
                'departure_time' => $variables['departure_time'] ?? null,
                'departure_clock' => $variables['departure_clock'] ?? null,
                'arrival_time' => $variables['arrival_time'] ?? null,
                'destination_country' => $variables['destination_country'] ?? null,
                'journey_card' => (bool) ($variables['journey_card'] ?? false) ?: null,
                'stage' => $variables['stage'] ?? null,
                'checklist' => is_array($variables['checklist'] ?? null) && $variables['checklist'] !== []
                    ? $variables['checklist']
                    : null,
                'next_best_offer' => is_array($variables['next_best_offer'] ?? null)
                    ? $variables['next_best_offer']
                    : null,
                'offers' => is_array($variables['offers'] ?? null) && $variables['offers'] !== []
                    ? $variables['offers']
                    : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== false),
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
            'order' => '/my-orders/'.$notification->related_id,
            'support_ticket', 'support' => '/support/'.$notification->related_id,
            'price_alert', 'travel_search_intent' => $variables['deep_link'] ?? '/flights',
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

    private function categoryFromType(?string $type, mixed $productType): ?string
    {
        return match (strtolower((string) $productType)) {
            'flight' => 'flights',
            'hotel' => 'hotels',
            'insurance' => 'insurance',
            'esim' => 'esim',
            default => match (strtolower((string) $type)) {
                'flight' => 'flights',
                'payment' => 'payments',
                'tag' => 'offers',
                'system' => 'security',
                default => null,
            },
        };
    }
}
