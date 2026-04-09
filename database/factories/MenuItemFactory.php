<?php

namespace Database\Factories;

use App\Enums\PriceType;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => MenuSection::factory(),
            'price_type' => PriceType::Fixed,
            'price_value' => $this->faker->randomFloat(2, 1, 50),
            'price_original_text' => '',
            'starred' => false,
            'sort_order' => 0,
        ];
    }
}
