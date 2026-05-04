<?php

namespace Database\Factories;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dining_table_id' => DiningTable::factory(),
            'status' => BillStatus::Open,
            'currency' => 'USD',
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status' => BillStatus::Closed,
            'closed_at' => now(),
            'total_amount' => fake()->randomFloat(2, 5, 200),
        ]);
    }

    /**
     * Pin the bill to a fresh dining table inside the given restaurant.
     */
    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(function () use ($restaurant): array {
            $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
            $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

            return ['dining_table_id' => $table->id];
        });
    }
}
