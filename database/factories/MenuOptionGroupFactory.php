<?php

namespace Database\Factories;

use App\Enums\OptionGroupKind;
use App\Models\Menu;
use App\Models\MenuOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOptionGroup>
 */
class MenuOptionGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'kind' => OptionGroupKind::Addon,
            'required' => false,
            'allow_multiple' => false,
            'min_select' => 0,
            'sort_order' => 0,
        ];
    }

    public function variant(): static
    {
        return $this->state(fn (): array => ['kind' => OptionGroupKind::Variant]);
    }

    public function addon(): static
    {
        return $this->state(fn (): array => ['kind' => OptionGroupKind::Addon]);
    }
}
