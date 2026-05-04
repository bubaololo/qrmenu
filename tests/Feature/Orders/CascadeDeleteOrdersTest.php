<?php

namespace Tests\Feature\Orders;

use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\Zone;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CascadeDeleteOrdersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{
     *     restaurant: Restaurant, table: DiningTable, menu: Menu, item: MenuItem,
     *     bill: Bill, order: Order, orderItem: OrderItem
     * }
     */
    private function setupOrder(): array
    {
        $r = Restaurant::factory()->create(['currency' => 'USD']);
        $z = Zone::factory()->create(['restaurant_id' => $r->id]);
        $t = DiningTable::factory()->create(['zone_id' => $z->id]);
        $m = Menu::factory()->create(['restaurant_id' => $r->id]);
        $s = MenuSection::factory()->create(['menu_id' => $m->id]);
        $i = MenuItem::factory()->create(['section_id' => $s->id, 'price_value' => 10]);

        $bill = Bill::factory()->create(['dining_table_id' => $t->id]);
        $order = Order::factory()->create(['bill_id' => $bill->id]);
        $oi = OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $i->id,
            'unit_price' => 10,
        ]);

        return ['restaurant' => $r, 'table' => $t, 'menu' => $m, 'item' => $i, 'bill' => $bill, 'order' => $order, 'orderItem' => $oi];
    }

    #[Test]
    public function test_delete_restaurant_cascades_to_bills_orders_items(): void
    {
        $ctx = $this->setupOrder();

        $ctx['restaurant']->delete();

        $this->assertDatabaseMissing('bills', ['id' => $ctx['bill']->id]);
        $this->assertDatabaseMissing('orders', ['id' => $ctx['order']->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $ctx['orderItem']->id]);
        $this->assertDatabaseMissing('menus', ['id' => $ctx['menu']->id]);
        $this->assertDatabaseMissing('menu_sections', ['menu_id' => $ctx['menu']->id]);
    }

    #[Test]
    public function test_delete_dining_table_cascades_to_bills_and_orders(): void
    {
        $ctx = $this->setupOrder();

        $ctx['table']->delete();

        $this->assertDatabaseMissing('bills', ['id' => $ctx['bill']->id]);
        $this->assertDatabaseMissing('orders', ['id' => $ctx['order']->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $ctx['orderItem']->id]);
    }

    #[Test]
    public function test_delete_zone_cascades_to_tables_then_to_orders(): void
    {
        $ctx = $this->setupOrder();
        $zone = $ctx['table']->zone;

        $zone->delete();

        $this->assertDatabaseMissing('dining_tables', ['id' => $ctx['table']->id]);
        $this->assertDatabaseMissing('orders', ['id' => $ctx['order']->id]);
    }

    #[Test]
    public function test_delete_order_cascades_to_order_items(): void
    {
        $ctx = $this->setupOrder();

        $ctx['order']->delete();

        $this->assertDatabaseMissing('order_items', ['id' => $ctx['orderItem']->id]);
        $this->assertDatabaseHas('bills', ['id' => $ctx['bill']->id]);
    }

    #[Test]
    public function test_delete_bill_cascades_to_orders(): void
    {
        $ctx = $this->setupOrder();

        $ctx['bill']->delete();

        $this->assertDatabaseMissing('bills', ['id' => $ctx['bill']->id]);
        $this->assertDatabaseMissing('orders', ['id' => $ctx['order']->id]);
        $this->assertDatabaseMissing('order_items', ['id' => $ctx['orderItem']->id]);
    }

    #[Test]
    public function test_can_delete_menu_when_orders_exist(): void
    {
        // After dropping orders.menu_id the menu has no FK from order rows,
        // so it can be removed without affecting orders.
        $ctx = $this->setupOrder();

        $ctx['menu']->delete();

        $this->assertDatabaseMissing('menus', ['id' => $ctx['menu']->id]);
        $this->assertDatabaseHas('orders', ['id' => $ctx['order']->id]);
    }

    #[Test]
    public function test_deleting_menu_item_cascades_to_order_items(): void
    {
        // Direct menu_item deletion cascades to its order_items. Admins
        // typically disable items via `is_orderable` instead of deleting; this
        // path exists mainly so a `restaurant->delete()` cascade resolves
        // cleanly when both parallel chains converge on order_items.
        $ctx = $this->setupOrder();

        $ctx['item']->delete();

        $this->assertDatabaseMissing('order_items', ['id' => $ctx['orderItem']->id]);
        $this->assertDatabaseHas('orders', ['id' => $ctx['order']->id]);
    }

    #[Test]
    public function test_restaurant_cannot_have_two_menus(): void
    {
        $r = Restaurant::factory()->create();
        Menu::factory()->create(['restaurant_id' => $r->id]);

        $this->expectException(QueryException::class);

        Menu::factory()->create(['restaurant_id' => $r->id]);
    }
}
