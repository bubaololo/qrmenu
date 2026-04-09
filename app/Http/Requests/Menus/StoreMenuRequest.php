<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class StoreMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_locale' => ['required', 'string', 'max:10'],
            'detected_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
