<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderItemKitchenStatus;
use App\Enums\RestaurantUserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KitchenStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_updates_kitchen_status_and_timestamps(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Waiter->value]);
        $order = Order::factory()->forRestaurant($restaurant)->create();
        $item = OrderItem::factory()->create(['order_id' => $order->id]);

        $this->actingAs($user)
            ->patchJson("/api/v1/order-items/{$item->id}", ['kitchen_status' => OrderItemKitchenStatus::Cooking->value])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.kitchen_status', OrderItemKitchenStatus::Cooking->value);

        $this->assertNotNull($item->fresh()->started_cooking_at);

        $this->actingAs($user)
            ->patchJson("/api/v1/order-items/{$item->id}", ['kitchen_status' => OrderItemKitchenStatus::Ready->value])
            ->assertStatus(200);

        $this->assertNotNull($item->fresh()->ready_at);
    }
}
