<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'guest_token' => (string) Str::uuid(),
            'status' => OrderStatus::Pending,
            'placed_at' => now(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state([
            'status' => OrderStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => OrderStatus::Completed,
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_reason' => 'Customer changed mind',
        ]);
    }

    /**
     * Build the full restaurant → zone → dining_table → bill chain so the order
     * resolves back to the given restaurant via `Order::forRestaurant`.
     */
    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(function () use ($restaurant): array {
            $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
            $table = DiningTable::factory()->create(['zone_id' => $zone->id]);
            $bill = Bill::factory()->create(['dining_table_id' => $table->id]);

            return ['bill_id' => $bill->id];
        });
    }
}
