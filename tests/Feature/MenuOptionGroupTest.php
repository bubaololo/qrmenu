<?php

namespace Tests\Feature;

use App\Enums\OptionGroupKind;
use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\TranslationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuOptionGroupTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_store_creates_option_group_with_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/option-groups", [
                'name' => 'ADD ON',
                'kind' => OptionGroupKind::Addon->value,
                'allow_multiple' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'ADD ON')
            ->assertJsonPath('data.attributes.kind', OptionGroupKind::Addon->value)
            ->assertJsonPath('data.attributes.allow_multiple', true);

        $groupId = $response->json('data.id');
        $this->assertDatabaseHas('menu_option_groups', ['id' => $groupId, 'menu_id' => $menu->id, 'kind' => 'addon']);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuOptionGroup::class,
            'translatable_id' => $groupId,
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'ADD ON',
        ]);
    }

    #[Test]
    public function test_store_requires_kind(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/option-groups", ['name' => 'Size'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kind');
    }

    #[Test]
    public function test_store_rejects_name_over_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/option-groups", [
                'name' => str_repeat('a', config('limits.name') + 1),
                'kind' => OptionGroupKind::Addon->value,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_update_modifies_option_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id, 'required' => false]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-option-groups/{$group->id}", ['required' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.required', true);

        $this->assertDatabaseHas('menu_option_groups', ['id' => $group->id, 'required' => true]);
    }

    #[Test]
    public function test_destroy_deletes_option_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-option-groups/{$group->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_option_groups', ['id' => $group->id]);
    }

    #[Test]
    public function test_attach_items_links_group_to_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id]);
        $item1 = MenuItem::factory()->create(['section_id' => $section->id]);
        $item2 = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/attach-items", [
                'item_ids' => [$item1->id, $item2->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_item_option_group', ['item_id' => $item1->id, 'group_id' => $group->id]);
        $this->assertDatabaseHas('menu_item_option_group', ['item_id' => $item2->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_attach_links_group_to_items_across_sections(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $sectionA = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $sectionB = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id]);
        $itemA = MenuItem::factory()->create(['section_id' => $sectionA->id]);
        $itemB = MenuItem::factory()->create(['section_id' => $sectionB->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/attach-items", [
                'item_ids' => [$itemA->id, $itemB->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_item_option_group', ['item_id' => $itemA->id, 'group_id' => $group->id]);
        $this->assertDatabaseHas('menu_item_option_group', ['item_id' => $itemB->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_detach_items_removes_pivot(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group->items()->attach($item->id);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/detach-items", [
                'item_ids' => [$item->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_option_group', ['item_id' => $item->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_attach_ignores_items_from_other_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $otherMenu = Menu::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $otherSection = MenuSection::factory()->create(['menu_id' => $otherMenu->id]);
        $group = MenuOptionGroup::factory()->create(['menu_id' => $menu->id]);
        $foreignItem = MenuItem::factory()->create(['section_id' => $otherSection->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-option-groups/{$group->id}/attach-items", [
                'item_ids' => [$foreignItem->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_option_group', ['item_id' => $foreignItem->id, 'group_id' => $group->id]);
    }
}
