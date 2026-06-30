<?php

namespace App\Modules\Api\Orders\Http\Requests;

use App\Models\Order;
use App\Modules\Api\DTO\CreateOrderDTO;
use App\Modules\Api\Support\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

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
            'service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'details' => ['required', 'array'],
            'details.passenger_name' => ['required_if:service_type,flight', 'string', 'max:120'],
            'details.airline' => ['required_if:service_type,flight', 'string', 'max:120'],
            'details.pnr' => ['required_if:service_type,flight', 'string', 'max:40'],
            'details.hotel_name' => ['required_if:service_type,hotel', 'string', 'max:160'],
            'details.check_in' => ['required_if:service_type,hotel', 'date'],
            'details.check_out' => ['required_if:service_type,hotel', 'date', 'after_or_equal:details.check_in'],
            'details.guests' => ['required_if:service_type,hotel', 'integer', 'min:1'],
            'details.insurance_type' => ['required_if:service_type,insurance', 'string', 'max:120'],
            'details.coverage_days' => ['required_if:service_type,insurance', 'integer', 'min:1'],
            'details.beneficiary_name' => ['required_if:service_type,insurance', 'string', 'max:120'],
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