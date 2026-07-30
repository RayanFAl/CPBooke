<?php

namespace App\Modules\Api\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'cpbooke_id' => $this->resource->id,
            'number' => $this->resource->booking_reference,
            'provider_name' => $this->resource->provider_name,
            'external_booking_id' => $this->resource->external_booking_id,
            'booking_reference' => $this->resource->booking_reference,
            'status' => $this->resource->status,
            'payment_status' => $this->resource->payment_status,
            'product_type' => $this->resource->service_type,
            'service_type' => $this->resource->service_type,
            'details' => $this->resource->details ?? [],
            'esim' => $this->resolveEsimSummary(),
            'insurance' => $this->resolveInsuranceSummary(),
            'currency' => $this->resource->currency,
            'total_amount' => $this->resource->total_amount,
            'base_amount' => $this->resource->base_amount,
            'tax_amount' => $this->resource->tax_amount,
            'request_payload' => $this->resource->request_payload ?? [],
            'response_payload' => $this->resource->response_payload ?? [],
            'error_message' => $this->resource->error_message,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveEsimSummary(): ?array
    {
        if ($this->resource->service_type !== 'esim') {
            return null;
        }

        $details = is_array($this->resource->details) ? $this->resource->details : [];
        $payload = is_array($this->resource->response_payload) ? $this->resource->response_payload : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $firstItem = $items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

        return [
            'title' => $details['title'] ?? ($firstItem['title'] ?? null),
            'country' => $details['country'] ?? ($itemDetails['country'] ?? null),
            'data' => $details['data'] ?? ($itemDetails['data'] ?? null),
            'validity_days' => $details['validity_days'] ?? ($itemDetails['validity_days'] ?? null),
            'iccid' => $details['iccid'] ?? ($itemDetails['iccid'] ?? null),
            'activation_code' => $details['activation_code'] ?? ($itemDetails['activation_code'] ?? null),
            'qr' => $details['qr'] ?? ($itemDetails['qr'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveInsuranceSummary(): ?array
    {
        if ($this->resource->service_type !== 'insurance') {
            return null;
        }

        $details = is_array($this->resource->details) ? $this->resource->details : [];
        $payload = is_array($this->resource->response_payload) ? $this->resource->response_payload : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $firstItem = $items[0] ?? [];
        $itemDetails = is_array($firstItem['item_details'] ?? null) ? $firstItem['item_details'] : [];

        return [
            'title' => $details['title'] ?? ($firstItem['title'] ?? null),
            'product_subtype' => $details['product_subtype'] ?? ($firstItem['product_subtype'] ?? null),
            'item_id' => isset($details['item_id'])
                ? (string) $details['item_id']
                : (isset($itemDetails['item_id']) ? (string) $itemDetails['item_id'] : null),
            'provider' => $details['provider'] ?? ($itemDetails['provider'] ?? null),
            'ticket_number' => $details['ticket_number'] ?? ($itemDetails['ticket_number'] ?? null),
            'report_reference' => $details['report_reference'] ?? ($itemDetails['report_reference'] ?? null),
            'zone_id' => $details['zone_id'] ?? ($itemDetails['zone_id'] ?? null),
            'zone_name' => $details['zone_name'] ?? ($itemDetails['zone_name'] ?? null),
            'duration_id' => $details['duration_id'] ?? ($itemDetails['duration_id'] ?? null),
            'duration_label' => $details['duration_label'] ?? ($itemDetails['duration_label'] ?? null),
            'policy_date_from' => $details['policy_date_from'] ?? ($itemDetails['policy_date_from'] ?? null),
            'policy_date_to' => $details['policy_date_to'] ?? ($itemDetails['policy_date_to'] ?? null),
        ];
    }
}