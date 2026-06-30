<?php

namespace App\Modules\Admin\Support\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupportTicketRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'category' => ['required', 'string', Rule::in(['booking_change', 'refund_request', 'technical_issue', 'payment_issue', 'document_request'])],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assigned_agent_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:160'],
            'first_message' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * Configure additional support-specific validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $orderId = $this->integer('order_id');

            if (! $orderId) {
                return;
            }

            $order = Order::query()->select(['id', 'customer_id'])->find($orderId);

            if ($order !== null && $order->customer_id !== $this->integer('user_id')) {
                $validator->errors()->add('order_id', 'The selected order does not belong to the selected customer.');
            }
        });
    }
}