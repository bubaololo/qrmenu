<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMenuOptionGroupRequest extends FormRequest
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
            'type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_variation' => ['sometimes', 'boolean'],
            'required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'min_select' => ['sometimes', 'integer', 'min:0'],
            'max_select' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
