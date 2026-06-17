<?php

namespace Database\Factories;

use App\Enums\ModifierPricingMode;
use App\Models\Menu;
use App\Models\ModifierGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModifierGroup>
 */
class ModifierGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_option_id' => null,
            'pricing_mode' => ModifierPricingMode::Add,
            'selection_type' => 'multi',
            'selection_min' => 0,
            'selection_max' => null,
            'required' => false,
            'charge_above' => null,
            'portion_denominator' => 1,
            'sort_order' => 0,
        ];
    }

    /** A "Size"-style axis: absolute price, pick exactly one, required. */
    public function variation(): static
    {
        return $this->state(fn () => [
            'pricing_mode' => ModifierPricingMode::Replace,
            'selection_type' => 'single',
            'selection_min' => 1,
            'selection_max' => 1,
            'required' => true,
        ]);
    }
}
