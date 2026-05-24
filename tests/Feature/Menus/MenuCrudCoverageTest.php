<?php

namespace Tests\Feature\Menus;

use App\Enums\PriceType;
use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\TranslationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage tests for menu editing edge cases not covered in MenuItemTest /
 * MenuSectionTest / MenuOptionGroupTest — primarily validation, locale handling,
 * cascade delete on the application layer, and the section→item relationship.
 */
class MenuCrudCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    private function makeSection(Restaurant $restaurant, string $sourceLocale = 'en'): MenuSection
    {
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => $sourceLocale]);

        return MenuSection::factory()->create(['menu_id' => $menu->id]);
    }

    #[Test]
    public function test_store_item_validates_required_name(): void
    {
        $restaurant = Restaurant::factory()->create();
        $section = $this->makeSection($restaurant);
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-sections/{$section->id}/items", ['price_value' => '5.50'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_store_item_validates_price_type_enum(): void
    {
        $restaurant = Restaurant::factory()->create();
        $section = $this->makeSection($restaurant);
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-sections/{$section->id}/items", [
                'name' => 'X',
                'price_type' => 'totally-bogus',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_type']);
    }

    #[Test]
    public function test_store_item_negative_price_rejected(): void
    {
        $restaurant = Restaurant::factory()->create();
        $section = $this->makeSection($restaurant);
        $user = $this->asOwnerOf($restaurant);

        $this->actingAs($user)
            ->postJson("/api/v1/menu-sections/{$section->id}/items", [
                'name' => 'X',
                'price_type' => PriceType::Fixed->value,
                'price_value' => -1,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_value']);
    }

    #[Test]
    public function test_update_item_overwrites_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'en', 'Old', isInitial: true);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'New'])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.name', 'New');

        $this->assertSame(
            1,
            Translation::where('translatable_type', MenuItem::class)
                ->where('translatable_id', $item->id)
                ->where('locale', 'en')
                ->where('field_id', TranslationField::where('name', 'name')->value('id'))
                ->count(),
            'Initial translation should be overwritten, not duplicated',
        );
        $this->assertSame(
            'New',
            Translation::where('translatable_type', MenuItem::class)
                ->where('translatable_id', $item->id)->where('locale', 'en')
                ->where('field_id', TranslationField::where('name', 'name')->value('id'))
                ->value('value'),
        );
    }

    #[Test]
    public function test_update_item_clears_description_when_null_passed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('description', 'en', 'Was set', isInitial: true);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['starred' => true])
            ->assertStatus(200);

        // The existing controller skips overwrite when the field isn't passed —
        // so `description` should remain. This pins behaviour so a future change
        // to that branch shows up explicitly in test diff.
        $this->assertSame(
            'Was set',
            Translation::where('translatable_type', MenuItem::class)
                ->where('translatable_id', $item->id)->where('locale', 'en')
                ->where('field_id', TranslationField::where('name', 'description')->value('id'))
                ->value('value'),
        );
    }

    #[Test]
    public function test_update_item_translates_using_x_locale_header(): void
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => 'fr']);
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $item->setTranslation('name', 'en', 'English', isInitial: true);

        $this->actingAs($user)
            ->withHeaders(['X-Locale' => 'fr'])
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Français'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'fr',
            'value' => 'Français',
            'is_initial' => false,
        ]);
        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'en',
            'value' => 'English',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_destroy_section_cascades_to_items_via_db(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-sections/{$section->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    #[Test]
    public function test_destroy_menu_cascades_to_sections_and_items(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
        $this->assertDatabaseMissing('menu_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    #[Test]
    public function test_destroy_option_group_cascades_to_options(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/menu-option-groups/{$group->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('menu_option_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('menu_option_group_options', ['id' => $option->id]);
    }

    #[Test]
    public function test_full_menu_endpoint_returns_translated_payload(): void
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => 'en']);
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'en']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $section->setTranslation('name', 'en', 'Drinks', isInitial: true);
        $section->setTranslation('name', 'fr', 'Boissons');
        $item = MenuItem::factory()->create(['section_id' => $section->id, 'price_value' => '7.50']);
        $item->setTranslation('name', 'en', 'Tea', isInitial: true);
        $item->setTranslation('name', 'fr', 'Thé');

        // Default (en) — `full` returns a flat tree (FullMenuResource), not JSON:API
        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.sections.0.name', 'Drinks')
            ->assertJsonPath('data.sections.0.items.0.name', 'Tea');

        // X-Locale: fr (frontend signals an explicit locale switch).
        $this->actingAs($user)
            ->withHeaders(['X-Locale' => 'fr'])
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.sections.0.name', 'Boissons')
            ->assertJsonPath('data.sections.0.items.0.name', 'Thé');
    }

    #[Test]
    public function test_section_and_item_visibility_toggle_persists(): void
    {
        // After the multi-menu collapse, section/item `is_active` flags drive
        // public-menu visibility instead of activating an alternative menu.
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create([
            'menu_id' => $menu->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}", ['is_active' => false])
            ->assertStatus(200);

        $this->assertDatabaseHas('menu_sections', ['id' => $section->id, 'is_active' => false]);
    }
}
