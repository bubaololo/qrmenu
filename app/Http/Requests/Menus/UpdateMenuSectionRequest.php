<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'icon_id' => ['sometimes', 'nullable', 'integer', 'exists:icons,id'],
            'icon_name' => ['sometimes', 'nullable', 'string', 'max:100', Rule::exists('icons', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
