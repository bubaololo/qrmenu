<?php

namespace App\Http\Requests\DiningTables;

use App\Data\DiningTableData;
use App\Enums\DiningTableShape;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'number' => ['sometimes', 'integer', 'min:1'],
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
        $table = $this->route('diningTable');

        return new DiningTableData(
            number: $validated['number'] ?? $table->number,
            capacity: $validated['capacity'] ?? $table->capacity,
            shape: DiningTableShape::from($validated['shape'] ?? $table->shape->value),
            x: array_key_exists('x', $validated) ? $validated['x'] : $table->x,
            y: array_key_exists('y', $validated) ? $validated['y'] : $table->y,
            width: array_key_exists('width', $validated) ? $validated['width'] : $table->width,
            height: array_key_exists('height', $validated) ? $validated['height'] : $table->height,
            rotation: $validated['rotation'] ?? $table->rotation,
            sort_order: $validated['sort_order'] ?? $table->sort_order,
            is_active: $validated['is_active'] ?? $table->is_active,
        );
    }
}
