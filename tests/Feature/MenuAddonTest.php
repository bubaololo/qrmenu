<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuAddon;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\TranslationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuAddonTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_store_creates_addon_with_name_and_delta_price(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/addons", [
                'name' => 'Extra cheese',
                'price' => 20,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Extra cheese')
            ->assertJsonPath('data.attributes.price', '20.00');

        $addonId = $response->json('data.id');
        $this->assertDatabaseHas('menu_addons', ['id' => $addonId, 'menu_id' => $menu->id, 'price' => 20]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuAddon::class,
            'translatable_id' => $addonId,
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Extra cheese',
        ]);
    }

    #[Test]
    public function test_store_requires_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/addons", ['price' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_update_modifies_addon(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id, 'price' => 10]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-addons/{$addon->id}", ['price' => 25])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.price', '25.00');

        $this->assertDatabaseHas('menu_addons', ['id' => $addon->id, 'price' => 25]);
    }

    #[Test]
    public function test_destroy_deletes_addon(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-addons/{$addon->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_addons', ['id' => $addon->id]);
    }

    #[Test]
    public function test_attach_items_links_addon_to_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id]);
        $item1 = MenuItem::factory()->create(['section_id' => $section->id]);
        $item2 = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-addons/{$addon->id}/attach-items", [
                'item_ids' => [$item1->id, $item2->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_item_addon', ['item_id' => $item1->id, 'addon_id' => $addon->id]);
        $this->assertDatabaseHas('menu_item_addon', ['item_id' => $item2->id, 'addon_id' => $addon->id]);
    }

    #[Test]
    public function test_detach_items_removes_pivot(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $addon->items()->attach($item->id);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-addons/{$addon->id}/detach-items", [
                'item_ids' => [$item->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_addon', ['item_id' => $item->id, 'addon_id' => $addon->id]);
    }

    #[Test]
    public function test_attach_ignores_items_from_other_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $otherMenu = Menu::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
        $otherSection = MenuSection::factory()->create(['menu_id' => $otherMenu->id]);
        $addon = MenuAddon::factory()->create(['menu_id' => $menu->id]);
        $foreignItem = MenuItem::factory()->create(['section_id' => $otherSection->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-addons/{$addon->id}/attach-items", [
                'item_ids' => [$foreignItem->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_addon', ['item_id' => $foreignItem->id, 'addon_id' => $addon->id]);
    }
}
