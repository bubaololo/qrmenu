<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\TableShape;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'table_shape_id' => fn () => TableShape::inRandomOrder()->value('id'),
            'number' => fake()->unique()->numberBetween(1, 9999),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
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
