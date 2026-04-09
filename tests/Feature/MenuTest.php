<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    private function asWaiterOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Waiter->value]);

        return $user;
    }

    #[Test]
    public function test_unauthenticated_cannot_list_menus(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->getJson("/api/v1/restaurants/{$restaurant->id}/menus")->assertStatus(401);
    }

    #[Test]
    public function test_owner_can_list_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        Menu::factory()->count(2)->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/restaurants/{$restaurant->id}/menus")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function test_non_member_cannot_list_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/restaurants/{$restaurant->id}/menus")
            ->assertStatus(403);
    }

    #[Test]
    public function test_store_creates_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/menus", [
                'source_locale' => 'uk',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'menus')
            ->assertJsonPath('data.attributes.source_locale', 'uk');

        $this->assertDatabaseHas('menus', [
            'id' => $response->json('data.id'),
            'restaurant_id' => $restaurant->id,
            'source_locale' => 'uk',
        ]);
    }

    #[Test]
    public function test_waiter_cannot_create_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $waiter = $this->asWaiterOf($restaurant);

        $this->actingAs($waiter)
            ->postJson("/api/v1/restaurants/{$restaurant->id}/menus", ['source_locale' => 'uk'])
            ->assertStatus(403);
    }

    #[Test]
    public function test_show_returns_full_menu_tree(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $menu->id)
            ->assertJsonPath('data.sections.0.id', $section->id);
    }

    #[Test]
    public function test_show_returns_403_for_non_member(): void
    {
        $menu = Menu::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function test_update_modifies_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->putJson("/api/v1/menus/{$menu->id}", ['source_locale' => 'en'])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.source_locale', 'en');

        $this->assertDatabaseHas('menus', ['id' => $menu->id, 'source_locale' => 'en']);
    }

    #[Test]
    public function test_destroy_deletes_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    }

    #[Test]
    public function test_waiter_cannot_delete_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $waiter = $this->asWaiterOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($waiter)
            ->deleteJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function test_activate_activates_menu_and_deactivates_siblings(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu1 = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
        $menu2 = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => false]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu2->id}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_active', true);

        $this->assertDatabaseHas('menus', ['id' => $menu1->id, 'is_active' => false]);
        $this->assertDatabaseHas('menus', ['id' => $menu2->id, 'is_active' => true]);
    }

    #[Test]
    public function test_clone_creates_deep_copy(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/clone")
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'menus');

        $clonedId = $response->json('data.id');
        $this->assertNotEquals($menu->id, (int) $clonedId);
        $this->assertDatabaseHas('menus', [
            'id' => $clonedId,
            'created_from_menu_id' => $menu->id,
        ]);
    }

    #[Test]
    public function test_waiter_can_view_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $waiter = $this->asWaiterOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($waiter)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200);
    }

    #[Test]
    public function test_full_returns_nested_tree_without_lazy_loading(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $group->items()->attach($item->id);
        MenuOptionGroupOption::factory()->count(2)->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.sections.0.id', $section->id)
            ->assertJsonPath('data.sections.0.items.0.id', $item->id)
            ->assertJsonCount(2, 'data.sections.0.items.0.option_groups.0.options');
    }
}
