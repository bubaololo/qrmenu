<?php

namespace Database\Factories;

use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOptionGroup>
 */
class MenuOptionGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'section_id' => MenuSection::factory(),
            'is_variation' => false,
            'required' => false,
            'allow_multiple' => false,
            'min_select' => 0,
            'sort_order' => 0,
        ];
    }
}
