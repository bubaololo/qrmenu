<?php

namespace Database\Factories;

use App\Enums\OrderItemKitchenStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'quantity' => fake()->numberBetween(1, 4),
            'unit_price' => fake()->randomFloat(2, 1, 50),
            'currency' => 'USD',
            'kitchen_status' => OrderItemKitchenStatus::Waiting,
        ];
    }

    public function cooking(): static
    {
        return $this->state([
            'kitchen_status' => OrderItemKitchenStatus::Cooking,
            'started_cooking_at' => now(),
        ]);
    }

    public function ready(): static
    {
        return $this->state([
            'kitchen_status' => OrderItemKitchenStatus::Ready,
            'started_cooking_at' => now()->subMinutes(10),
            'ready_at' => now(),
        ]);
    }
}
