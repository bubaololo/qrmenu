<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupRequest extends FormRequest
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
            'pricing_mode' => ['required', 'in:replace,add'],
            'selection_type' => ['nullable', 'in:single,multi,portion'],
            'selection_min' => ['nullable', 'integer', 'min:0'],
            'selection_max' => ['nullable', 'integer', 'min:1'],
            'required' => ['nullable', 'boolean'],
            'charge_above' => ['nullable', 'integer', 'min:0'],
            'portion_denominator' => ['nullable', 'integer', 'min:1', 'max:255'],
            'price_driver_group_id' => ['nullable', 'integer', 'exists:modifier_groups,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
