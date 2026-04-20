<?php

namespace App\Http\Requests\Halls;

use App\Data\HallData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHallRequest extends FormRequest
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
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): HallData
    {
        $validated = $this->validated();

        return new HallData(
            name: $validated['name'],
            color: $validated['color'] ?? $this->route('hall')->color,
            sort_order: $validated['sort_order'] ?? $this->route('hall')->sort_order,
            is_active: $validated['is_active'] ?? $this->route('hall')->is_active,
        );
    }
}
