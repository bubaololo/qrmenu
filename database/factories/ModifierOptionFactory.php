<?php

namespace Database\Factories;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierOption>
 */
class ModifierOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => ModifierGroup::factory(),
            'price' => $this->faker->randomFloat(2, 1, 50),
            'is_default' => false,
            'default_qty' => 1,
            'max_qty' => 1,
            'linked_menu_item_id' => null,
            'sort_order' => 0,
        ];
    }
}
