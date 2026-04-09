<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuOptionGroupRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'max:100'],
            'is_variation' => ['nullable', 'boolean'],
            'required' => ['nullable', 'boolean'],
            'allow_multiple' => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
