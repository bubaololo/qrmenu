<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuAddon>
 */
class MenuAddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'price' => $this->faker->randomFloat(2, 1, 20),
            'sort_order' => 0,
        ];
    }
}
