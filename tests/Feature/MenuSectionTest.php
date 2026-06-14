<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Icon;
use App\Models\Menu;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\TranslationField;
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
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Starters',
            'is_initial' => true,
        ]);
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

    #[Test]
    public function test_store_resolves_icon_name_to_icon_id(): void
    {
        Icon::firstOrCreate(['name' => 'noodle-bowl']);

        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => 'Hot dishes',
                'icon_name' => 'noodle-bowl',
            ])
            ->assertStatus(201);

        $sectionId = $response->json('data.id');
        $icon = Icon::where('name', 'noodle-bowl')->firstOrFail();

        $this->assertDatabaseHas('menu_sections', [
            'id' => $sectionId,
            'icon_id' => $icon->id,
        ]);
    }

    #[Test]
    public function test_store_reuses_existing_icon_by_name(): void
    {
        $existing = Icon::firstOrCreate(['name' => 'iced-coffee']);

        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => 'Coffee',
                'icon_name' => 'iced-coffee',
            ])
            ->assertStatus(201);

        $this->assertSame(1, Icon::where('name', 'iced-coffee')->count());
        $this->assertDatabaseHas('menu_sections', ['icon_id' => $existing->id]);
    }

    #[Test]
    public function test_store_rejects_unknown_icon_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => 'Bad',
                'icon_name' => 'not-a-real-icon',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('icon_name');
    }

    #[Test]
    public function test_store_rejects_name_over_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => str_repeat('a', config('limits.name') + 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_store_accepts_name_at_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/sections", [
                'name' => str_repeat('a', config('limits.name')),
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function test_update_replaces_icon_via_icon_name(): void
    {
        Icon::firstOrCreate(['name' => 'pizza']);

        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}", [
                'icon_name' => 'pizza',
            ])
            ->assertStatus(200);

        $icon = Icon::where('name', 'pizza')->firstOrFail();
        $this->assertDatabaseHas('menu_sections', [
            'id' => $section->id,
            'icon_id' => $icon->id,
        ]);
    }

    #[Test]
    public function test_update_clears_icon_with_null_icon_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $icon = Icon::firstOrCreate(['name' => 'dish-01']);
        $section = MenuSection::factory()->create([
            'menu_id' => $menu->id,
            'icon_id' => $icon->id,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}", [
                'icon_name' => null,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_sections', [
            'id' => $section->id,
            'icon_id' => null,
        ]);
    }
}
