<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderItemKitchenStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateOrderItemKitchenStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kitchen_status' => ['required', new Enum(OrderItemKitchenStatus::class)],
        ];
    }
}
