<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.config('limits.name')],
            'pricing_mode' => ['sometimes', 'required', 'in:replace,add'],
            'selection_type' => ['sometimes', 'required', 'in:single,multi,portion'],
            'selection_min' => ['sometimes', 'integer', 'min:0'],
            'selection_max' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'required' => ['sometimes', 'boolean'],
            'charge_above' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'portion_denominator' => ['sometimes', 'integer', 'min:1', 'max:255'],
            'price_driver_group_id' => ['sometimes', 'nullable', 'integer', 'exists:modifier_groups,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
