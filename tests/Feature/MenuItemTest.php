<?php

namespace Tests\Feature;

use App\Enums\PriceType;
use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_owner_can_list_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        MenuItem::factory()->count(3)->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menu-sections/{$section->id}/items")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_non_member_cannot_list_items(): void
    {
        $section = MenuSection::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menu-sections/{$section->id}/items")
            ->assertStatus(403);
    }

    #[Test]
    public function test_store_creates_item_with_translations(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menu-sections/{$section->id}/items", [
                'name' => 'Pho Bo',
                'description' => 'Beef noodle soup',
                'price_type' => PriceType::Fixed->value,
                'price_value' => '5.50',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'menu_items')
            ->assertJsonPath('data.attributes.name', 'Pho Bo')
            ->assertJsonPath('data.attributes.description', 'Beef noodle soup');

        $itemId = $response->json('data.id');
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $itemId,
            'field' => 'name',
            'value' => 'Pho Bo',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_show_returns_item(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menu-items/{$item->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $item->id);
    }

    #[Test]
    public function test_update_modifies_item(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id, 'starred' => false]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['starred' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.starred', true);

        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'starred' => true]);
    }

    #[Test]
    public function test_destroy_deletes_item(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-items/{$item->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    #[Test]
    public function test_reorder_updates_sort_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $i1 = MenuItem::factory()->create(['section_id' => $section->id, 'sort_order' => 0]);
        $i2 = MenuItem::factory()->create(['section_id' => $section->id, 'sort_order' => 1]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}/items/reorder", [
                'order' => [
                    ['id' => $i1->id, 'sort_order' => 1],
                    ['id' => $i2->id, 'sort_order' => 0],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_items', ['id' => $i1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('menu_items', ['id' => $i2->id, 'sort_order' => 0]);
    }

    #[Test]
    public function test_non_owner_gets_403_on_store(): void
    {
        $section = MenuSection::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/menu-sections/{$section->id}/items", ['name' => 'Test'])
            ->assertStatus(403);
    }
}
