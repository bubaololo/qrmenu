<?php

namespace App\Http\Requests\Menus;

use App\Enums\OptionGroupKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'name' => ['required', 'string', 'max:'.config('limits.name')],
            'type' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(OptionGroupKind::class)],
            'required' => ['nullable', 'boolean'],
            'allow_multiple' => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
