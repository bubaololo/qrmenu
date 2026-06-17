<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Restaurant;
use App\Models\TranslationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModifierGroupTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_store_creates_replace_group_with_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", [
                'name' => 'Size',
                'pricing_mode' => 'replace',
                'selection_type' => 'single',
                'selection_min' => 1,
                'selection_max' => 1,
                'required' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Size')
            ->assertJsonPath('data.attributes.pricing_mode', 'replace')
            ->assertJsonPath('data.attributes.selection_type', 'single')
            ->assertJsonPath('data.attributes.required', true);

        $groupId = $response->json('data.id');
        $this->assertDatabaseHas('modifier_groups', [
            'id' => $groupId,
            'menu_id' => $menu->id,
            'pricing_mode' => 'replace',
            'selection_min' => 1,
            'required' => true,
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => ModifierGroup::class,
            'translatable_id' => $groupId,
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Size',
        ]);
    }

    #[Test]
    public function test_store_creates_add_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", [
                'name' => 'Extras',
                'pricing_mode' => 'add',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Extras')
            ->assertJsonPath('data.attributes.pricing_mode', 'add')
            // No selection_min/required given => stays optional.
            ->assertJsonPath('data.attributes.selection_min', 0)
            ->assertJsonPath('data.attributes.required', false);

        $this->assertDatabaseHas('modifier_groups', [
            'id' => $response->json('data.id'),
            'menu_id' => $menu->id,
            'pricing_mode' => 'add',
            'required' => false,
        ]);
    }

    #[Test]
    public function test_store_requires_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", ['pricing_mode' => 'add'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_store_requires_pricing_mode(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", ['name' => 'Size'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pricing_mode');
    }

    #[Test]
    public function test_store_rejects_name_over_limit(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", [
                'name' => str_repeat('a', config('limits.name') + 1),
                'pricing_mode' => 'add',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_update_modifies_preset_fields(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id, 'sort_order' => 0]);

        $this->actingAs($user)
            ->putJson("/api/v1/modifier-groups/{$group->id}", [
                'sort_order' => 3,
                'selection_type' => 'multi',
                'charge_above' => 2,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.sort_order', 3)
            ->assertJsonPath('data.attributes.selection_type', 'multi')
            ->assertJsonPath('data.attributes.charge_above', 2);

        $this->assertDatabaseHas('modifier_groups', [
            'id' => $group->id,
            'sort_order' => 3,
            'selection_type' => 'multi',
            'charge_above' => 2,
        ]);
    }

    #[Test]
    public function test_update_required_normalizes_selection_min(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        // Add factory default: not required, min 0.
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);

        // Asking for required=true forces selection_min up to at least 1.
        $this->actingAs($user)
            ->putJson("/api/v1/modifier-groups/{$group->id}", ['required' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.required', true)
            ->assertJsonPath('data.attributes.selection_min', 1);

        $this->assertDatabaseHas('modifier_groups', [
            'id' => $group->id,
            'required' => true,
            'selection_min' => 1,
        ]);
    }

    #[Test]
    public function test_update_clearing_required_recomputes_from_selection_min(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        // variation() state: required, selection_min 1.
        $group = ModifierGroup::factory()->variation()->create(['menu_id' => $menu->id]);

        // Dropping required + min 0 => required is recomputed as (min >= 1) = false.
        $this->actingAs($user)
            ->putJson("/api/v1/modifier-groups/{$group->id}", ['required' => false, 'selection_min' => 0])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.selection_min', 0)
            ->assertJsonPath('data.attributes.required', false);

        $this->assertDatabaseHas('modifier_groups', [
            'id' => $group->id,
            'selection_min' => 0,
            'required' => false,
        ]);
    }

    #[Test]
    public function test_update_selection_min_above_zero_makes_required(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        // Add factory default: not required, min 0.
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);

        // selection_min >= 1 => required recomputed to true.
        $this->actingAs($user)
            ->putJson("/api/v1/modifier-groups/{$group->id}", ['selection_min' => 2])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.selection_min', 2)
            ->assertJsonPath('data.attributes.required', true);

        $this->assertDatabaseHas('modifier_groups', [
            'id' => $group->id,
            'selection_min' => 2,
            'required' => true,
        ]);
    }

    #[Test]
    public function test_update_group_name_translation(): void
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => 'en']);
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/modifier-groups/{$group->id}", ['name' => 'Toppings'], ['X-Locale' => 'en'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => ModifierGroup::class,
            'translatable_id' => $group->id,
            'locale' => 'en',
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Toppings',
        ]);
    }

    #[Test]
    public function test_destroy_deletes_group_and_cascades_options(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $option = ModifierOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/modifier-groups/{$group->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('modifier_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('modifier_options', ['id' => $option->id]);
    }

    #[Test]
    public function test_store_option_with_price(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $group = ModifierGroup::factory()->variation()->create(['menu_id' => $menu->id]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/modifier-groups/{$group->id}/options", [
                'name' => 'Large',
                'price' => 159,
                'is_default' => true,
                'max_qty' => 3,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.name', 'Large')
            ->assertJsonPath('data.attributes.is_default', true)
            ->assertJsonPath('data.attributes.max_qty', 3);

        $optionId = $response->json('data.id');
        $this->assertDatabaseHas('modifier_options', [
            'id' => $optionId,
            'group_id' => $group->id,
            'price' => 159,
            'is_default' => true,
            'max_qty' => 3,
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => ModifierOption::class,
            'translatable_id' => $optionId,
            'field_id' => TranslationField::where('name', 'name')->value('id'),
            'value' => 'Large',
        ]);
    }

    #[Test]
    public function test_store_option_requires_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/modifier-groups/{$group->id}/options", ['price' => 10])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function test_update_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $option = ModifierOption::factory()->create(['group_id' => $group->id, 'price' => 100]);

        $this->actingAs($user)
            ->putJson("/api/v1/modifier-options/{$option->id}", ['price' => 209])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.price', '209.00');

        $this->assertDatabaseHas('modifier_options', ['id' => $option->id, 'price' => 209]);
    }

    #[Test]
    public function test_destroy_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $option = ModifierOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/modifier-options/{$option->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('modifier_options', ['id' => $option->id]);
    }

    #[Test]
    public function test_attach_items_links_group_across_sections(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $sectionA = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $sectionB = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $itemA = MenuItem::factory()->create(['section_id' => $sectionA->id]);
        $itemB = MenuItem::factory()->create(['section_id' => $sectionB->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/modifier-groups/{$group->id}/attach-items", [
                'item_ids' => [$itemA->id, $itemB->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_item_modifier_group', ['item_id' => $itemA->id, 'group_id' => $group->id]);
        $this->assertDatabaseHas('menu_item_modifier_group', ['item_id' => $itemB->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_detach_items_removes_pivot(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group->items()->attach($item->id);

        $this->actingAs($user)
            ->postJson("/api/v1/modifier-groups/{$group->id}/detach-items", [
                'item_ids' => [$item->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_modifier_group', ['item_id' => $item->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_attach_ignores_items_from_other_menus(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $otherMenu = Menu::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
        $otherSection = MenuSection::factory()->create(['menu_id' => $otherMenu->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $foreignItem = MenuItem::factory()->create(['section_id' => $otherSection->id]);

        $this->actingAs($user)
            ->postJson("/api/v1/modifier-groups/{$group->id}/attach-items", [
                'item_ids' => [$foreignItem->id],
            ])
            ->assertStatus(200);

        $this->assertDatabaseMissing('menu_item_modifier_group', ['item_id' => $foreignItem->id, 'group_id' => $group->id]);
    }

    #[Test]
    public function test_non_owner_cannot_create_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/menus/{$menu->id}/modifier-groups", [
                'name' => 'Size',
                'pricing_mode' => 'replace',
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function test_non_owner_cannot_update_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->putJson("/api/v1/modifier-groups/{$group->id}", ['sort_order' => 5])
            ->assertStatus(403);
    }

    #[Test]
    public function test_non_owner_cannot_delete_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->deleteJson("/api/v1/modifier-groups/{$group->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function test_non_owner_cannot_create_option(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/modifier-groups/{$group->id}/options", ['name' => 'Large'])
            ->assertStatus(403);
    }

    #[Test]
    public function test_index_lists_top_level_groups_with_usage_count_and_options(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $itemA = MenuItem::factory()->create(['section_id' => $section->id]);
        $itemB = MenuItem::factory()->create(['section_id' => $section->id]);

        $used = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        ModifierOption::factory()->create(['group_id' => $used->id, 'price' => 12]);
        $used->items()->attach([$itemA->id, $itemB->id]);

        $unused = ModifierGroup::factory()->create(['menu_id' => $menu->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}/modifier-groups?include=options")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $groups = collect($response->json('data'))->keyBy('id');
        $this->assertSame(2, $groups[$used->id]['attributes']['items_count']);
        $this->assertSame(0, $groups[$unused->id]['attributes']['items_count']);

        // Options are embedded when requested via include.
        $response->assertJsonFragment(['price' => '12.00']);
    }

    #[Test]
    public function test_index_is_owner_only(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menus/{$menu->id}/modifier-groups")
            ->assertStatus(403);
    }

    #[Test]
    public function test_update_item_overrides_writes_pivot_only(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id, 'selection_min' => 0, 'required' => false]);
        $group->items()->attach($item->id);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}/modifier-groups/{$group->id}", [
                'required_override' => true,
                'selection_max_override' => 2,
            ])
            ->assertOk();

        // Pivot carries the override...
        $this->assertDatabaseHas('menu_item_modifier_group', [
            'item_id' => $item->id,
            'group_id' => $group->id,
            'required_override' => true,
            'selection_max_override' => 2,
        ]);
        // ...the shared group row is untouched.
        $this->assertDatabaseHas('modifier_groups', [
            'id' => $group->id,
            'selection_min' => 0,
            'required' => false,
        ]);
    }

    #[Test]
    public function test_override_is_per_item_in_full_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $itemA = MenuItem::factory()->create(['section_id' => $section->id]);
        $itemB = MenuItem::factory()->create(['section_id' => $section->id]);
        // Shared optional add-group attached to both items.
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id, 'selection_min' => 0, 'selection_max' => null, 'required' => false]);
        $group->items()->attach([$itemA->id, $itemB->id]);

        // Make it required (max 2) only on item A.
        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$itemA->id}/modifier-groups/{$group->id}", [
                'required_override' => true,
                'selection_max_override' => 2,
            ])
            ->assertOk();

        $data = $this->actingAs($user)->getJson("/api/v1/menus/{$menu->id}")->assertOk()->json('data');
        $items = collect($data['sections'])->flatMap(fn ($s) => $s['items'])->keyBy('id');
        $groupOnA = collect($items[$itemA->id]['modifier_groups'])->firstWhere('id', $group->id);
        $groupOnB = collect($items[$itemB->id]['modifier_groups'])->firstWhere('id', $group->id);

        // Effective values differ per item; the raw overrides are exposed too.
        $this->assertTrue($groupOnA['required']);
        $this->assertSame(2, $groupOnA['selection_max']);
        $this->assertTrue($groupOnA['overrides']['required']);
        $this->assertFalse($groupOnB['required']);
        $this->assertNull($groupOnB['overrides']['required']);
    }

    #[Test]
    public function test_override_rejects_unattached_group(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]); // not attached

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}/modifier-groups/{$group->id}", ['required_override' => true])
            ->assertStatus(404);
    }

    #[Test]
    public function test_override_rejects_group_from_another_menu(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $foreignGroup = ModifierGroup::factory()->create(['menu_id' => Menu::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id])->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}/modifier-groups/{$foreignGroup->id}", ['required_override' => true])
            ->assertStatus(404);
    }

    #[Test]
    public function test_non_owner_cannot_override(): void
    {
        $restaurant = Restaurant::factory()->create();
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $group = ModifierGroup::factory()->create(['menu_id' => $menu->id]);
        $group->items()->attach($item->id);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->putJson("/api/v1/menu-items/{$item->id}/modifier-groups/{$group->id}", ['required_override' => true])
            ->assertStatus(403);
    }
}
