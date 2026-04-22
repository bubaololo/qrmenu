<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon_id' => ['nullable', 'integer', 'exists:icons,id'],
            'icon_name' => ['nullable', 'string', 'max:100', Rule::in(config('food_icons.allowed', []))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
