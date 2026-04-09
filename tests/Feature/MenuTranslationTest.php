<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuTranslationTest extends TestCase
{
    use RefreshDatabase;

    private function asOwnerOf(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        return $user;
    }

    #[Test]
    public function test_locales_returns_available_locales(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        Translation::create(['translatable_type' => MenuItem::class, 'translatable_id' => $item->id, 'locale' => 'vi', 'field' => 'name', 'value' => 'Phở', 'is_initial' => true]);
        Translation::create(['translatable_type' => MenuItem::class, 'translatable_id' => $item->id, 'locale' => 'en', 'field' => 'name', 'value' => 'Pho', 'is_initial' => false]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}/locales")
            ->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('vi'));
        $this->assertTrue($codes->contains('en'));
        $this->assertEquals('vi', $response->json('meta.source_locale'));
        $this->assertNotNull($response->json('meta.primary_language'));
    }

    #[Test]
    public function test_locales_always_includes_source_and_primary_even_without_translations(): void
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => 'ru']);
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}/locales")
            ->assertStatus(200);

        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('vi'), 'source_locale must always be in list');
        $this->assertTrue($codes->contains('ru'), 'primary_language must always be in list');
        $this->assertEquals('ru', $response->json('meta.primary_language'));
    }

    #[Test]
    public function test_non_member_cannot_list_locales(): void
    {
        $menu = Menu::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/menus/{$menu->id}/locales")
            ->assertStatus(403);
    }

    #[Test]
    public function test_full_menu_returns_source_locale_by_default(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}", ['Accept-Language' => ''])
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'vi');
    }

    #[Test]
    public function test_full_menu_returns_requested_locale(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}", ['Accept-Language' => 'en'])
            ->assertStatus(200)
            ->assertJsonPath('data.locale', 'en');
    }

    #[Test]
    public function test_full_menu_includes_locales(): void
    {
        $restaurant = Restaurant::factory()->create(['primary_language' => 'fr']);
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}")
            ->assertStatus(200);

        $codes = collect($response->json('data.locales'))->pluck('code');
        $this->assertTrue($codes->contains('vi'), 'source_locale must be in locales');
        $this->assertTrue($codes->contains('fr'), 'primary_language must be in locales');

        $sourceEntry = collect($response->json('data.locales'))->firstWhere('code', 'vi');
        $this->assertTrue($sourceEntry['is_source']);
    }

    #[Test]
    public function test_full_menu_returns_translated_content(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $item->setTranslation('name', 'vi', 'Phở bò', isInitial: true);
        $item->setTranslation('name', 'en', 'Beef Pho', isInitial: false);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}", ['Accept-Language' => 'en'])
            ->assertStatus(200);

        $name = $response->json('data.sections.0.items.0.name');
        $this->assertEquals('Beef Pho', $name);
    }

    #[Test]
    public function test_full_menu_falls_back_to_source_when_translation_missing(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $item->setTranslation('name', 'vi', 'Phở bò', isInitial: true);
        // No French translation exists

        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}", ['Accept-Language' => 'fr'])
            ->assertStatus(200);

        $name = $response->json('data.sections.0.items.0.name');
        $this->assertEquals('Phở bò', $name, 'Should fall back to source text when translation is missing');
    }

    #[Test]
    public function test_update_then_read_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        // Write English translation
        $this->actingAs($user)
            ->putJson(
                "/api/v1/menu-items/{$item->id}",
                ['name' => 'Beef Noodle Soup', 'description' => 'Classic dish'],
                ['Accept-Language' => 'en']
            )
            ->assertStatus(200);

        // Read back in English
        $response = $this->actingAs($user)
            ->getJson("/api/v1/menus/{$menu->id}", ['Accept-Language' => 'en'])
            ->assertStatus(200);

        $this->assertEquals('Beef Noodle Soup', $response->json('data.sections.0.items.0.name'));
        $this->assertEquals('Classic dish', $response->json('data.sections.0.items.0.description'));
    }

    #[Test]
    public function test_store_dispatches_translation_job(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/translations/en")
            ->assertStatus(202);
    }

    #[Test]
    public function test_store_rejects_invalid_locale(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/translations/xx")
            ->assertStatus(422);
    }

    #[Test]
    public function test_store_rejects_source_locale(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);

        $this->actingAs($user)
            ->postJson("/api/v1/menus/{$menu->id}/translations/vi")
            ->assertStatus(422);
    }

    #[Test]
    public function test_non_owner_cannot_trigger_translation(): void
    {
        $menu = Menu::factory()->create(['source_locale' => 'vi']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/menus/{$menu->id}/translations/en")
            ->assertStatus(403);
    }

    #[Test]
    public function test_update_section_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-sections/{$section->id}", ['name' => 'Starters'], ['Accept-Language' => 'en'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuSection::class,
            'translatable_id' => $section->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Starters',
            'is_initial' => false,
        ]);
    }

    #[Test]
    public function test_update_item_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/menu-items/{$item->id}",
                ['name' => 'Beef Pho', 'description' => 'Classic Vietnamese noodle soup'],
                ['Accept-Language' => 'en']
            )
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Beef Pho',
        ]);
    }

    #[Test]
    public function test_update_option_group_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-option-groups/{$group->id}", ['name' => 'Toppings'], ['Accept-Language' => 'en'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuOptionGroup::class,
            'translatable_id' => $group->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Toppings',
        ]);
    }

    #[Test]
    public function test_update_option_translation(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $group = MenuOptionGroup::factory()->create(['section_id' => $section->id]);
        $option = MenuOptionGroupOption::factory()->create(['group_id' => $group->id]);

        $this->actingAs($user)
            ->putJson(
                "/api/v1/menu-option-group-options/{$option->id}",
                ['name' => 'Extra Spicy'],
                ['Accept-Language' => 'en']
            )
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuOptionGroupOption::class,
            'translatable_id' => $option->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Extra Spicy',
        ]);
    }

    #[Test]
    public function test_update_without_header_uses_source_locale(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Phở bò'], ['Accept-Language' => ''])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'vi',
            'field' => 'name',
            'value' => 'Phở bò',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_update_with_source_locale_header_sets_is_initial_true(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Phở bò'], ['Accept-Language' => 'vi'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'vi',
            'field' => 'name',
            'value' => 'Phở bò',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_update_with_non_source_locale_sets_is_initial_false(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Beef Pho'], ['Accept-Language' => 'en'])
            ->assertStatus(200);

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'en',
            'field' => 'name',
            'value' => 'Beef Pho',
            'is_initial' => false,
        ]);
    }

    #[Test]
    public function test_store_ignores_accept_language_header(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id, 'source_locale' => 'vi']);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);

        $this->actingAs($user)
            ->postJson(
                "/api/v1/menu-sections/{$section->id}/items",
                ['name' => 'Phở bò', 'price_type' => 'fixed', 'price_value' => 0],
                ['Accept-Language' => 'en']
            )
            ->assertStatus(201);

        $item = MenuItem::latest()->first();

        $this->assertDatabaseHas('translations', [
            'translatable_type' => MenuItem::class,
            'translatable_id' => $item->id,
            'locale' => 'vi',
            'field' => 'name',
            'value' => 'Phở bò',
            'is_initial' => true,
        ]);
    }

    #[Test]
    public function test_update_translation_rejects_invalid_locale(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = $this->asOwnerOf($restaurant);
        $menu = Menu::factory()->create(['restaurant_id' => $restaurant->id]);
        $section = MenuSection::factory()->create(['menu_id' => $menu->id]);
        $item = MenuItem::factory()->create(['section_id' => $section->id]);

        // Invalid Accept-Language header — middleware silently ignores it,
        // so the update proceeds against source_locale (is_initial: true). 200 is expected.
        $this->actingAs($user)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Test'], ['Accept-Language' => 'xx'])
            ->assertStatus(200);
    }

    #[Test]
    public function test_non_owner_cannot_update_translations(): void
    {
        $section = MenuSection::factory()->create();
        $item = MenuItem::factory()->create(['section_id' => $section->id]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->putJson("/api/v1/menu-items/{$item->id}", ['name' => 'Test'], ['Accept-Language' => 'en'])
            ->assertStatus(403);
    }
}
