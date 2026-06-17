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
            'linked_menu_item_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
