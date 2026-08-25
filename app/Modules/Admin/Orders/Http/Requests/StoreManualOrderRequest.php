<?php

namespace App\Modules\Admin\Orders\Http\Requests;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('orders.create');
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $serviceType = (string) $this->input('service_type');

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('account_type', User::ACCOUNT_TYPE_CUSTOMER),
            ],
            'service_type' => ['required', 'string', Rule::in(Order::serviceTypes())],
            'booking_reference' => ['required', 'string', 'max:40'],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', 'string', 'size:3'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_status' => ['required', 'string', Rule::in([Order::PAYMENT_STATUS_PAID, Order::PAYMENT_STATUS_UNPAID])],
            'payment_method' => ['nullable', 'string', Rule::in([
                Order::PAYMENT_METHOD_CASH,
                Order::PAYMENT_METHOD_CARD,
                Order::PAYMENT_METHOD_WALLET,
                Order::PAYMENT_METHOD_BANK,
            ])],
            'passenger_name' => ['required', 'string', 'max:120'],
            'origin' => ['required_if:service_type,flight', 'nullable', 'string', 'max:64'],
            'destination' => ['required_if:service_type,flight', 'nullable', 'string', 'max:64'],
            'departure_date' => ['nullable', 'date'],
            'return_date' => ['nullable', 'date', 'after_or_equal:departure_date'],
            'hotel_name' => ['required_if:service_type,hotel', 'nullable', 'string', 'max:160'],
            'check_in' => ['required_if:service_type,hotel', 'nullable', 'date'],
            'check_out' => ['required_if:service_type,hotel', 'nullable', 'date', 'after_or_equal:check_in'],
            'insurance_type' => ['required_if:service_type,insurance', 'nullable', 'string', 'max:120'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
