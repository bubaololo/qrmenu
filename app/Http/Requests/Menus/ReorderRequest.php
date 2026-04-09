<?php

namespace App\Http\Requests\Menus;

use Illuminate\Foundation\Http\FormRequest;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*.id' => ['required', 'integer'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }
}
