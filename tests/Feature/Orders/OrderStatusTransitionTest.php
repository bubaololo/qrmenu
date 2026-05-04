<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\RestaurantUserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function staffOf(Restaurant $restaurant, RestaurantUserRole $role = RestaurantUserRole::Waiter): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    #[Test]
    public function test_pending_to_in_progress_allowed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->staffOf($restaurant);
        $order = Order::factory()->forRestaurant($restaurant)->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/orders/{$order->id}", ['status' => OrderStatus::InProgress->value])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.status', OrderStatus::InProgress->value);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::InProgress->value]);
    }

    #[Test]
    public function test_completed_cannot_revert(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->staffOf($restaurant);
        $order = Order::factory()->forRestaurant($restaurant)->completed()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/orders/{$order->id}", ['status' => OrderStatus::Pending->value])
            ->assertStatus(422);
    }

    #[Test]
    public function test_destroy_marks_cancelled(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->staffOf($restaurant);
        $order = Order::factory()->forRestaurant($restaurant)->create();

        $this->actingAs($user)
            ->deleteJson("/api/v1/orders/{$order->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::Cancelled->value]);
    }

    #[Test]
    public function test_outsider_cannot_view_or_update(): void
    {
        $restaurant = Restaurant::factory()->create();
        $stranger = User::factory()->create();
        $order = Order::factory()->forRestaurant($restaurant)->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->patchJson("/api/v1/orders/{$order->id}", ['status' => OrderStatus::Completed->value])
            ->assertStatus(403);
    }
}
