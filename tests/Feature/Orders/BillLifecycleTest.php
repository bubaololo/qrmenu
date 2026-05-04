<?php

namespace Tests\Feature\Orders;

use App\Enums\BillStatus;
use App\Enums\RestaurantUserRole;
use App\Models\Bill;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Restaurant, 1: User} */
    private function withStaff(): array
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Waiter->value]);

        return [$restaurant, $user];
    }

    #[Test]
    public function test_close_calculates_total_from_order_items(): void
    {
        [$restaurant, $user] = $this->withStaff();
        $bill = Bill::factory()->forRestaurant($restaurant)->create();
        $order = Order::factory()->create(['bill_id' => $bill->id]);
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2, 'unit_price' => 5.00]);
        OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'unit_price' => 7.50]);

        $this->actingAs($user)
            ->postJson("/api/v1/bills/{$bill->id}/close")
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.total_amount', '17.50')
            ->assertJsonPath('data.attributes.status', BillStatus::Closed->value);
    }

    #[Test]
    public function test_split_creates_separate_closed_bills(): void
    {
        [$restaurant, $user] = $this->withStaff();
        $bill = Bill::factory()->forRestaurant($restaurant)->create();
        $order = Order::factory()->create(['bill_id' => $bill->id]);
        $a = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'unit_price' => 10]);
        $b = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 1, 'unit_price' => 20]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/bills/{$bill->id}/split", [
                'splits' => [
                    ['order_item_ids' => [$a->id]],
                    ['order_item_ids' => [$b->id]],
                ],
            ])
            ->assertStatus(200);

        $this->assertSame(2, count($response->json('data')));
        $this->assertSame(BillStatus::Closed->value, Bill::find($bill->id)->status->value);
        $this->assertSame(3, Bill::query()->count());
    }

    #[Test]
    public function test_close_already_closed_returns_validation_error(): void
    {
        [$restaurant, $user] = $this->withStaff();
        $bill = Bill::factory()->forRestaurant($restaurant)->closed()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/bills/{$bill->id}/close")
            ->assertStatus(422);
    }
}
