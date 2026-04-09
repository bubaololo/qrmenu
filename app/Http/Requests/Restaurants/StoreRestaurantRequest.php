<?php

namespace App\Http\Requests\Restaurants;

use Illuminate\Foundation\Http\FormRequest;

class StoreRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
            'primary_language' => ['sometimes', 'nullable', 'string', 'max:10'],
            'opening_hours' => ['sometimes', 'nullable', 'array'],
            'opening_hours.raw_text' => ['sometimes', 'nullable', 'string'],
            'opening_hours.is_24_7' => ['sometimes', 'boolean'],
            'opening_hours.periods' => ['sometimes', 'array'],
            'opening_hours.periods.*.days' => ['required_with:opening_hours.periods', 'array'],
            'opening_hours.periods.*.open' => ['required_with:opening_hours.periods', 'string', 'date_format:H:i'],
            'opening_hours.periods.*.close' => ['required_with:opening_hours.periods', 'string', 'date_format:H:i'],
        ];
    }
}
