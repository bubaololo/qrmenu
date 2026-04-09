<?php

namespace App\Http\Requests\Menus;

use App\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMenuItemRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'starred' => ['nullable', 'boolean'],
            'price_type' => ['nullable', new Enum(PriceType::class)],
            'price_value' => ['nullable', 'numeric', 'min:0'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'price_unit' => ['nullable', 'string', 'max:50'],
            'price_original_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
