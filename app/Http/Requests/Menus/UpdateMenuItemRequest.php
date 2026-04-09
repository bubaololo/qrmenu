<?php

namespace App\Http\Requests\Menus;

use App\Enums\PriceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateMenuItemRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string'],
            'starred' => ['sometimes', 'boolean'],
            'price_type' => ['sometimes', new Enum(PriceType::class)],
            'price_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_min' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'price_original_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
