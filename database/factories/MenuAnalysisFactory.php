<?php

namespace Database\Factories;

use App\Enums\MenuAnalysisStatus;
use App\Models\MenuAnalysis;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuAnalysis>
 */
class MenuAnalysisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory(),
            'status' => MenuAnalysisStatus::Completed,
            'image_count' => 1,
            'image_paths' => [],
            'original_image_paths' => [],
            'image_disk' => 'public',
        ];
    }
}
