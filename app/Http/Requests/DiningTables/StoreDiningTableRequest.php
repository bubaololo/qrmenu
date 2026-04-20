<?php

namespace App\Http\Requests\DiningTables;

use App\Data\DiningTableData;
use App\Enums\DiningTableShape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['required', 'integer', 'min:1'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'shape' => ['sometimes', Rule::enum(DiningTableShape::class)],
            'x' => ['sometimes', 'nullable', 'numeric'],
            'y' => ['sometimes', 'nullable', 'numeric'],
            'width' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'height' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rotation' => ['sometimes', 'numeric', 'min:0', 'max:360'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function toData(): DiningTableData
    {
        $validated = $this->validated();

        return new DiningTableData(
            number: $validated['number'],
            capacity: $validated['capacity'] ?? 4,
            shape: DiningTableShape::from($validated['shape'] ?? DiningTableShape::Square->value),
            x: $validated['x'] ?? null,
            y: $validated['y'] ?? null,
            width: $validated['width'] ?? null,
            height: $validated['height'] ?? null,
            rotation: $validated['rotation'] ?? 0,
            sort_order: $validated['sort_order'] ?? 0,
            is_active: $validated['is_active'] ?? true,
        );
    }
}
