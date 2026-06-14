<?php

namespace App\Http\Requests\Zones;

use App\Data\ZoneData;
use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.config('limits.zone_name')],
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
            color: $validated['color'] ?? '#6B7280',
            sort_order: $validated['sort_order'] ?? 0,
            is_active: $validated['is_active'] ?? true,
        );
    }
}
