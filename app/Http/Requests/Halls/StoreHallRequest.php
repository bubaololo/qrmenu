<?php

namespace App\Http\Requests\Halls;

use App\Data\HallData;
use Illuminate\Foundation\Http\FormRequest;

class StoreHallRequest extends FormRequest
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
            color: $validated['color'] ?? '#6B7280',
            sort_order: $validated['sort_order'] ?? 0,
            is_active: $validated['is_active'] ?? true,
        );
    }
}
