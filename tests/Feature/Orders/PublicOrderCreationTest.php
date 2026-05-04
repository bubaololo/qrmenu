<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{restaurant: Restaurant, table: DiningTable, menu: Menu, item: MenuItem, section: MenuSection}
     */
    private function setupMenu(): array
    {
        $restaurant = Restaurant::factory()->create(['currency' => 'USD']);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id, 'is_active' => true]);
        $item = MenuItem::factory()->create([
            'section_id' => $section->id,
            'price_value' => 12.50,
            'is_visible' => true,
            'is_orderable' => true,
        ]);

        return [
            'restaurant' => $restaurant,
            'table' => $table,
            'menu' => $menu,
            'item' => $item,
            'section' => $section,
        ];
    }

    #[Test]
    public function test_creates_order_with_snapshot_price(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();

        $response = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => OrderStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'menu_item_id' => $i->id,
            'quantity' => 2,
            'unit_price' => '12.50',
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('bills', [
            'dining_table_id' => $t->id,
            'status' => 'open',
        ]);
    }

    #[Test]
    public function test_sets_guest_token_cookie(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])
            ->assertStatus(201)
            ->assertCookie('guest_token');
    }

    #[Test]
    public function test_rejects_unknown_restaurant(): void
    {
        ['table' => $t, 'item' => $i] = $this->setupMenu();

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => 'doesnotexist',
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['restaurant_uniqid']);
    }

    #[Test]
    public function test_rejects_table_from_another_restaurant(): void
    {
        ['restaurant' => $r1, 'item' => $i] = $this->setupMenu();
        ['table' => $foreignTable] = $this->setupMenu();

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r1->uniqid,
            'table_uniqid' => $foreignTable->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['table_uniqid']);
    }

    #[Test]
    public function test_rejects_menu_item_from_another_restaurants_menu(): void
    {
        ['restaurant' => $r, 'table' => $t] = $this->setupMenu();
        ['item' => $foreignItem] = $this->setupMenu();

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $foreignItem->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function test_rejects_inactive_section_item(): void
    {
        ['restaurant' => $r, 'table' => $t, 'section' => $section] = $this->setupMenu();
        $section->update(['is_active' => false]);

        $item = MenuItem::query()->where('section_id', $section->id)->first();

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function test_rejects_unorderable_item(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();
        $i->update(['is_orderable' => false]);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function test_rejects_invisible_item(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();
        $i->update(['is_visible' => false]);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    #[Test]
    public function test_reuses_open_bill_for_same_table(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();

        $first = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $second = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [['menu_item_id' => $i->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $billId1 = $first->json('data.attributes.bill_id');
        $billId2 = $second->json('data.attributes.bill_id');
        $this->assertSame($billId1, $billId2);
        $this->assertSame(1, Bill::query()->count());
    }
}
