<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Modules\Api\DTO\CreateOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;

class CreateOrderRequest extends ApiFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'provider_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'request_payload' => ['required', 'array'],
        ];
    }

    /**
     * Convert the request payload to a DTO.
     */
    public function toDto(): CreateOrderDTO
    {
        return CreateOrderDTO::fromArray($this->validated());
    }
}