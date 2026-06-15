<?php

namespace App\Http\Requests\Menus;

use App\Enums\OptionGroupKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['sometimes', 'string', 'max:'.config('limits.name')],
            'type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'kind' => ['sometimes', Rule::enum(OptionGroupKind::class)],
            'required' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'min_select' => ['sometimes', 'integer', 'min:0'],
            'max_select' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
