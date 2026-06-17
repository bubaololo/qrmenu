<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.config('limits.name')],
            'price' => ['nullable', 'numeric'],
            'is_default' => ['nullable', 'boolean'],
            'default_qty' => ['nullable', 'integer', 'min:0'],
            'max_qty' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            // Size-dependent price matrix: price per driver option.
            'prices' => ['sometimes', 'array'],
            'prices.*.driver_option_id' => ['required_with:prices', 'integer', 'exists:modifier_options,id'],
            'prices.*.price' => ['required_with:prices', 'numeric'],
        ];
    }
}
