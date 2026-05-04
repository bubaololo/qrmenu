<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class SplitBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'splits' => ['required', 'array', 'min:2'],
            'splits.*.order_item_ids' => ['required', 'array', 'min:1'],
            'splits.*.order_item_ids.*' => ['integer', 'exists:order_items,id'],
        ];
    }
}
