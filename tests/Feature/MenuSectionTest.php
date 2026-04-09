<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuSectionTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_owner_can_list_sections(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        MenuSection::factory()->count(3)->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}/sections")
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function test_non_member_cannot_list_sections(): void
    {
        $menu = Menu::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menus/{$menu->id}/sections")
            ->assertStatus(403);
    }

    #[Test]
    public function test_store_creates_section_with_name_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => 'Starters',
                'sort_order' => 0,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'menu_sections')
            ->assertJsonPath('data.attributes.name', 'Starters');

        $sectionId = $response->json('data.id');
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuSection::class,
            'translatable_id' => $sectionId,
            'field' => 'name',
            'value' => 'Starters',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_show_returns_section(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->getJson("/api/v1/menu-sections/{$section->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', (string) $section->id);
    }

    #[Test]
    public function test_update_modifies_section(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}", ['sort_order' => 5])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.sort_order', 5);

        $this->assertDatabaseHas('menu_sections', ['id' => $section->id, 'sort_order' => 5]);
    }

    #[Test]
    public function test_destroy_deletes_section(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-sections/{$section->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_sections', ['id' => $section->id]);
    }

    #[Test]
    public function test_reorder_updates_sort_orders(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $s1 = MenuSection::factory()->create(['menu_id' => $menu->id, 'sort_order' => 0]);
        $s2 = MenuSection::factory()->create(['menu_id' => $menu->id, 'sort_order' => 1]);

        $this->actingAs($user)
            ->putJson("/api/v1/menus/{$menu->id}/sections/reorder", [
                'order' => [
                    ['id' => $s1->id, 'sort_order' => 1],
                    ['id' => $s2->id, 'sort_order' => 0],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_sections', ['id' => $s1->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('menu_sections', ['id' => $s2->id, 'sort_order' => 0]);
    }

    #[Test]
    public function test_non_owner_gets_403_on_store(): void
    {
        $menu = Menu::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/menus/{$menu->id}/sections", ['name' => 'Test'])
            ->assertStatus(403);
    }
}
