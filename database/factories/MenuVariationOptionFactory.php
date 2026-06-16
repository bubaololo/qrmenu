<?php

namespace Database\Factories;

use App\Models\MenuVariation;
use App\Models\MenuVariationOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuVariationOption>
 */
class MenuVariationOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'variation_id' => MenuVariation::factory(),
            'price' => $this->faker->randomFloat(2, 1, 50),
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
