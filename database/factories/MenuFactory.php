<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'source_locale' => 'vi',
            'is_active' => false,
            'source_images_count' => 0,
        ];
    }
}
