<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class UpdateModifierOptionRequest extends FormRequest
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
            'price' => ['sometimes', 'nullable', 'numeric'],
            'is_default' => ['sometimes', 'boolean'],
            'default_qty' => ['sometimes', 'integer', 'min:0'],
            'max_qty' => ['sometimes', 'integer', 'min:1'],
            'linked_menu_item_id' => ['sometimes', 'nullable', 'integer', 'exists:menu_items,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
