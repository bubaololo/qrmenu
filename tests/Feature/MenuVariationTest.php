<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\MenuVariation;
use App\Models\MenuVariationOption;
use App\Models\Restaurant;
use App\Models\TranslationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuVariationTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_store_creates_variation_with_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/variations", ['name' => 'Size'])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Size');

        $variationId = $response->json('data.id');
        $this->assertDatabaseHas('menu_variations', ['id' => $variationId, 'menu_id' => $menu->id]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuVariation::class,
            'translatable_id' => $variationId,
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Size',
        ]);
    }

    #[Test]
    public function test_store_requires_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/variations", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_store_rejects_name_over_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/variations", [
                'name' => str_repeat('a', config('limits.name') + 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_update_modifies_variation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id, 'sort_order' => 0]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-variations/{$variation->id}", ['sort_order' => 3])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.sort_order', 3);

        $this->assertDatabaseHas('menu_variations', ['id' => $variation->id, 'sort_order' => 3]);
    }

    #[Test]
    public function test_destroy_deletes_variation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-variations/{$variation->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_variations', ['id' => $variation->id]);
    }

    #[Test]
    public function test_store_option_with_absolute_price(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menu-variations/{$variation->id}/options", [
                'name' => 'Large',
                'price' => 159,
                'is_default' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Large')
            ->assertJsonPath('data.attributes.is_default', true);

        $optionId = $response->json('data.id');
        $this->assertDatabaseHas('menu_variation_options', [
            'id' => $optionId,
            'variation_id' => $variation->id,
            'price' => 159,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function test_update_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $option = MenuVariationOption::factory()->create(['variation_id' => $variation->id, 'price' => 100]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-variation-options/{$option->id}", ['price' => 209])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.price', '209.00');

        $this->assertDatabaseHas('menu_variation_options', ['id' => $option->id, 'price' => 209]);
    }

    #[Test]
    public function test_destroy_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $option = MenuVariationOption::factory()->create(['variation_id' => $variation->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-variation-options/{$option->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_variation_options', ['id' => $option->id]);
    }

    #[Test]
    public function test_attach_items_links_variation_across_sections(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $sectionA = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $sectionB = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $itemA = MenuItem::factory()->create(['section_id' => $sectionA->id]);
        $itemB = MenuItem::factory()->create(['section_id' => $sectionB->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-variations/{$variation->id}/attach-items", [
                'item_ids' => [$itemA->id, $itemB->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_item_variation', ['item_id' => $itemA->id, 'variation_id' => $variation->id]);
        $this->assertDatabaseHas('menu_item_variation', ['item_id' => $itemB->id, 'variation_id' => $variation->id]);
    }

    #[Test]
    public function test_detach_items_removes_pivot(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $variation->items()->attach($item->id);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-variations/{$variation->id}/detach-items", [
                'item_ids' => [$item->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_variation', ['item_id' => $item->id, 'variation_id' => $variation->id]);
    }

    #[Test]
    public function test_attach_ignores_items_from_other_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $otherMenu = Menu::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
        $otherSection = MenuSection::factory()->create(['menu_id' => $otherMenu->id]);
        $variation = MenuVariation::factory()->create(['menu_id' => $menu->id]);
        $foreignItem = MenuItem::factory()->create(['section_id' => $otherSection->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-variations/{$variation->id}/attach-items", [
                'item_ids' => [$foreignItem->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_variation', ['item_id' => $foreignItem->id, 'variation_id' => $variation->id]);
    }
}
