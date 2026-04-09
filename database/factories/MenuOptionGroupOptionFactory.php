<?php

namespace Database\Factories;

use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOptionGroupOption>
 */
class MenuOptionGroupOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_id' => MenuOptionGroup::factory(),
            'price_adjust' => 0,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
