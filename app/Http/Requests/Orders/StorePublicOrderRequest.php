<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'restaurant_uniqid' => ['required', 'string', 'min:8', 'max:32'],
            'table_uniqid' => ['required', 'string', 'min:8', 'max:32'],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'items.*.variation_option_id' => ['nullable', 'integer', 'exists:menu_option_group_options,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.selected_options' => ['nullable', 'array'],
            'items.*.selected_options.*.group_id' => ['required', 'integer'],
            'items.*.selected_options.*.option_ids' => ['required', 'array'],
            'items.*.selected_options.*.option_ids.*' => ['integer'],
        ];
    }
}
