<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuAddonRequest extends FormRequest
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
