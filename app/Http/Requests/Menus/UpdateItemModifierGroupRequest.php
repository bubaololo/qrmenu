<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Per-item overrides of an attached modifier group's selection rule. Each
 * `*_override` is nullable — send `null` to inherit the group's own default.
 */
class UpdateItemModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'selection_min_override' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'selection_max_override' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'required_override' => ['sometimes', 'nullable', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
