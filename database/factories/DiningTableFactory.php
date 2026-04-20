<?php

namespace Database\Factories;

use App\Enums\DiningTableShape;
use App\Models\DiningTable;
use App\Models\Hall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hall_id' => Hall::factory(),
            'number' => fake()->numberBetween(1, 50),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
            'shape' => fake()->randomElement(DiningTableShape::cases())->value,
            'x' => null,
            'y' => null,
            'width' => null,
            'height' => null,
            'rotation' => 0,
            'sort_order' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    public function withPosition(): static
    {
        return $this->state([
            'x' => fake()->randomFloat(2, 0, 800),
            'y' => fake()->randomFloat(2, 0, 600),
            'width' => fake()->randomFloat(2, 60, 120),
            'height' => fake()->randomFloat(2, 60, 120),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
