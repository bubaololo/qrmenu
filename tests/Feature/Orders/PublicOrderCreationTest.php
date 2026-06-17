<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Bill;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OrderItem;
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
        $item->setTranslation('name', 'en', 'Pizza', isInitial: true);

        return [
            'restaurant' => $restaurant,
            'table' => $table,
            'menu' => $menu,
            'item' => $item,
            'section' => $section,
        ];
    }

    /** Build a REPLACE (Size-style) group attached to $item with one option. */
    private function makeReplaceGroup(Menu $menu, MenuItem $item, float $price, string $name = 'Large'): ModifierOption
    {
        $group = ModifierGroup::factory()->variation()->create(['menu_id' => $menu->id]);
        $group->setTranslation('name', 'en', 'Size', isInitial: true);
        $option = ModifierOption::factory()->create(['group_id' => $group->id, 'price' => $price]);
        $option->setTranslation('name', 'en', $name, isInitial: true);
        $item->modifierGroups()->attach($group->id);

        return $option->fresh();
    }

    /** Build an ADD (Extras-style) group attached to $item with one option. */
    private function makeAddGroup(Menu $menu, MenuItem $item, float $delta, string $name = 'Cheese', int $maxQty = 1): ModifierOption
    {
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]); // add/multi/min0
        $group->setTranslation('name', 'en', 'Extras', isInitial: true);
        $option = ModifierOption::factory()->create([
            'group_id' => $group->id,
            'price' => $delta,
            'max_qty' => $maxQty,
        ]);
        $option->setTranslation('name', 'en', $name, isInitial: true);
        $item->modifierGroups()->attach($group->id);

        return $option->fresh();
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
            'menu_item_name_snapshot' => 'Pizza',
            'base_price_snapshot' => '12.50',
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
    public function test_replace_group_uses_absolute_option_price(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        $option = $this->makeReplaceGroup($m, $i, 95.00);

        $response = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $option->group_id, 'option_id' => $option->id],
                ],
            ]],
        ])->assertStatus(201);

        // Absolute price replaces the dish base (12.50 ignored); base snapshot
        // is the chosen replace option's price.
        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.id'),
            'menu_item_id' => $i->id,
            'unit_price' => '95.00',
            'base_price_snapshot' => '95.00',
        ]);

        $orderItem = OrderItem::query()->where('order_id', $response->json('data.id'))->first();
        $this->assertDatabaseHas('order_item_modifiers', [
            'order_item_id' => $orderItem->id,
            'modifier_group_id' => $option->group_id,
            'modifier_option_id' => $option->id,
            'group_name_snapshot' => 'Size',
            'option_name_snapshot' => 'Large',
            'unit_price_snapshot' => '95.00',
        ]);
    }

    #[Test]
    public function test_add_group_adds_delta_to_base(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        $option = $this->makeAddGroup($m, $i, 3.00);

        $response = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $option->group_id, 'option_id' => $option->id],
                ],
            ]],
        ])->assertStatus(201);

        // 12.50 base + 3.00 delta = 15.50; base snapshot is the dish price_value.
        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.id'),
            'unit_price' => '15.50',
            'base_price_snapshot' => '12.50',
        ]);

        $orderItem = OrderItem::query()->where('order_id', $response->json('data.id'))->first();
        $this->assertDatabaseHas('order_item_modifiers', [
            'order_item_id' => $orderItem->id,
            'modifier_group_id' => $option->group_id,
            'modifier_option_id' => $option->id,
            'group_name_snapshot' => 'Extras',
            'option_name_snapshot' => 'Cheese',
            'unit_price_snapshot' => '3.00',
            'line_amount_snapshot' => '3.00',
        ]);
    }

    #[Test]
    public function test_add_group_respects_quantity(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        $option = $this->makeAddGroup($m, $i, 2.00, maxQty: 5);

        $response = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $option->group_id, 'option_id' => $option->id, 'qty' => 3],
                ],
            ]],
        ])->assertStatus(201);

        // 12.50 base + 2.00 * 3 = 18.50.
        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.id'),
            'unit_price' => '18.50',
        ]);
    }

    #[Test]
    public function test_combined_replace_and_add_pricing(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        $size = $this->makeReplaceGroup($m, $i, 75.00, name: 'Medium');
        $extra = $this->makeAddGroup($m, $i, 5.00, name: 'Bacon');

        $response = $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $size->group_id, 'option_id' => $size->id],
                    ['group_id' => $extra->group_id, 'option_id' => $extra->id],
                ],
            ]],
        ])->assertStatus(201);

        // 75 (absolute) + 5 (delta) = 80.
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'unit_price' => '80.00',
        ]);

        $orderItem = OrderItem::query()->where('order_id', $orderId)->first();
        // Two snapshot rows: one per chosen modifier.
        $this->assertSame(2, $orderItem->modifiers()->count());
        $this->assertDatabaseHas('order_item_modifiers', [
            'order_item_id' => $orderItem->id,
            'modifier_option_id' => $size->id,
            'option_name_snapshot' => 'Medium',
        ]);
        $this->assertDatabaseHas('order_item_modifiers', [
            'order_item_id' => $orderItem->id,
            'modifier_option_id' => $extra->id,
            'option_name_snapshot' => 'Bacon',
        ]);
    }

    #[Test]
    public function test_rejects_option_from_another_item(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m, 'section' => $section] = $this->setupMenu();

        // A group attached to a DIFFERENT item on the SAME menu.
        $otherItem = MenuItem::factory()->create([
            'section_id' => $section->id,
            'is_visible' => true,
            'is_orderable' => true,
        ]);
        $foreign = $this->makeAddGroup($m, $otherItem, 4.00);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $foreign->group_id, 'option_id' => $foreign->id],
                ],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0']);
    }

    #[Test]
    public function test_rejects_option_from_another_menu(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i] = $this->setupMenu();
        ['item' => $foreignItem, 'menu' => $foreignMenu] = $this->setupMenu();

        $foreign = $this->makeAddGroup($foreignMenu, $foreignItem, 4.00);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $foreign->group_id, 'option_id' => $foreign->id],
                ],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0']);
    }

    #[Test]
    public function test_rejects_missing_required_group(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        // Required replace group attached, but the order omits any selection for it.
        $this->makeReplaceGroup($m, $i, 50.00);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0']);
    }

    #[Test]
    public function test_rejects_exceeding_selection_max(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        // Single-select (max 1) group with two options; picking both exceeds max.
        $option1 = $this->makeReplaceGroup($m, $i, 60.00, name: 'Small');
        $group = ModifierGroup::query()->find($option1->group_id);
        $option2 = ModifierOption::factory()->create(['group_id' => $group->id, 'price' => 80.00]);
        $option2->setTranslation('name', 'en', 'Large', isInitial: true);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $group->id, 'option_id' => $option1->id],
                    ['group_id' => $group->id, 'option_id' => $option2->id],
                ],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0']);
    }

    #[Test]
    public function test_rejects_quantity_above_option_max_qty(): void
    {
        ['restaurant' => $r, 'table' => $t, 'item' => $i, 'menu' => $m] = $this->setupMenu();

        // Add option with max_qty 1; asking for 2 is rejected.
        $option = $this->makeAddGroup($m, $i, 2.00, maxQty: 1);

        $this->postJson('/api/v1/public/orders', [
            'restaurant_uniqid' => $r->uniqid,
            'table_uniqid' => $t->uniqid,
            'items' => [[
                'menu_item_id' => $i->id,
                'quantity' => 1,
                'selections' => [
                    ['group_id' => $option->group_id, 'option_id' => $option->id, 'qty' => 2],
                ],
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0']);
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
