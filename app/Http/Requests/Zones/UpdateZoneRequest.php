<?php

namespace App\Http\Requests\Zones;

use App\Data\ZoneData;
use Illuminate\Foundation\Http\FormRequest;

class UpdateZoneRequest extends FormRequest
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

    public function toData(): ZoneData
    {
        $validated = $this->validated();

        return new ZoneData(
            name: $validated['name'],
            color: $validated['color'] ?? $this->route('zone')->color,
            sort_order: $validated['sort_order'] ?? $this->route('zone')->sort_order,
            is_active: $validated['is_active'] ?? $this->route('zone')->is_active,
        );
    }
}
