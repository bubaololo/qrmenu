<?php

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuSection>
 */
class MenuSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'sort_order' => 0,
        ];
    }
}
