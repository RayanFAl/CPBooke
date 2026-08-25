<?php

namespace App\Modules\Api\AI\Http\Requests;

use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class TravelAssistantRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:2000'],
            'conversation' => ['nullable', 'array', 'max:20'],
            'conversation.*.role' => ['nullable', 'string', 'max:32'],
            'conversation.*.text' => ['nullable', 'string', 'max:1000'],
            'conversation.*.content' => ['nullable', 'string', 'max:1000'],
            'searchContext' => ['nullable', 'array'],
            'searchContext.product' => ['nullable', 'string', 'max:32'],
            'searchContext.origin' => ['nullable', 'string', 'max:120'],
            'searchContext.destination' => ['nullable', 'string', 'max:120'],
            'searchContext.departureDate' => ['nullable', 'string', 'max:32'],
            'searchContext.returnDate' => ['nullable', 'string', 'max:32'],
            'searchContext.adults' => ['nullable', 'integer', 'min:1', 'max:9'],
            'searchContext.children' => ['nullable', 'integer', 'min:0', 'max:9'],
            'searchContext.infants' => ['nullable', 'integer', 'min:0', 'max:9'],
            'searchContext.cabinClass' => ['nullable', 'string', 'max:32'],
            'searchContext.nonStop' => ['nullable', 'boolean'],
            'searchContext.airline' => ['nullable', 'string', 'max:80'],
            'searchContext.departureTimeFrom' => ['nullable', 'string', 'max:8'],
            'searchContext.departureTimeTo' => ['nullable', 'string', 'max:8'],
            'searchContext.budget' => ['nullable', 'numeric', 'min:0'],
            'searchContext.sortPreference' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'array'],
            'search.origin' => ['nullable', 'string', 'max:8'],
            'search.destination' => ['nullable', 'string', 'max:8'],
            'search.adults' => ['nullable', 'integer', 'min:1', 'max:9'],
            'search.departureDate' => ['nullable', 'string', 'max:32'],
            'preferences' => ['nullable', 'array'],
            'preferences.priority' => ['nullable', 'string', 'max:32'],
            'preferences.sortPreference' => ['nullable', 'string', 'max:32'],
            'preferences.nonStop' => ['nullable', 'boolean'],
            'preferences.cabinClass' => ['nullable', 'string', 'max:32'],
            'preferences.departureTimeFrom' => ['nullable', 'string', 'max:8'],
            'preferences.departureTimeTo' => ['nullable', 'string', 'max:8'],
            'preferences.airline' => ['nullable', 'string', 'max:80'],
            'preferences.budget' => ['nullable', 'numeric', 'min:0'],
            'offers' => ['nullable', 'array', 'max:20'],
            'offers.*.offerId' => ['nullable', 'string', 'max:120'],
            'offers.*.offer_id' => ['nullable', 'string', 'max:120'],
            'offers.*.id' => ['nullable', 'string', 'max:120'],
            'offers.*.airline' => ['nullable', 'string', 'max:120'],
            'offers.*.price' => ['nullable', 'numeric'],
            'offers.*.currency' => ['nullable', 'string', 'max:8'],
            'offers.*.stops' => ['nullable', 'integer', 'min:0', 'max:10'],
            'offers.*.departure' => ['nullable', 'string', 'max:32'],
            'offers.*.arrival' => ['nullable', 'string', 'max:32'],
            'offers.*.duration' => ['nullable', 'string', 'max:32'],
            'mode' => ['nullable', 'string', 'in:interpret,recommend'],
            'forceGemini' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $mode = $this->input('mode');
            $hasOffers = is_array($this->input('offers')) && $this->input('offers') !== [];
            $message = trim((string) $this->input('message', ''));

            if ($mode === 'recommend' || ($mode === null && $hasOffers && $message === '')) {
                if (! $hasOffers) {
                    $validator->errors()->add('offers', 'At least one real offer is required for recommendations.');
                }

                return;
            }

            if ($message === '') {
                $validator->errors()->add('message', 'A message is required for travel assistant interpretation.');
            }
        });
    }
}
